/**
 * Newsletter sign-up line above the place order button of the Luma checkout.
 *
 * Shown only when the cart's own email address is invited. A customer's cart is decided at page load; a guest's
 * email reaches the cart with the payment information, so the line asks again after each time it is saved.
 * The box is never ticked by default; each change is stored on the cart.
 */
define([
    'ko',
    'uiComponent',
    'mage/storage',
    'Magento_Checkout/js/model/quote',
    'Magento_Customer/js/model/customer',
    'Magento_Checkout/js/model/url-builder',
    'PixelPerfect_NewsletterSignup/js/model/payment-information-saved'
], function (ko, Component, storage, quote, customer, urlBuilder, paymentInformationSaved) {
    'use strict';

    var config = (window.checkoutConfig || {}).pixelperfectNewsletterSignup || {enabled: false};

    return Component.extend({
        defaults: {
            template: 'PixelPerfect_NewsletterSignup/checkout-signup'
        },

        /** @inheritdoc */
        initialize: function () {
            this._super();
            this.enabled = Boolean(config.enabled);
            this.benefitText = config.benefitText || '';
            this.invited = ko.observable(Boolean(config.invited));
            this.optIn = ko.observable(Boolean(config.optIn));
            this.isVisible = ko.pureComputed(function () {
                return this.enabled && this.invited();
            }, this);
            this.optIn.subscribe(this.save, this);

            if (this.enabled && !customer.isLoggedIn()) {
                paymentInformationSaved.subscribe(this.refresh, this);
            }

            return this;
        },

        /**
         * REST address of the line for this cart
         *
         * @return {String}
         */
        getUrl: function () {
            return customer.isLoggedIn() ?
                urlBuilder.createUrl('/carts/mine/newsletter-signup', {}) :
                urlBuilder.createUrl('/guest-carts/:cartId/newsletter-signup', {cartId: quote.getQuoteId()});
        },

        /**
         * Ask whether the email address now on the cart is invited
         */
        refresh: function () {
            var self = this;

            storage.get(this.getUrl(), false).done(function (invited) {
                self.invited(Boolean(invited));

                if (!invited && self.optIn()) {
                    self.optIn(false);
                }
            });
        },

        /**
         * Store the shopper's choice on the cart
         *
         * @param {Boolean} value
         */
        save: function (value) {
            var self = this;

            storage.put(this.getUrl(), JSON.stringify({optIn: Boolean(value)}), false).done(function (stored) {
                if (Boolean(stored) !== Boolean(value)) {
                    self.optIn(Boolean(stored));
                }
            });
        }
    });
});
