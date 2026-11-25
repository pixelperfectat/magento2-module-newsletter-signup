<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResource;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use PixelPerfect\NewsletterSignup\Service\InvitationDecision;
use PixelPerfect\NewsletterSignup\Service\SubscriptionStatusCheck;
use Psr\Log\LoggerInterface;

class InvitationDecisionTest extends TestCase
{
    private const EMAIL = 'jane.doe@example.com';

    /**
     * Subscriber rows Magento knows, keyed by website id and stored email
     *
     * @var array<int, array<string, array<string, mixed>>>
     */
    private array $rows = [];

    /**
     * Store-view configuration, keyed by store id and path
     *
     * @var array<int, array<string, string>>
     */
    private array $config = [];

    /** @var array<int, int> website id per store id */
    private array $websites = [1 => 1, 2 => 2];

    private bool $lookupFails = false;

    /** @var list<array{message: string, context: array<string, mixed>}> */
    private array $loggedErrors = [];

    private InvitationDecision $decision;

    protected function setUp(): void
    {
        foreach ([1, 2] as $storeId) {
            $this->config[$storeId] = [
                'newsletter/general/active' => '1',
                'newsletter/subscription/allow_guest_subscribe' => '1',
                'newsletter/signup_checkout/enabled' => '1',
                'newsletter/signup_checkout/hide_unsubscribed' => '0',
                'newsletter/signup_confirmation_page/enabled' => '1',
                'newsletter/signup_confirmation_page/hide_unsubscribed' => '0',
            ];
        }
        $this->decision = $this->buildDecision();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function places(): array
    {
        return ['checkout' => ['checkout'], 'confirmation page' => ['confirmation_page']];
    }

    /**
     * @dataProvider places
     */
    public function testAnEmailThatNeverSubscribedIsInvited(string $place): void
    {
        $this->assertTrue($this->decision->shouldInvite($place, self::EMAIL, 1, false));
    }

    /**
     * @dataProvider places
     */
    public function testAPendingSubscriberIsNotInvited(string $place): void
    {
        $this->subscriber(2);

        $this->assertFalse($this->decision->shouldInvite($place, self::EMAIL, 1, false));
    }

    /**
     * @dataProvider places
     */
    public function testAnUnconfirmedSubscriberIsNotInvited(string $place): void
    {
        $this->subscriber(4);

        $this->assertFalse($this->decision->shouldInvite($place, self::EMAIL, 1, false));
    }

    /**
     * @dataProvider places
     */
    public function testAConfirmedSubscriberIsNotInvited(string $place): void
    {
        $this->subscriber(1);

        $this->assertFalse($this->decision->shouldInvite($place, self::EMAIL, 1, false));
    }

    /**
     * @dataProvider places
     */
    public function testAnUnsubscribedAddressIsInvitedBackByDefault(string $place): void
    {
        $this->subscriber(3);

        $this->assertTrue($this->decision->shouldInvite($place, self::EMAIL, 1, false));
    }

    /**
     * @dataProvider places
     */
    public function testAnUnsubscribedAddressIsNotInvitedWhereThePlaceHidesIt(string $place): void
    {
        $this->subscriber(3);
        $this->config[1]["newsletter/signup_{$place}/hide_unsubscribed"] = '1';

        $this->assertFalse($this->decision->shouldInvite($place, self::EMAIL, 1, false));
    }

    public function testHidingUnsubscribedAtOnePlaceLeavesTheOtherPlaceInviting(): void
    {
        $this->subscriber(3);
        $this->config[1]['newsletter/signup_checkout/hide_unsubscribed'] = '1';

        $this->assertFalse($this->decision->shouldInvite('checkout', self::EMAIL, 1, false));
        $this->assertTrue($this->decision->shouldInvite('confirmation_page', self::EMAIL, 1, false));
    }

    /**
     * @dataProvider places
     */
    public function testTheHideSettingDoesNotAffectAddressesThatNeverSubscribed(string $place): void
    {
        $this->config[1]["newsletter/signup_{$place}/hide_unsubscribed"] = '1';

        $this->assertTrue($this->decision->shouldInvite($place, self::EMAIL, 1, false));
    }

    /**
     * @dataProvider places
     */
    public function testNobodyIsInvitedWhereThePlaceIsSwitchedOff(string $place): void
    {
        $this->config[1]["newsletter/signup_{$place}/enabled"] = '0';

        $this->assertFalse($this->decision->shouldInvite($place, self::EMAIL, 1, false));
    }

    public function testSwitchingOffOnePlaceLeavesTheOtherInviting(): void
    {
        $this->config[1]['newsletter/signup_checkout/enabled'] = '0';

        $this->assertTrue($this->decision->shouldInvite('confirmation_page', self::EMAIL, 1, false));
    }

    public function testAPlaceSwitchedOffInOneStoreViewStillInvitesInAnother(): void
    {
        $this->config[1]['newsletter/signup_confirmation_page/enabled'] = '0';

        $this->assertFalse($this->decision->shouldInvite('confirmation_page', self::EMAIL, 1, false));
        $this->assertTrue($this->decision->shouldInvite('confirmation_page', self::EMAIL, 2, false));
    }

    /**
     * @dataProvider places
     */
    public function testNobodyIsInvitedWhenTheNewsletterIsSwitchedOff(string $place): void
    {
        $this->config[1]['newsletter/general/active'] = '0';

        $this->assertFalse($this->decision->shouldInvite($place, self::EMAIL, 1, false));
    }

    /**
     * @dataProvider places
     */
    public function testAGuestIsNotInvitedWhenMagentoRefusesGuestSubscriptions(string $place): void
    {
        $this->config[1]['newsletter/subscription/allow_guest_subscribe'] = '0';

        $this->assertFalse($this->decision->shouldInvite($place, self::EMAIL, 1, true));
    }

    public function testACustomerIsInvitedWhenMagentoRefusesGuestSubscriptions(): void
    {
        $this->config[1]['newsletter/subscription/allow_guest_subscribe'] = '0';

        $this->assertTrue($this->decision->shouldInvite('checkout', self::EMAIL, 1, false));
    }

    public function testAGuestIsInvitedWhenMagentoAllowsGuestSubscriptions(): void
    {
        $this->assertTrue($this->decision->shouldInvite('checkout', self::EMAIL, 1, true));
    }

    public function testAnUnknownPlaceNeverInvites(): void
    {
        $this->assertFalse($this->decision->shouldInvite('footer', self::EMAIL, 1, false));
    }

    public function testAnEmptyEmailIsNotInvited(): void
    {
        $this->assertFalse($this->decision->shouldInvite('checkout', '  ', 1, false));
    }

    public function testTheStatusIsReadOnTheWebsiteOfTheStoreView(): void
    {
        $this->rows[2][self::EMAIL] = ['subscriber_status' => '1'];

        $this->assertTrue($this->decision->shouldInvite('checkout', self::EMAIL, 1, false));
        $this->assertFalse($this->decision->shouldInvite('checkout', self::EMAIL, 2, false));
    }

    public function testAPlaceAddedByAnotherModuleIsDecidedByItsOwnSettings(): void
    {
        $this->config[1]['newsletter/signup_footer/enabled'] = '1';
        $this->config[1]['newsletter/signup_footer/hide_unsubscribed'] = '1';
        $this->subscriber(3);
        $decision = $this->buildDecision(['footer' => 'newsletter/signup_footer']);

        $this->assertFalse($decision->shouldInvite('footer', self::EMAIL, 1, false));
        $this->assertTrue($decision->shouldInvite('checkout', 'john.smith@example.com', 1, false));
    }

    public function testAFailingStatusLookupInvitesNobodyAndIsLogged(): void
    {
        $this->lookupFails = true;

        $this->assertFalse($this->decision->shouldInvite('checkout', self::EMAIL, 1, false));
        $this->assertCount(1, $this->loggedErrors);
        $this->assertStringContainsString('Database gone', $this->loggedErrors[0]['message']);
        $this->assertInstanceOf(\RuntimeException::class, $this->loggedErrors[0]['context']['exception'] ?? null);
    }

    private function subscriber(int $status): void
    {
        $this->rows[1][self::EMAIL] = ['subscriber_status' => (string)$status];
    }

    /**
     * @param array<string, string> $extraPlaces
     */
    private function buildDecision(array $extraPlaces = []): InvitationDecision
    {
        $resource = $this->createMock(SubscriberResource::class);
        $resource->method('loadBySubscriberEmail')->willReturnCallback(
            function (string $email, int $websiteId): array {
                if ($this->lookupFails) {
                    throw new \RuntimeException('Database gone');
                }
                return $this->rows[$websiteId][$email] ?? [];
            }
        );

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            function (string $path, string $scope = 'default', mixed $scopeCode = null): ?string {
                $this->assertSame(ScopeInterface::SCOPE_STORE, $scope);
                return $this->config[(int)$scopeCode][$path] ?? null;
            }
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            function (string $path, string $scope = 'default', mixed $scopeCode = null): bool {
                $this->assertSame(ScopeInterface::SCOPE_STORE, $scope);
                return (bool)($this->config[(int)$scopeCode][$path] ?? false);
            }
        );

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturnCallback(
            function (mixed $storeId): StoreInterface {
                $store = $this->createMock(StoreInterface::class);
                $store->method('getWebsiteId')->willReturn($this->websites[(int)$storeId]);
                return $store;
            }
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(
            function (string|\Stringable $message, array $context = []): void {
                $this->loggedErrors[] = ['message' => (string)$message, 'context' => $context];
            }
        );

        return new InvitationDecision(
            new SubscriptionStatusCheck($resource),
            $scopeConfig,
            $storeManager,
            $logger,
            array_merge(
                [
                    'checkout' => 'newsletter/signup_checkout',
                    'confirmation_page' => 'newsletter/signup_confirmation_page',
                ],
                $extraPlaces
            )
        );
    }
}
