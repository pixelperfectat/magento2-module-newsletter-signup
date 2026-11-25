/**
 * Tells the newsletter sign-up line when the payment information, and with it a guest's email, reached the cart.
 */
define([
    'mage/utils/wrapper',
    'PixelPerfect_NewsletterSignup/js/model/payment-information-saved'
], function (wrapper, paymentInformationSaved) {
    'use strict';

    return function (setPaymentInformationExtended) {
        return wrapper.wrap(setPaymentInformationExtended, function (original) {
            var args = Array.prototype.slice.call(arguments, 1);

            return original.apply(null, args).done(function () {
                paymentInformationSaved(paymentInformationSaved() + 1);
            });
        });
    };
});
