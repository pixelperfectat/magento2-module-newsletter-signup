<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Session\Generic as Session;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\TestCase;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutOptInInterface;
use PixelPerfect\NewsletterSignup\Service\CheckoutOptIn;
use PixelPerfect\NewsletterSignup\Service\SignupSourceContext;
use PixelPerfect\NewsletterSignup\Service\SignupSourceList;
use Psr\Log\LoggerInterface;

class CheckoutOptInTest extends TestCase
{
    /**
     * Calls Magento's subscription manager received
     *
     * @var list<array{method: string, email?: string, customerId?: int, storeId: int}>
     */
    private array $subscriptions = [];

    /** @var list<array{message: string, context: array<string, mixed>}> */
    private array $loggedErrors = [];

    private bool $subscriptionFails = false;

    private SignupSourceContext $sourceContext;

    private CheckoutOptIn $optIn;

    protected function setUp(): void
    {
        $this->sourceContext = $this->buildSourceContext();
        $this->optIn = $this->buildOptIn();
    }

    public function testATickedBoxSignsTheGuestOrderEmailUpOnTheOrderStore(): void
    {
        $this->optIn->subscribeAfterOrder($this->quote(true), $this->guestOrder('jane.doe@example.com', 2));

        $this->assertSame(
            [['method' => 'subscribe', 'email' => 'jane.doe@example.com', 'storeId' => 2]],
            $this->subscriptions
        );
    }

    public function testATickedBoxSignsTheCustomerAccountUpOnTheOrderStore(): void
    {
        $this->optIn->subscribeAfterOrder($this->quote(true), $this->customerOrder(42, 'john.smith@example.com', 3));

        $this->assertSame(
            [['method' => 'subscribeCustomer', 'customerId' => 42, 'storeId' => 3]],
            $this->subscriptions
        );
    }

    public function testAnOrderWithoutCustomerAccountIsSignedUpByItsEmail(): void
    {
        $this->optIn->subscribeAfterOrder($this->quote(true), $this->customerOrder(0, 'jane.doe@example.com', 2));

        $this->assertSame(
            [['method' => 'subscribe', 'email' => 'jane.doe@example.com', 'storeId' => 2]],
            $this->subscriptions
        );
    }

    public function testASignUpFromTheCheckoutIsCreditedToTheCheckout(): void
    {
        $this->sourceContext->rememberSource('order_email');

        $this->optIn->subscribeAfterOrder($this->quote(true), $this->guestOrder('jane.doe@example.com', 2));

        $this->assertSame('checkout', $this->sourceContext->getSource());
    }

    public function testACustomerSignUpFromTheCheckoutIsCreditedToTheCheckout(): void
    {
        $this->sourceContext->rememberSource('order_email');

        $this->optIn->subscribeAfterOrder($this->quote(true), $this->customerOrder(42, 'john.smith@example.com', 3));

        $this->assertSame('checkout', $this->sourceContext->getSource());
    }

    public function testAnUntickedBoxSignsNobodyUp(): void
    {
        $this->optIn->subscribeAfterOrder($this->quote(false), $this->guestOrder('jane.doe@example.com', 2));

        $this->assertSame([], $this->subscriptions);
        $this->assertSame('other', $this->sourceContext->getSource());
    }

    public function testAQuoteWithoutAChoiceSignsNobodyUp(): void
    {
        $this->optIn->subscribeAfterOrder($this->quote(null), $this->guestOrder('jane.doe@example.com', 2));

        $this->assertSame([], $this->subscriptions);
    }

    public function testAGuestOrderWithoutEmailSignsNobodyUp(): void
    {
        $this->optIn->subscribeAfterOrder($this->quote(true), $this->guestOrder(' ', 2));

        $this->assertSame([], $this->subscriptions);
    }

    public function testAFailingSignUpIsLoggedAndDoesNotReachTheOrder(): void
    {
        $this->subscriptionFails = true;

        $this->optIn->subscribeAfterOrder($this->quote(true), $this->guestOrder('jane.doe@example.com', 2));

        $this->assertCount(1, $this->loggedErrors);
        $this->assertSame(
            'Newsletter sign-up: the checkout sign-up failed: Mail server gone',
            $this->loggedErrors[0]['message']
        );
        $this->assertInstanceOf(\RuntimeException::class, $this->loggedErrors[0]['context']['exception'] ?? null);
    }

    private function quote(?bool $optIn): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        if ($optIn !== null) {
            $quote->setData(CheckoutOptInInterface::QUOTE_FIELD, $optIn ? 1 : 0);
        }

        return $quote;
    }

    private function guestOrder(string $email, int $storeId): OrderInterface
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerEmail')->willReturn($email);
        // Magento hands ids over as strings
        $order->method('getStoreId')->willReturn((string)$storeId);
        $order->method('getCustomerIsGuest')->willReturn(1);
        $order->method('getCustomerId')->willReturn(null);

        return $order;
    }

    private function customerOrder(int $customerId, string $email, int $storeId): OrderInterface
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getCustomerEmail')->willReturn($email);
        $order->method('getStoreId')->willReturn((string)$storeId);
        $order->method('getCustomerIsGuest')->willReturn(0);
        $order->method('getCustomerId')->willReturn((string)$customerId);

        return $order;
    }

    private function buildOptIn(): CheckoutOptIn
    {
        $manager = $this->createMock(SubscriptionManagerInterface::class);
        $manager->method('subscribe')->willReturnCallback(
            function (string $email, int $storeId): Subscriber {
                $this->failIfAsked();
                $this->subscriptions[] = ['method' => 'subscribe', 'email' => $email, 'storeId' => $storeId];
                return $this->createMock(Subscriber::class);
            }
        );
        $manager->method('subscribeCustomer')->willReturnCallback(
            function (int $customerId, int $storeId): Subscriber {
                $this->failIfAsked();
                $this->subscriptions[] = [
                    'method' => 'subscribeCustomer',
                    'customerId' => $customerId,
                    'storeId' => $storeId,
                ];
                return $this->createMock(Subscriber::class);
            }
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(
            function (string|\Stringable $message, array $context = []): void {
                $this->loggedErrors[] = ['message' => (string)$message, 'context' => $context];
            }
        );

        return new CheckoutOptIn($manager, $this->sourceContext, $logger);
    }

    private function failIfAsked(): void
    {
        if ($this->subscriptionFails) {
            throw new \RuntimeException('Mail server gone');
        }
    }

    private function buildSourceContext(): SignupSourceContext
    {
        $data = [];
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->addMethods(['setData'])
            ->getMock();
        $session->method('getData')->willReturnCallback(
            function (string $key = '') use (&$data): mixed {
                return $data[$key] ?? null;
            }
        );
        $session->method('setData')->willReturnCallback(
            function (string $key, mixed $value) use (&$data, $session): Session {
                $data[$key] = $value;
                return $session;
            }
        );
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);

        return new SignupSourceContext(
            $session,
            new SignupSourceList(
                $scopeConfig,
                new Json(),
                ['checkout' => 'Checkout', 'order_email' => 'Order email', 'other' => 'Other']
            )
        );
    }
}
