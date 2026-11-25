<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Test\Unit\ViewModel;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Url\EncoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResource;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceContextInterface;
use PixelPerfect\NewsletterSignup\Service\InvitationDecision;
use PixelPerfect\NewsletterSignup\Service\SubscriptionStatusCheck;
use PixelPerfect\NewsletterSignup\ViewModel\ConfirmationPageInvitation;
use Psr\Log\LoggerInterface;

class ConfirmationPageInvitationTest extends TestCase
{
    private const EMAIL = 'jane.doe@example.com';

    /**
     * Subscriber status per stored email on website 1
     *
     * @var array<string, int>
     */
    private array $subscribers = [];

    /** @var array<string, string> store-view configuration of store 1 */
    private array $config = [
        'newsletter/general/active' => '1',
        'newsletter/subscription/allow_guest_subscribe' => '1',
        'newsletter/signup_confirmation_page/enabled' => '1',
        'newsletter/signup_confirmation_page/hide_unsubscribed' => '0',
        'newsletter/signup_confirmation_page/pending_note_text' => 'Check your inbox.',
        'newsletter/signup_confirmation_page/subscribed_note_text' => 'You are subscribed.',
    ];

    /**
     * Checkout sign-up choice per quote id; a quote id without an entry does not exist
     *
     * @var array<int, mixed>
     */
    private array $quotes = [55 => null];

    private bool $statusLookupFails = false;

    /** @var array<string, mixed> data of the last order */
    private array $order = [
        'entity_id' => 100,
        'store_id' => 1,
        'customer_email' => self::EMAIL,
        'customer_is_guest' => 1,
        'quote_id' => 55,
    ];

    public function testAShopperWhoTickedTheCheckoutBoxAndMustConfirmIsToldToCheckTheirInbox(): void
    {
        $this->quotes[55] = '1';
        $this->subscribers[self::EMAIL] = 2;

        $viewModel = $this->buildViewModel();

        $this->assertSame('Check your inbox.', $viewModel->getNoteText());
        $this->assertFalse($viewModel->isVisible());
    }

    public function testAnUnconfirmedSubscriberWhoTickedTheBoxIsToldToCheckTheirInboxToo(): void
    {
        $this->quotes[55] = '1';
        $this->subscribers[self::EMAIL] = 4;

        $this->assertSame('Check your inbox.', $this->buildViewModel()->getNoteText());
    }

    public function testAShopperWhoTickedTheCheckoutBoxAndIsSubscribedIsToldSo(): void
    {
        $this->quotes[55] = '1';
        $this->subscribers[self::EMAIL] = 1;

        $viewModel = $this->buildViewModel();

        $this->assertSame('You are subscribed.', $viewModel->getNoteText());
        $this->assertFalse($viewModel->isVisible());
    }

    public function testWithoutATickTheBlockInvitesAsBefore(): void
    {
        $this->quotes[55] = '0';

        $viewModel = $this->buildViewModel();

        $this->assertSame('', $viewModel->getNoteText());
        $this->assertTrue($viewModel->isVisible());
    }

    public function testWithoutATickAConfirmedSubscriberGetsNoNote(): void
    {
        $this->subscribers[self::EMAIL] = 1;

        $this->assertSame('', $this->buildViewModel()->getNoteText());
    }

    public function testATickWhoseSignUpFailedLeavesTheInvitation(): void
    {
        $this->quotes[55] = '1';

        $viewModel = $this->buildViewModel();

        $this->assertSame('', $viewModel->getNoteText());
        $this->assertTrue($viewModel->isVisible());
    }

    public function testATickedShopperWhoUnsubscribedSinceGetsNoNote(): void
    {
        $this->quotes[55] = '1';
        $this->subscribers[self::EMAIL] = 3;

        $this->assertSame('', $this->buildViewModel()->getNoteText());
    }

    public function testTheNoteFollowsTheBlockSwitch(): void
    {
        $this->quotes[55] = '1';
        $this->subscribers[self::EMAIL] = 2;
        $this->config['newsletter/signup_confirmation_page/enabled'] = '0';

        $this->assertSame('', $this->buildViewModel()->getNoteText());
    }

    public function testTheNoteFollowsTheNewsletterSwitch(): void
    {
        $this->quotes[55] = '1';
        $this->subscribers[self::EMAIL] = 2;
        $this->config['newsletter/general/active'] = '0';

        $this->assertSame('', $this->buildViewModel()->getNoteText());
    }

