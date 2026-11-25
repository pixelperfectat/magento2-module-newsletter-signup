<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Data;

/**
 * Newsletter subscription status of an email address on one website
 */
enum SubscriptionStatus: string
{
    /** Never subscribed on this website */
    case NotSubscribed = 'not_subscribed';

    /** Signed up, confirmation link not clicked yet */
    case Pending = 'pending';

    /** Double opt-in completed */
    case Confirmed = 'confirmed';

    /** Was subscribed, then opted out */
    case Unsubscribed = 'unsubscribed';
}
