<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Service;

use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutSignupInterface;
use PixelPerfect\NewsletterSignup\Api\Service\GuestCheckoutSignupInterface;

class GuestCheckoutSignup implements GuestCheckoutSignupInterface
{
    /**
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     * @param CheckoutSignupInterface $checkoutSignup
     */
    public function __construct(
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        private readonly CheckoutSignupInterface $checkoutSignup
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isInvited(string $cartId): bool
    {
        return $this->checkoutSignup->isInvited($this->maskedQuoteIdToQuoteId->execute($cartId));
    }

    /**
     * @inheritDoc
     */
    public function setOptIn(string $cartId, bool $optIn): bool
    {
        return $this->checkoutSignup->setOptIn($this->maskedQuoteIdToQuoteId->execute($cartId), $optIn);
    }
}