    public function testAStatusThatCannotBeReadMeansNoNote(): void
    {
        $this->quotes[55] = '1';
        $this->statusLookupFails = true;

        $this->assertSame('', $this->buildViewModel()->getNoteText());
    }

    public function testTheNoteTextIsReadForTheOrdersStoreView(): void
    {
        $this->quotes[55] = '1';
        $this->subscribers[self::EMAIL] = 2;
        $this->order['store_id'] = 2;

        $this->assertSame('Check your inbox in store 2.', $this->buildViewModel()->getNoteText());
    }

    public function testAMissingQuoteMeansNoNote(): void
    {
        $this->order['quote_id'] = 56;
        $this->subscribers[self::EMAIL] = 2;

        $this->assertSame('', $this->buildViewModel()->getNoteText());
    }

    public function testNoNoteWithoutAnOrderInTheSession(): void
    {
        $this->order['entity_id'] = null;
        $this->quotes[55] = '1';
        $this->subscribers[self::EMAIL] = 2;

        $this->assertSame('', $this->buildViewModel()->getNoteText());
    }

    public function testAGuestWhoseEmailIsNotSubscribedIsInvited(): void
    {
        $this->assertTrue($this->buildViewModel()->isVisible());
    }

    public function testAConfirmedSubscriberIsNotInvited(): void
    {
        $this->subscribers[self::EMAIL] = 1;

        $this->assertFalse($this->buildViewModel()->isVisible());
    }

    public function testNothingShowsWithoutAnOrderInTheSession(): void
    {
        $this->order['entity_id'] = null;

        $this->assertFalse($this->buildViewModel()->isVisible());
    }

    private function buildViewModel(): ConfirmationPageInvitation
    {
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData', 'getId'])
            ->getMock();
        $order->method('getId')->willReturnCallback(fn (): mixed => $this->order['entity_id']);
        $order->method('getData')->willReturnCallback(
            fn (string $key = '', mixed $index = null): mixed => $key === '' ? $this->order : ($this->order[$key] ?? null)
        );
        $checkoutSession = $this->getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLastRealOrder'])
            ->getMock();
        $checkoutSession->method('getLastRealOrder')->willReturn($order);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn (string $path): bool => (bool)($this->config[$path] ?? false)
        );
        $scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path, string $scope = 'default', mixed $scopeId = null): ?string => $scopeId === 2
                && $path === 'newsletter/signup_confirmation_page/pending_note_text'
                ? 'Check your inbox in store 2.'
                : $this->config[$path] ?? null
        );

        $store = $this->createMock(StoreInterface::class);
        $store->method('getWebsiteId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $subscriberResource = $this->createMock(SubscriberResource::class);
        $subscriberResource->method('loadBySubscriberEmail')->willReturnCallback(
            fn (string $email, int $websiteId): array => $this->statusLookupFails
                ? throw new \RuntimeException('lookup failed')
                : ($websiteId === 1 && isset($this->subscribers[$email])
                ? ['subscriber_status' => (string)$this->subscribers[$email]]
                : [])
        );
        $statusCheck = new SubscriptionStatusCheck($subscriberResource);
        $logger = $this->createMock(LoggerInterface::class);

        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $cartRepository->method('get')->willReturnCallback(
            function (int $quoteId): Quote {
                if (!array_key_exists($quoteId, $this->quotes)) {
                    throw new NoSuchEntityException(__('No such quote'));
                }
                $quote = $this->getMockBuilder(Quote::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods(['getData'])
                    ->getMock();
                $quote->method('getData')->willReturnCallback(
                    fn (string $key = ''): mixed => $key === 'pixelperfect_newsletter_opt_in'
                        ? $this->quotes[$quoteId]
                        : null
                );
                return $quote;
            }
        );

        return new ConfirmationPageInvitation(
            $checkoutSession,
            new InvitationDecision(
                $statusCheck,
                $scopeConfig,
                $storeManager,
                $logger,
                ['confirmation_page' => 'newsletter/signup_confirmation_page']
            ),
            $this->createMock(SignupSourceContextInterface::class),
            $scopeConfig,
            $this->createMock(UrlInterface::class),
            $this->createMock(EncoderInterface::class),
            $storeManager,
            $logger,
            $cartRepository,
            $statusCheck
        );
    }
}
