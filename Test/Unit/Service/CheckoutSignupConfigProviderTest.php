<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Test\Unit\Service;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutOptInInterface;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutSignupInterface;
use PixelPerfect\NewsletterSignup\Service\CheckoutSignupConfigProvider;
use Psr\Log\LoggerInterface;

class CheckoutSignupConfigProviderTest extends TestCase
{
    /** @var array<string, string> */
    private array $config = [
        'newsletter/general/active' => '1',
        'newsletter/signup_checkout/enabled' => '1',
        'newsletter/signup_checkout/benefit_text' => 'Ten percent off your next order.',
    ];

    /** @var array<int, bool> invitation per cart id */
    private array $invited = [];

    private bool $lookupFails = false;

    /** @var list<string> */
    private array $loggedErrors = [];

    public function testAnInvitedCartShowsTheLineUnticked(): void
    {
        $this->invited[9] = true;

        $this->assertSame(
            ['enabled' => true, 'invited' => true, 'optIn' => false, 'benefitText' => 'Ten percent off your next order.'],
            $this->configFor($this->quote(9, null))
        );
    }

    public function testTheShoppersEarlierTickIsKept(): void
    {
        $this->invited[9] = true;

        $this->assertTrue($this->configFor($this->quote(9, 1))['optIn']);
    }

    public function testAnEarlierTickIsDroppedWhenTheCartIsNoLongerInvited(): void
    {
        $this->invited[9] = false;

        $config = $this->configFor($this->quote(9, 1));

        $this->assertFalse($config['invited']);
        $this->assertFalse($config['optIn']);
    }

    public function testACartNotSavedYetIsNotInvitedAtPageLoad(): void
    {
        $this->invited[0] = true;

        $this->assertSame(
            ['enabled' => true, 'invited' => false, 'optIn' => false, 'benefitText' => 'Ten percent off your next order.'],
            $this->configFor($this->quote(null, null))
        );
    }

    public function testASwitchedOffLineIsOnlyReportedAsOff(): void
    {
        $this->config['newsletter/signup_checkout/enabled'] = '0';

        $this->assertSame(['enabled' => false], $this->configFor($this->quote(9, null)));
    }

    public function testTheLineIsOffWhenTheNewsletterIsSwitchedOff(): void
    {
        $this->config['newsletter/general/active'] = '0';

        $this->assertSame(['enabled' => false], $this->configFor($this->quote(9, null)));
    }

    public function testAFailingLookupHidesTheLineAndIsLogged(): void
    {
        $this->lookupFails = true;

        $config = $this->configFor($this->quote(9, 1));

        $this->assertFalse($config['invited']);
        $this->assertFalse($config['optIn']);
        $this->assertCount(1, $this->loggedErrors);
    }

    /**
     * @return array<string, bool|string>
     */
    private function configFor(Quote $quote): array
    {
        $session = $this->createMock(CheckoutSession::class);
        $session->method('getQuote')->willReturn($quote);

        $signup = $this->createMock(CheckoutSignupInterface::class);
        $signup->method('isInvited')->willReturnCallback(
            fn (int $cartId): bool => $this->lookupFails
                ? throw new \RuntimeException('Database gone')
                : ($this->invited[$cartId] ?? false)
        );

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            fn (string $path, string $scope): bool => $scope === ScopeInterface::SCOPE_STORE
                && (bool)($this->config[$path] ?? false)
        );
        $scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path, string $scope): ?string => $scope === ScopeInterface::SCOPE_STORE
                ? ($this->config[$path] ?? null)
                : null
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(
            function (string|\Stringable $message): void {
                $this->loggedErrors[] = (string)$message;
            }
        );

        $provider = new CheckoutSignupConfigProvider($session, $signup, $scopeConfig, $logger);

        return $provider->getConfig()[CheckoutSignupConfigProvider::CONFIG_KEY];
    }

    private function quote(?int $id, ?int $optIn): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods(['getId'])->getMock();
        $quote->method('getId')->willReturn($id);
        $quote->setData(CheckoutOptInInterface::QUOTE_FIELD, $optIn);

        return $quote;
    }
}
