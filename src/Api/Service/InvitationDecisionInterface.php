<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Service;

/**
 * Decides whether a place in the shop invites an email address to the newsletter
 *
 * @api
 */
interface InvitationDecisionInterface
{
    /**
     * Place code of the sign-up line in the checkout
     */
    public const PLACE_CHECKOUT = 'checkout';

    /**
     * Place code of the sign-up block on the order confirmation page
     */
    public const PLACE_CONFIRMATION_PAGE = 'confirmation_page';

    /**
     * Whether the place invites the email address in the store view
     *
     * Invites when the address never subscribed, and when it unsubscribed unless the place hides the invitation
     * for unsubscribed people. Never invites pending or confirmed subscribers, at a place that is switched off
     * or unknown, or a guest when Magento does not allow guest subscriptions.
     *
     * @param string $place
     * @param string $email
     * @param int $storeId
     * @param bool $isGuest
     * @return bool
     */
    public function shouldInvite(string $place, string $email, int $storeId, bool $isGuest): bool;
}
