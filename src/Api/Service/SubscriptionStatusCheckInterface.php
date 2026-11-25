<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Service;

use PixelPerfect\NewsletterSignup\Api\Data\SubscriptionStatus;

/**
 * Newsletter subscription status of an email address, read from Magento's own subscriber data
 *
 * @api
 */
interface SubscriptionStatusCheckInterface
{
    /**
     * Subscription status of the email address on the website
     *
     * @param string $email
     * @param int $websiteId
     * @return SubscriptionStatus
     */
    public function getStatus(string $email, int $websiteId): SubscriptionStatus;
}
