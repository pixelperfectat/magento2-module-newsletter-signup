<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Service;

use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutOptInInterface;
use PixelPerfect\NewsletterSignup\Api\Service\InvitationDecisionInterface;
use PixelPerfect\NewsletterSignup\Api\Service\SignupSourceContextInterface;
use Psr\Log\LoggerInterface;

class CheckoutOptIn implements CheckoutOptInInterface
{
    /**
     * @param SubscriptionManagerInterface $subscriptionManager
     * @param SignupSourceContextInterface $sourceContext
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SubscriptionManagerInterface $subscriptionManager,
        private readonly SignupSourceContextInterface $sourceContext,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function subscribeAfterOrder(Quote $quote, OrderInterface $order): void
    {
        if (!$quote->getData(self::QUOTE_FIELD)) {
            return;
        }

        $storeId = (int)$order->getStoreId();
        $customerId = (int)$order->getCustomerId();
        $email = trim((string)$order->getCustomerEmail());
        try {
            if (!$order->getCustomerIsGuest() && $customerId > 0) {
                $this->sourceContext->useSource(InvitationDecisionInterface::PLACE_CHECKOUT);
                $this->subscriptionManager->subscribeCustomer($customerId, $storeId);
            } elseif ($email !== '') {
                $this->sourceContext->useSource(InvitationDecisionInterface::PLACE_CHECKOUT);
                $this->subscriptionManager->subscribe($email, $storeId);
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                'Newsletter sign-up: the checkout sign-up failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
