<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Test\Unit\Service;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Session\Generic as Session;
use Magento\Framework\Stdlib\DateTime\DateTime;
use PHPUnit\Framework\TestCase;
use PixelPerfect\NewsletterSignup\Model\ResourceModel\SignupSource as SignupSourceResource;
use PixelPerfect\NewsletterSignup\Service\SignupSourceContext;
use PixelPerfect\NewsletterSignup\Service\SignupSourceList;
use PixelPerfect\NewsletterSignup\Service\SignupSourceRecorder;

class SignupSourceRecorderTest extends TestCase
{
    private const NOW = '2026-11-25 10:00:00';

    /**
     * Source rows written so far, keyed by subscriber id
     *
     * @var array<int, array{code: string, recorded_at: string}>
     */
    private array $records = [];

    /** @var array<string, mixed> */
    private array $sessionData = [];

    private ?string $extraSourcesConfig = null;

    private SignupSourceContext $context;

    private SignupSourceRecorder $recorder;

    protected function setUp(): void
    {
        $this->context = $this->buildContext();
        $this->recorder = $this->buildRecorder($this->context);
    }

    public function testASignUpWithoutAnySourceIsRecordedAsOther(): void
    {
        $this->recorder->record(7);

        $this->assertSame('other', $this->records[7]['code']);
    }

    public function testTheTimeOfTheSignUpIsRecorded(): void
    {
        $this->recorder->record(7);

        $this->assertSame(self::NOW, $this->records[7]['recorded_at']);
    }

    public function testASourceRememberedEarlierInTheVisitIsCredited(): void
    {
        $this->context->rememberSource('order_email');

        $this->recorder->record(7);

        $this->assertSame('order_email', $this->records[7]['code']);
    }

    public function testTheRememberedSourceSurvivesIntoALaterRequestOfTheSameVisit(): void
    {
        $this->context->rememberSource('website_footer');
        $laterRequest = $this->buildContext();

        $this->buildRecorder($laterRequest)->record(7);

        $this->assertSame('website_footer', $this->records[7]['code']);
    }

    public function testASourceSetByTheEntryPointWinsOverTheRememberedOne(): void
    {
        $this->context->rememberSource('order_email');
        $this->context->useSource('checkout');

        $this->recorder->record(7);

        $this->assertSame('checkout', $this->records[7]['code']);
    }

    public function testAnUnknownRememberedValueIsIgnored(): void
    {
        $this->context->rememberSource('not-a-source');

        $this->recorder->record(7);

        $this->assertSame('other', $this->records[7]['code']);
    }

    public function testAnUnknownValueDoesNotReplaceAKnownRememberedSource(): void
    {
        $this->context->rememberSource('shipment_email');
        $this->context->rememberSource('not-a-source');

        $this->recorder->record(7);

        $this->assertSame('shipment_email', $this->records[7]['code']);
    }

    public function testAnEmptyRememberedValueIsIgnored(): void
    {
        $this->context->rememberSource('');

        $this->recorder->record(7);

        $this->assertSame('other', $this->records[7]['code']);
    }

    public function testAnUnknownSourceSetByTheEntryPointFallsBackToTheRememberedOne(): void
    {
        $this->context->rememberSource('order_email');
        $this->context->useSource('not-a-source');

        $this->recorder->record(7);

        $this->assertSame('order_email', $this->records[7]['code']);
    }

    public function testTheFirstSourceIsKeptWhenTheSameSubscriberSignsUpAgain(): void
    {
        $this->context->useSource('checkout');
        $this->recorder->record(7);

        $this->context->useSource('confirmation_page');
        $this->recorder->record(7);

        $this->assertSame('checkout', $this->records[7]['code']);
    }

    public function testEachSubscriberGetsTheirOwnSource(): void
    {
        $this->context->useSource('checkout');
        $this->recorder->record(7);

        $this->buildRecorder($this->buildContext())->record(8);

        $this->assertSame('checkout', $this->records[7]['code']);
        $this->assertSame('other', $this->records[8]['code']);
    }

