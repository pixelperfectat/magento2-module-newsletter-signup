<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Service;

/**
 * The checkout sign-up line of a guest cart, addressed by the cart's masked id
 *
 * @api
 */
interface GuestCheckoutSignupInterface
{
    /**
     * Whether the checkout line invites the email address of the guest cart
     *
     * @param string $cartId Masked cart id
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isInvited(string $cartId): bool;

    /**
     * Store the shopper's choice on the guest cart; a ticked box is refused when the cart's email is not invited
     *
     * @param string $cartId Masked cart id
     * @param bool $optIn
     * @return bool The stored choice
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function setOptIn(string $cartId, bool $optIn): bool;
}
