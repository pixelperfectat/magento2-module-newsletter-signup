<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Service;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutOptInInterface;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutSignupInterface;
use PixelPerfect\NewsletterSignup\Api\Service\InvitationDecisionInterface;

class CheckoutSignup implements CheckoutSignupInterface
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param InvitationDecisionInterface $invitationDecision
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly InvitationDecisionInterface $invitationDecision
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isInvited(int $cartId): bool
    {
        return $this->invites($this->getQuote($cartId));
    }

    /**
     * @inheritDoc
     */
    public function setOptIn(int $cartId, bool $optIn): bool
    {
        $quote = $this->getQuote($cartId);
        $stored = $optIn && $this->invites($quote);
        $quote->setData(CheckoutOptInInterface::QUOTE_FIELD, $stored ? 1 : 0);
        $this->cartRepository->save($quote);

        return $stored;
    }

    /**
     * Whether the checkout line invites the quote's email address
     *
     * A customer's quote carries the account email; a guest's email reaches the quote with the billing or the
     * shipping address.
     *
     * @param Quote $quote
     * @return bool
     */
    private function invites(Quote $quote): bool
    {
        $email = (string)$quote->getData('customer_email');
        if ($email === '') {
            $email = (string)$quote->getBillingAddress()->getEmail();
        }
        if ($email === '') {
            $email = (string)$quote->getShippingAddress()->getEmail();
        }

        return $this->invitationDecision->shouldInvite(
            InvitationDecisionInterface::PLACE_CHECKOUT,
            $email,
            (int)$quote->getStoreId(),
            !$quote->getData('customer_id')
        );
    }

    /**
     * Active quote of the cart
     *
     * @param int $cartId
     * @return Quote
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function getQuote(int $cartId): Quote
    {
        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);

        return $quote;
    }
}