    public function testASourceAddedInTheConfigurationIsAccepted(): void
    {
        $this->extraSourcesConfig = '{"_1":{"code":"landing_page","label":"Landing page"}}';
        $context = $this->buildContext();
        $context->rememberSource('landing_page');

        $this->buildRecorder($context)->record(7);

        $this->assertSame('landing_page', $this->records[7]['code']);
    }

    public function testTheSourceListHoldsTheDefaultsAndTheConfiguredSourcesWithTheirLabels(): void
    {
        $this->extraSourcesConfig = '{"_1":{"code":"landing_page","label":"Landing page"},'
            . '"_2":{"code":"Bad Code!","label":"Broken"},"_3":{"code":"","label":"Empty"},'
            . '"_4":{"code":"checkout","label":"Renamed checkout"},"_5":{"code":"no_label","label":""},'
            . '"_6":"not a row","_7":{"code":" spaced ","label":" Spaced "}}';

        $labels = [];
        $sources = $this->buildList()->getAll();
        $this->assertTrue(array_is_list($sources));
        foreach ($sources as $source) {
            $labels[$source->getCode()] = $source->getLabel();
        }

        $this->assertSame(
            [
                'checkout' => 'Checkout',
                'confirmation_page' => 'Order confirmation page',
                'order_email' => 'Order email',
                'shipment_email' => 'Shipment email',
                'website_footer' => 'Website footer',
                'signup_page' => 'Sign-up page',
                'other' => 'Other',
                'landing_page' => 'Landing page',
                'no_label' => 'no_label',
                'spaced' => 'Spaced',
            ],
            $labels
        );
    }

    /**
     * @dataProvider unreadableConfigurations
     */
    public function testAnUnreadableSourceConfigurationLeavesTheDefaults(?string $config): void
    {
        $this->extraSourcesConfig = $config;

        $this->assertCount(7, $this->buildList()->getAll());
        $this->assertTrue($this->buildList()->isKnown('signup_page'));
        $this->assertFalse($this->buildList()->isKnown('landing_page'));
    }

    /**
     * @return array<string, array{0: string|null}>
     */
    public function unreadableConfigurations(): array
    {
        return ['not set' => [null], 'not a list' => ['"not a list"'], 'not JSON' => ['{broken']];
    }

    private function buildList(): SignupSourceList
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            fn (string $path): ?string => $path === 'newsletter/signup_source/extra_sources'
                ? $this->extraSourcesConfig
                : null
        );

        return new SignupSourceList(
            $scopeConfig,
            new Json(),
            [
                'checkout' => 'Checkout',
                'confirmation_page' => 'Order confirmation page',
                'order_email' => 'Order email',
                'shipment_email' => 'Shipment email',
                'website_footer' => 'Website footer',
                'signup_page' => 'Sign-up page',
                'other' => 'Other',
            ]
        );
    }

    private function buildContext(): SignupSourceContext
    {
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getData'])
            ->addMethods(['setData'])
            ->getMock();
        $session->method('getData')->willReturnCallback(
            fn (string $key = ''): mixed => $this->sessionData[$key] ?? null
        );
        $session->method('setData')->willReturnCallback(
            function (string $key, mixed $value) use ($session): Session {
                $this->sessionData[$key] = $value;
                return $session;
            }
        );

        return new SignupSourceContext($session, $this->buildList());
    }

    private function buildRecorder(SignupSourceContext $context): SignupSourceRecorder
    {
        $resource = $this->createMock(SignupSourceResource::class);
        $resource->method('getSourceCode')->willReturnCallback(
            fn (int $subscriberId): ?string => $this->records[$subscriberId]['code'] ?? null
        );
        $resource->method('saveSource')->willReturnCallback(
            function (int $subscriberId, string $code, string $recordedAt): void {
                $this->records[$subscriberId] = ['code' => $code, 'recorded_at' => $recordedAt];
            }
        );
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn(self::NOW);

        return new SignupSourceRecorder($resource, $context, $dateTime);
    }
}
