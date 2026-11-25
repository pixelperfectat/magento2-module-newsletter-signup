<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Service;

/**
 * The checkout sign-up line of a cart: whether it invites the cart's own email address, and the shopper's choice
 *
 * Only ever answers for the email address already on the cart, never for an address passed in.
 *
 * @api
 */
interface CheckoutSignupInterface
{
    /**
     * Whether the checkout line invites the email address of the cart
     *
     * @param int $cartId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isInvited(int $cartId): bool;

    /**
     * Store the shopper's choice on the cart; a ticked box is refused when the cart's email is not invited
     *
     * @param int $cartId
     * @param bool $optIn
     * @return bool The stored choice
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setOptIn(int $cartId, bool $optIn): bool;
}
