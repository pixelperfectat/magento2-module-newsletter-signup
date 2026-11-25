<?php declare(strict_types=1);

namespace PixelPerfect\NewsletterSignup\Api\Service;

use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Signs the order's email address up for the newsletter after an order whose shopper ticked the checkout box
 *
 * @api
 */
interface CheckoutOptInInterface
{
    /**
     * Quote field holding the shopper's choice: 1 ticked, 0 unticked, null never answered
     */
    public const QUOTE_FIELD = 'pixelperfect_newsletter_opt_in';

    /**
     * Sign the order's email address up when the quote holds a ticked box
     *
     * Magento's subscription manager sends the confirmation email when the shop requires one. Nothing happens
     * for an unticked box or a quote without a choice. A failure is logged and never reaches the order.
     *
     * @param Quote $quote
     * @param OrderInterface $order
     * @return void
     */
    public function subscribeAfterOrder(Quote $quote, OrderInterface $order): void;
}
