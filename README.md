# Newsletter Signup for Magento 2

Invites shoppers to the newsletter only when they are not subscribed yet, and says why it is worth it.

- A subscription status check for an email address and website: not subscribed, pending (confirmation email
  sent, link not clicked yet), confirmed, or unsubscribed. Other modules can call it.
- A sign-up line with a short benefit in the Luma checkout.
- A sign-up block on the order confirmation page, for the Luma and the Hyvä theme.
- An invitation decision per place (checkout, order confirmation page, or a place of your own): invite only
  people who are not subscribed, and by default those who unsubscribed.
- The place each subscriber signed up from, shown as a "Source" column with a filter in the admin newsletter
  subscriber grid.

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

## Who gets invited

`PixelPerfect\NewsletterSignup\Api\Service\InvitationDecisionInterface::shouldInvite($place, $email, $storeId,
$isGuest)` answers whether a place invites an email address in a store view:

| Status | Invited |
|---|---|
| Not subscribed | Yes |
| Pending | No |
| Confirmed | No |
| Unsubscribed | Yes, unless the place's "Hide for Unsubscribed" setting is on |

Nobody is invited when the place is switched off, when Magento's newsletter is disabled, or, for a guest, when
Magento does not allow guest subscriptions. When the status cannot be read, nobody is invited and the error is
logged.

The places are `checkout` and `confirmation_page`. Add your own as one more item of the `places` argument of
`PixelPerfect\NewsletterSignup\Service\InvitationDecision` in `di.xml`: the item name is the place code, the
value the configuration group that holds its `enabled` and `hide_unsubscribed` flags.

## Checkout sign-up line

In the Luma checkout, a shopper whose email address is not subscribed sees an unticked box with the benefit text
above the "Place Order" button. When they tick it, the order's email address is signed up after the order is
placed: Magento's subscription manager sends its confirmation email when the shop requires one, and the
subscriber gets the source "Checkout". A logged-in customer is signed up with their account. Unticked, nothing
happens. A failing sign-up is logged and never affects the order.

Whether the box shows is decided only for the email address already on the shopper's own cart: at page load for
a logged-in customer, and for a guest once the email reached the cart with the payment information. The
storefront never gets to ask about any other address.

Settings per store view, under Stores > Configuration > Customers > Newsletter > Sign-up Line in the Checkout:

| Setting | Default |
|---|---|
| Enabled | Yes |
| Hide for Unsubscribed | No |
| Benefit Text | A placeholder; write your own. It is the label of the box |

The choice is stored on the cart in the `pixelperfect_newsletter_opt_in` column of the `quote` table. Other
checkouts can use the same mechanism: store the choice with
`PixelPerfect\NewsletterSignup\Api\Service\CheckoutSignupInterface::setOptIn($cartId, $optIn)` (or the REST
routes `PUT /V1/carts/mine/newsletter-signup` and `PUT /V1/guest-carts/:cartId/newsletter-signup` with
`{"optIn": true}`), ask `isInvited($cartId)` (also `GET` on the same routes) to show or hide the box, and the
module signs the shopper up after the order.

## Order confirmation page block

On the checkout success page, in the Luma and the Hyvä theme, a shopper whose order email is not subscribed sees a block with the benefit
text and a "Subscribe" button. The button sends the order's email address through Magento's own newsletter
sign-up, so Magento's confirmation email and messages apply as usual, and records the source "Order
confirmation page". The shopper then lands on the home page with Magento's success message.

A shopper who ticked the checkout sign-up line for this order sees a short note instead: "check your inbox" while
the subscription waits for the confirmation click, or a thank-you when the shop does not ask for confirmation.
If that sign-up did not go through, the block invites as usual.

Settings per store view, under Stores > Configuration > Customers > Newsletter > Sign-up Block on the Order
Confirmation Page:

| Setting | Default |
|---|---|
| Enabled | Yes |
| Hide for Unsubscribed | No |
| Benefit Text | A placeholder; write your own |
| Tracking Parameters | Empty. Query parameters added to the page the shopper lands on, such as `utm_source=shop&utm_medium=confirmation_page` |
| Note After a Checkout Sign-up, Confirmation Pending | A placeholder "check your inbox" text |
| Note After a Checkout Sign-up, Subscribed | A placeholder thank-you text |

### Hyvä theme

The Hyvä theme gets its own template with Tailwind classes, picked through the `hyva_` layout handle of the
success page. The module adds itself to the Hyvä Tailwind build when `bin/magento hyva:config:generate` runs;
Hyvä is not a dependency, and without Hyvä nothing changes. After installing the module in a Hyvä shop,
regenerate the config and rebuild the theme styles:

```bash
bin/magento hyva:config:generate
cd app/design/frontend/<Vendor>/<theme>/web/tailwind && npm run build
```

## Sign-up source

Every new subscription records where it came from, whichever form created it. The admin newsletter subscriber
grid (Marketing > Newsletter Subscribers) shows it in the "Source" column, with a filter, so you can count
sign-ups per place.

The source is decided in this order:

1. The place that signed the shopper up, such as the checkout line or the confirmation page block.
2. The `nl_source` parameter of a shop link the shopper opened earlier in the same visit, for example
   `https://shop.example.com/?nl_source=website_footer`. A form can send it as a hidden field too.
3. Otherwise "Other".

A value that is not a known source code is ignored. The first source recorded for a subscriber is kept when
they sign up again.

Known sources:

| Code | Label |
|---|---|
| `checkout` | Checkout |
| `confirmation_page` | Order confirmation page |
| `order_email` | Order email |
| `shipment_email` | Shipment email |
| `website_footer` | Website footer |
| `signup_page` | Sign-up page |
| `other` | Other |

Add your own under Stores > Configuration > Customers > Newsletter > Sign-up Sources (a code of lower-case
letters, digits and underscores, plus a label), or as one more item of the `defaultSources` argument of
`PixelPerfect\NewsletterSignup\Service\SignupSourceList` in `di.xml`.

The parameter also works on pages served from the full page cache or Varnish: a small script on every
storefront page passes it to the shop, which remembers it in the session. Entry points of other modules
credit their own place with `SignupSourceContextInterface::useSource($code)` before they subscribe the
shopper.

Sources live in their own table. Removing the module leaves Magento's subscribers untouched.

## Automated testing (Playwright)

The Playwright suite in `Test/Playwright` tests the storefront of a running Magento store with the module
enabled. It is not part of CI. Every test places a real guest order and creates subscribers with fresh
addresses on a reserved domain, so run it against a development or staging store, never production.

```bash
cd Test/Playwright
npm install
npx playwright install chromium
cp .env.example .env
# Edit .env: PLAYWRIGHT_BASE_URL, the store view codes, the product and the shipping and payment methods
npm test               # every enabled project
npm run test:luma      # Luma store view, Magento's own checkout
npm run test:hyva      # Hyvä store view (set HYVA_ENABLED=1 and HYVA_CHECKOUT)
```

It covers the confirmation page block (shown to a new guest, the button signs them up, hidden for a
subscriber), the Luma checkout line (unticked by default, the note after a ticked order, hidden for a
subscriber) and the `nl_source` script. The line in Hyvä Checkout is tested by the Hyvä Checkout companion
module's own suite.

## Status

In development, not released yet. The first release is `0.1.0`.

## License

MIT, see [LICENSE](LICENSE).
