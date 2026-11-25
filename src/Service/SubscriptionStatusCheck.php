<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Service;

use Magento\Newsletter\Model\ResourceModel\Subscriber as SubscriberResource;
use Magento\Newsletter\Model\Subscriber;
use PixelPerfect\NewsletterSignup\Api\Data\SubscriptionStatus;
use PixelPerfect\NewsletterSignup\Api\Service\SubscriptionStatusCheckInterface;

class SubscriptionStatusCheck implements SubscriptionStatusCheckInterface
{
    /**
     * @param SubscriberResource $subscriberResource
     */
    public function __construct(
        private readonly SubscriberResource $subscriberResource
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getStatus(string $email, int $websiteId): SubscriptionStatus
    {
        $row = $this->subscriberResource->loadBySubscriberEmail(mb_strtolower(trim($email)), $websiteId);

        return match ((int)($row['subscriber_status'] ?? 0)) {
            Subscriber::STATUS_SUBSCRIBED => SubscriptionStatus::Confirmed,
            Subscriber::STATUS_NOT_ACTIVE, Subscriber::STATUS_UNCONFIRMED => SubscriptionStatus::Pending,
            Subscriber::STATUS_UNSUBSCRIBED => SubscriptionStatus::Unsubscribed,
            default => SubscriptionStatus::NotSubscribed,
        };
    }
}
