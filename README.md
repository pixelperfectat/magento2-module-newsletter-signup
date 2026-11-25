# Newsletter Signup for Magento 2

Invites shoppers to the newsletter only when they are not subscribed yet, and says why it is worth it.

- A subscription status check for an email address and website: not subscribed, pending (confirmation email
  sent, link not clicked yet), confirmed, or unsubscribed. Other modules can call it.
- A sign-up line with a short benefit in the Luma checkout.
- A sign-up block on the order confirmation page, for the Luma and the Hyvä theme.

Magento's own double opt-in stays in charge: the module never subscribes anyone without the confirmation
click.

For Hyvä Checkout, install `pixelperfectat/magento2-module-newsletter-signup-hyva-checkout` as well.

## Status

In development, not released yet. The first release is `0.1.0`.

## License

MIT, see [LICENSE](LICENSE).
