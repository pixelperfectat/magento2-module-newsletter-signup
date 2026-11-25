# Newsletter Signup for Magento 2

Invites shoppers to the newsletter only when they are not subscribed yet, and says why it is worth it.

- A subscription status check for an email address and website: not subscribed, pending (confirmation email
  sent, link not clicked yet), confirmed, or unsubscribed. Other modules can call it.
- A sign-up line with a short benefit in the Luma checkout.
- A sign-up block on the order confirmation page, for the Luma and the Hyvä theme.

Magento's own double opt-in stays in charge: the module never subscribes anyone without the confirmation
click.

## Subscription status check

Ask for the status of an email address on a website through
`PixelPerfect\NewsletterSignup\Api\Service\SubscriptionStatusCheckInterface::getStatus($email, $websiteId)`. It
returns a `SubscriptionStatus` value:

| Value | Meaning |
|---|---|
| `NotSubscribed` | No subscriber record on this website |
| `Pending` | Confirmation email sent, link not clicked yet (Magento "Not Activated" or "Unconfirmed") |
| `Confirmed` | Double opt-in completed (Magento "Subscribed") |
| `Unsubscribed` | Was subscribed, then opted out |

The email address is matched regardless of case and surrounding spaces. Replace the implementation with a
`<preference>` in `di.xml` if your shop keeps subscriptions elsewhere.

## Status

In development, not released yet. The first release is `0.1.0`.

## License

MIT, see [LICENSE](LICENSE).
