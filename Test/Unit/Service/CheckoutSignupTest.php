<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResource;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutOptInInterface;
use PixelPerfect\NewsletterSignup\Service\CheckoutSignup;
use PixelPerfect\NewsletterSignup\Service\GuestCheckoutSignup;
use PixelPerfect\NewsletterSignup\Service\InvitationDecision;
use PixelPerfect\NewsletterSignup\Service\SubscriptionStatusCheck;
use Psr\Log\LoggerInterface;

class CheckoutSignupTest extends TestCase
{
    private const MASKED_ID = 'aB3dE5fG7hJ9kL1mN3pQ5rS7tU9vW1xY';

    /** @var array<int, Quote> active quotes by id */
    private array $quotes = [];

    /** @var list<int> ids of the quotes saved */
    private array $saved = [];

    /** @var array<string, array<string, mixed>> subscriber rows on website 1 by email */
    private array $rows = [];

    /** @var array<string, string> store-view configuration */
    private array $config = [
        'newsletter/general/active' => '1',
        'newsletter/subscription/allow_guest_subscribe' => '1',
        'newsletter/signup_checkout/enabled' => '1',
        'newsletter/signup_checkout/hide_unsubscribed' => '0',
    ];

    public function testAGuestWhoseEmailNeverSubscribedIsInvited(): void
    {
        $this->guestQuote(5, 'jane.doe@example.com');

        $this->assertTrue($this->signup()->isInvited(5));
    }

    public function testAGuestIsAskedThroughTheirOwnMaskedCart(): void
    {
        $this->guestQuote(5, 'jane.doe@example.com');

        $this->assertTrue($this->guestSignup()->isInvited(self::MASKED_ID));
    }

    public function testAGuestWhoseEmailIsPendingIsNotInvited(): void
    {
        $this->guestQuote(5, 'jane.doe@example.com');
        $this->rows['jane.doe@example.com'] = ['subscriber_status' => '2'];

        $this->assertFalse($this->signup()->isInvited(5));
    }

    public function testAGuestWithoutEmailYetIsNotInvited(): void
    {
        $this->guestQuote(5, null);

        $this->assertFalse($this->signup()->isInvited(5));
    }

    public function testTheShippingAddressEmailCountsWhenTheBillingAddressHasNone(): void
    {
        $this->guestQuote(5, null, 'jane.doe@example.com');

        $this->assertTrue($this->signup()->isInvited(5));
    }

    public function testAGuestIsNotInvitedWhenMagentoRefusesGuestSubscriptions(): void
    {
        $this->guestQuote(5, 'jane.doe@example.com');
        $this->config['newsletter/subscription/allow_guest_subscribe'] = '0';

        $this->assertFalse($this->signup()->isInvited(5));
    }

    public function testACustomerIsAskedWithTheirAccountEmail(): void
    {
        $this->customerQuote(6, 42, 'john.smith@example.com');
        $this->config['newsletter/subscription/allow_guest_subscribe'] = '0';

        $this->assertTrue($this->signup()->isInvited(6));
        $this->rows['john.smith@example.com'] = ['subscriber_status' => '1'];
        $this->assertFalse($this->signup()->isInvited(6));
    }

    public function testNobodyIsInvitedWhenTheCheckoutLineIsSwitchedOff(): void
    {
        $this->guestQuote(5, 'jane.doe@example.com');
        $this->config['newsletter/signup_checkout/enabled'] = '0';

        $this->assertFalse($this->signup()->isInvited(5));
    }

    public function testATickedBoxIsStoredOnTheQuote(): void
    {
        $this->guestQuote(5, 'jane.doe@example.com');

        $this->assertTrue($this->guestSignup()->setOptIn(self::MASKED_ID, true));
        $this->assertSame(1, $this->quotes[5]->getData(CheckoutOptInInterface::QUOTE_FIELD));
        $this->assertSame([5], $this->saved);
    }

    public function testAnUntickedBoxIsStoredOnTheQuote(): void
    {
        $this->guestQuote(5, 'jane.doe@example.com');
        $this->quotes[5]->setData(CheckoutOptInInterface::QUOTE_FIELD, 1);

        $this->assertFalse($this->signup()->setOptIn(5, false));
        $this->assertSame(0, $this->quotes[5]->getData(CheckoutOptInInterface::QUOTE_FIELD));
        $this->assertSame([5], $this->saved);
    }

    public function testATickedBoxIsRefusedWhenTheShopperIsNotInvited(): void
    {
        $this->guestQuote(5, 'jane.doe@example.com');
        $this->rows['jane.doe@example.com'] = ['subscriber_status' => '1'];

        $this->assertFalse($this->signup()->setOptIn(5, true));
        $this->assertSame(0, $this->quotes[5]->getData(CheckoutOptInInterface::QUOTE_FIELD));
    }

    public function testAnUnknownMaskedCartIsRefused(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $this->guestSignup()->isInvited('unknown');
    }

    private function guestQuote(int $id, ?string $billingEmail, ?string $shippingEmail = null): void
    {
        $this->quotes[$id] = $this->quote($id, $billingEmail, $shippingEmail);
    }

    private function customerQuote(int $id, int $customerId, string $email): void
    {
        $quote = $this->quote($id, null, null);
        $quote->setData('customer_id', $customerId);
        $quote->setData('customer_email', $email);
        $this->quotes[$id] = $quote;
    }

    private function quote(int $id, ?string $billingEmail, ?string $shippingEmail): Quote
    {
        $billing = $this->createMock(Address::class);
        $billing->method('getEmail')->willReturn($billingEmail);
        $shipping = $this->createMock(Address::class);
        $shipping->method('getEmail')->willReturn($shippingEmail);

        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getBillingAddress', 'getShippingAddress', 'getStoreId'])
            ->getMock();
        $quote->method('getBillingAddress')->willReturn($billing);
        $quote->method('getShippingAddress')->willReturn($shipping);
        $quote->method('getStoreId')->willReturn(1);
        $quote->setData('entity_id', $id);

        return $quote;
    }

    private function signup(): CheckoutSignup
    {
        $repository = $this->createMock(CartRepositoryInterface::class);
        $repository->method('getActive')->willReturnCallback(
            fn (int $id): Quote => $this->quotes[$id] ?? throw new NoSuchEntityException()
        );
        $repository->method('save')->willReturnCallback(
            function (Quote $quote): void {
                $this->saved[] = (int)$quote->getData('entity_id');
            }
        );

        $resource = $this->createMock(SubscriberResource::class);
        $resource->method('loadBySubscriberEmail')->willReturnCallback(
            fn (string $email, int $websiteId): array => $websiteId === 1 ? ($this->rows[$email] ?? []) : []
        );
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn (string $path, string $scope = 'default', mixed $scopeCode = null): bool => $scopeCode === 1
                && (bool)($this->config[$path] ?? false)
        );
        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $decision = new InvitationDecision(
            new SubscriptionStatusCheck($resource),
            $scopeConfig,
            $storeManager,
            $this->createMock(LoggerInterface::class),
            ['checkout' => 'newsletter/signup_checkout']
        );

        return new CheckoutSignup($repository, $decision);
    }

    private function guestSignup(): GuestCheckoutSignup
    {
        $masks = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $masks->method('execute')->willReturnCallback(
            fn (string $maskedId): int => $maskedId === self::MASKED_ID ? 5 : throw new NoSuchEntityException()
        );

        return new GuestCheckoutSignup($masks, $this->signup());
    }
}
