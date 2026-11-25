<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use PixelPerfect\NewsletterSignup\Api\Service\CheckoutOptInInterface;

/**
 * Hands a placed order to the checkout opt-in, which signs the order's email address up when the box was ticked
 */
class SubscribeAfterCheckout implements ObserverInterface
{
    /**
     * @param CheckoutOptInInterface $checkoutOptIn
     */
    public function __construct(
        private readonly CheckoutOptInInterface $checkoutOptIn
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        $quote = $observer->getEvent()->getData('quote');
        if ($order instanceof OrderInterface && $quote instanceof Quote) {
            $this->checkoutOptIn->subscribeAfterOrder($quote, $order);
        }
    }
}
