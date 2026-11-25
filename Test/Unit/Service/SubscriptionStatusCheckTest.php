<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Test\Unit\Service;

use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResource;
use PHPUnit\Framework\TestCase;
use PixelPerfect\NewsletterSignup\Api\Data\SubscriptionStatus;
use PixelPerfect\NewsletterSignup\Service\SubscriptionStatusCheck;

class SubscriptionStatusCheckTest extends TestCase
{
    /**
     * Subscriber rows Magento knows, keyed by website id and stored email
     *
     * @var array<int, array<string, array<string, mixed>>>
     */
    private array $rows = [];

    private SubscriptionStatusCheck $check;

    protected function setUp(): void
    {
        $resource = $this->createMock(SubscriberResource::class);
        $resource->method('loadBySubscriberEmail')->willReturnCallback(
            fn (string $email, int $websiteId): array => $this->rows[$websiteId][$email] ?? []
        );
        $this->check = new SubscriptionStatusCheck($resource);
    }

    public function testAnEmailWithoutSubscriberRecordIsNotSubscribed(): void
    {
        $this->assertSame(
            SubscriptionStatus::NotSubscribed,
            $this->check->getStatus('jane.doe@example.com', 1)
        );
    }

    public function testASubscriberWhoClickedTheConfirmationLinkIsConfirmed(): void
    {
        $this->rows[1]['jane.doe@example.com'] = ['subscriber_status' => '1'];

        $this->assertSame(
            SubscriptionStatus::Confirmed,
            $this->check->getStatus('jane.doe@example.com', 1)
        );
    }

    public function testASubscriberWhoHasNotClickedTheConfirmationLinkIsPending(): void
    {
        $this->rows[1]['jane.doe@example.com'] = ['subscriber_status' => '2'];

        $this->assertSame(
            SubscriptionStatus::Pending,
            $this->check->getStatus('jane.doe@example.com', 1)
        );
    }

    public function testAnUnconfirmedSubscriberIsPending(): void
    {
        $this->rows[1]['jane.doe@example.com'] = ['subscriber_status' => '4'];

        $this->assertSame(
            SubscriptionStatus::Pending,
            $this->check->getStatus('jane.doe@example.com', 1)
        );
    }

    public function testTheEmailIsMatchedIgnoringCaseAndSurroundingSpaces(): void
    {
        $this->rows[1]['jane.doe@example.com'] = ['subscriber_status' => '1'];

        $this->assertSame(
            SubscriptionStatus::Confirmed,
            $this->check->getStatus(" Jane.Doe@Example.com \n", 1)
        );
    }

    public function testNonAsciiCapitalsInTheEmailAreMatchedToo(): void
    {
        $this->rows[1]['änne.doe@example.com'] = ['subscriber_status' => '1'];

        $this->assertSame(
            SubscriptionStatus::Confirmed,
            $this->check->getStatus('ÄNNE.DOE@example.com', 1)
        );
    }

    public function testAnUnknownSubscriberStatusCountsAsNotSubscribed(): void
    {
        $this->rows[1]['jane.doe@example.com'] = ['subscriber_status' => '9'];

        $this->assertSame(
            SubscriptionStatus::NotSubscribed,
            $this->check->getStatus('jane.doe@example.com', 1)
        );
    }

    public function testASubscriptionOnAnotherWebsiteDoesNotCountOnThisOne(): void
    {
        $this->rows[2]['jane.doe@example.com'] = ['subscriber_status' => '1'];

        $this->assertSame(
            SubscriptionStatus::NotSubscribed,
            $this->check->getStatus('jane.doe@example.com', 1)
        );
        $this->assertSame(
            SubscriptionStatus::Confirmed,
            $this->check->getStatus('jane.doe@example.com', 2)
        );
    }

    public function testASubscriberWhoOptedOutIsUnsubscribed(): void
    {
        $this->rows[1]['jane.doe@example.com'] = ['subscriber_status' => '3'];

        $this->assertSame(
            SubscriptionStatus::Unsubscribed,
            $this->check->getStatus('jane.doe@example.com', 1)
        );
    }
}
