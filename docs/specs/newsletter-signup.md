# Spec: Newsletter sign-up by subscription status

Tracked by #2 (the `Feature:` parent issue; tasks are its sub-issues).

## Problem Statement

A shop owner wants more newsletter subscribers, but the shop asks every buyer the same way, every time. The
checkout shows a bare "subscribe to the newsletter" option with no reason to say yes. The order and shipment
emails carry a newsletter invitation for everyone, including people who signed up long ago or who are halfway
through the double opt-in. That nags existing subscribers, wastes the best moment to win new ones, and gives
people who have not subscribed no reason to do it.

The shop already runs Magento's own double opt-in: Magento sends the confirmation email, and a subscriber
only counts once they click the link. So Magento already knows, per email address and website, whether
someone has never subscribed, is waiting to confirm, is confirmed, or has unsubscribed. Nothing in the shop
uses that knowledge yet.

## Solution

A free module, `magento2-module-newsletter-signup`, answers one question for any other module: what is the
newsletter subscription status of this email address on this website? It returns one of four states:
**not subscribed**, **pending** (confirmation email sent, link not clicked yet), **confirmed**, or
**unsubscribed**.

On top of that status check the module invites people only when it makes sense:

- **Checkout:** a light sign-up line with a short benefit, not pre-ticked, in the Luma checkout. When the
  shopper ticks it, the order's email address is signed up after the order is placed, and Magento sends its
  confirmation email. A companion module, `magento2-module-newsletter-signup-hyva-checkout`, adds the same
  line to Hyvä Checkout.
- **Order confirmation page:** a fuller block with the benefit written out and one clear button that signs the
  order's email address up (Magento then sends the confirmation email). It works in the Luma theme and the Hyvä
  theme.
- **Order and shipment emails:** the existing `magento2-module-sales-email` asks the status check, when this
  module is installed, and hides its invitation block for pending and confirmed subscribers.

Both invitations show for **not subscribed** and, by default, for **unsubscribed** (so former subscribers can
be won back). A setting per place hides them for unsubscribed people where a shop prefers that.

Nobody is ever subscribed without their own action and Magento's confirmation click.

## User Stories

1. As a shop owner, I want to know for any email address whether it is not subscribed, pending, confirmed or unsubscribed, so that every newsletter invitation can depend on it.
2. As a shop owner, I want the status read per website, so that a subscription on one website does not hide the invitation on another.
3. As a module developer, I want one public status check with a stable contract, so that my module can ask for the status without knowing Magento's subscriber internals.
4. As a module developer, I want the four states as a fixed set of named values, so that I can handle every case explicitly.
5. As a shop owner, I want an email address that never subscribed to count as not subscribed, so that it gets invited.
6. As a shop owner, I want a subscriber who has not clicked the confirmation link to count as pending, so that they are not asked to subscribe again.
7. As a shop owner, I want a subscriber in Magento's rare "unconfirmed" state to count as pending, so that someone partway through is not invited again.
8. As a shop owner, I want a confirmed subscriber to count as confirmed, so that they are never invited again.
9. As a shop owner, I want an unsubscribed address to count as unsubscribed, separate from not subscribed, so that I can decide per place whether to invite them back.
10. As a shop owner, I want email addresses compared regardless of upper and lower case and surrounding spaces, so that "Jane.Doe@example.com " matches "jane.doe@example.com".
11. As a shopper who is not subscribed, I want a short sign-up line in the checkout that tells me what I get, so that I can decide in a second.
12. As a shopper, I want the checkout sign-up box unticked by default, so that I am never signed up by accident.
13. As a shopper who ticks the box, I want my order's email address signed up after the order is placed, so that I do not have to fill in a separate form.
14. As a shopper who ticks the box, I want to receive Magento's confirmation email, so that I confirm the subscription myself.
15. As a shopper who does not tick the box, I want nothing to happen to my subscription, so that my choice is respected.
16. As a confirmed or pending subscriber, I want the checkout sign-up line hidden, so that I am not asked again.
17. As a logged-in customer, I want the checkout line to know my status from the start, so that it is hidden straight away when I am subscribed.
18. As a guest shopper, I want the checkout line to reflect the email address I entered in this checkout, so that it is correct for me.
19. As a shopper, I want the checkout to work exactly as before when the status cannot be determined, so that a newsletter problem never blocks my order.
20. As a shopper whose order fails, I want no sign-up to happen, so that I am only subscribed after a real order.
21. As a shopper who is not subscribed, I want a block on the order confirmation page that explains the benefit and has one clear button, so that signing up is one click.
22. As a shopper who clicks that button, I want the order's email address signed up and a message that a confirmation email is on its way, so that I know what to do next.
23. As a subscribed or pending shopper, I want no sign-up block on the order confirmation page, so that the page stays about my order.
24. As a shopper who ticked the checkout box, I want the confirmation page to show nothing or a short "check your inbox" note instead of the sign-up block, so that I am not asked twice.
25. As a shop owner on the Luma theme, I want the confirmation page block styled for Luma, so that it fits the page.
26. As a shop owner on the Hyvä theme, I want the confirmation page block built with the Hyvä theme's conventions and Tailwind classes, so that it fits the page and its classes are part of the theme build.
27. As a shop owner, I want the confirmation page block to work for guest orders, so that guests can sign up too.
28. As a shop owner, I want to switch the checkout line and the confirmation page block on or off separately per store view, so that I control where shoppers are invited.
29. As a shop owner, I want a setting per place that hides the invitation for unsubscribed people, so that I can respect an earlier opt-out if I choose to.
30. As a shop owner, I want the benefit text of the checkout line and of the confirmation page block to be editable per store view, so that I can word and translate it myself.
31. As a shop owner, I want sensible placeholder texts out of the box, so that the module works before I have written my own.
32. As a shop owner, I want the confirmation page button to carry tracking parameters I can configure, so that an attribution tool can count clicks and sign-ups.
33. As a shop owner, I want the module to respect Magento's "allow guest subscription" setting, so that guests are not offered something Magento would refuse.
34. As a shop owner, I want the status of an arbitrary email address never exposed to the storefront, so that nobody can probe who is subscribed.
35. As a shop owner using the sales email module, I want the order email invitation hidden for pending and confirmed recipients by default, so that subscribers are not nagged.
36. As a shop owner using the sales email module, I want the shipment email invitation to follow the same rule, so that both emails behave alike.
37. As a shop owner using the sales email module, I want to choose content to show instead for pending and for confirmed recipients, so that I can, for example, remind pending people to click the link.
38. As a shop owner using the sales email module, I want unsubscribed recipients to see the invitation by default and a setting to hide it, so that I decide about win-back mails myself.
39. As a shop owner using the sales email module without this module installed, I want the invitation to show exactly as today, so that nothing changes until I install it.
40. As a shop owner, I want a failing status check to fall back to showing the default invitation in emails and to logging the error, so that an email is never lost or broken because of the newsletter.
41. As a shop owner on Hyvä Checkout, I want the same checkout sign-up line there, by installing the companion module, so that both checkouts behave the same.
42. As a shop owner without Hyvä Checkout, I want the core module to install and compile without it, so that I never need a commercial package I do not use.
43. As a developer, I want every setting overridable in `di.xml` or the admin, and the status check replaceable by preference, so that I can adapt the module without editing it.

## Implementation Decisions

- **Two modules.** `magento2-module-newsletter-signup` (core) holds the status check, the invitation decision,
  the checkout opt-in handling, the Luma checkout line and the order confirmation page block for both the Luma
  and the Hyvä theme. `magento2-module-newsletter-signup-hyva-checkout` holds only the Hyvä Checkout line. It
  is separate because its component extends a Hyvä Checkout class; a class whose parent is missing breaks
  `setup:di:compile` in shops without Hyvä Checkout. The companion requires core; core never names the
  companion.
- **Status check (public contract, seam 1).** A service interface in core's service API takes an email address
  and a website and returns a subscription status value. The status is a fixed set of four named values: not
  subscribed, pending, confirmed, unsubscribed. The email is normalised (trimmed, lower-cased) before lookup.
- **Source of truth is Magento.** The status is read from Magento's own newsletter subscriber data for that
  email and website. Mapping: no subscriber record → not subscribed; "not activated" → pending;
  "unconfirmed" → pending; "subscribed" → confirmed; "unsubscribed" → unsubscribed. The module does not talk to
  any external newsletter service. Double opt-in stays Magento's: the shop's "need to confirm" setting drives
  the confirmation email.
- **Invitation decision (seam 2).** A core service answers "invite here?" for a place (checkout or
  confirmation page), an email and a store. It returns yes for not subscribed, no for pending and confirmed,
  and for unsubscribed it follows that place's "hide for unsubscribed" setting (default: invite). It returns no
  when the place is switched off or guest subscription is not allowed for a guest. The places are an
  extensible list, so another module can add one in `di.xml`.
- **Checkout opt-in (seam 3).** The shopper's choice is stored on the quote. After the order is placed, a core
  service signs up the order's email address on the order's store through Magento's subscription manager, which
  sends the confirmation email when required. No choice, or an unticked box, does nothing. A failure is logged
  and never fails the order. The Luma checkout line writes the choice through core's own mechanism; the Hyvä
  Checkout companion writes the same quote value.
- **No status for arbitrary emails on the storefront.** The storefront never gets an endpoint that takes an
  email and returns its status. The checkout learns the status only for its own quote's email (a logged-in
  customer's account email at page load; a guest's quote email through the guest's own cart), and only as
  "show the line or not". The confirmation page uses the last order in the shopper's session.
- **Order confirmation page block.** Rendered on Magento's checkout success page from the last order. Luma gets
  its own template; the Hyvä theme gets its own template through the Hyvä-prefixed layout handle of the same
  page. Core registers itself with the Hyvä Tailwind build through Hyvä's configuration event, which only fires
  when Hyvä is installed, so core needs no Hyvä package. The button posts to Magento's own newsletter
  subscription with the order's email, so the double opt-in path is the standard one. When the shopper ticked
  the checkout box for this order, the block shows a short "check your inbox" note instead.
- **Settings (per store view):** checkout line on/off; confirmation page block on/off; per place "hide for
  unsubscribed"; per place benefit text (CMS-free text or a CMS block identifier, the builder decides); tracking
  parameters appended to the confirmation page button link. Placeholder texts ship as defaults; the real
  wording is not decided yet.
- **Sales email integration (in `magento2-module-sales-email`).** The invitation view model asks the status
  check when core is installed, using the order's email and the order store's website. Not subscribed: the
  invitation as today. Pending and confirmed: nothing by default, with a configurable CMS block per state
  (sanitised the same way as the invitation). Unsubscribed: the invitation by default, with a setting to hide
  it. Core is an optional dependency (`suggest`, plus a soft lookup that tolerates its absence); without core
  the behaviour is unchanged. Any error from the status check is logged and falls back to the invitation as
  today. Both the order and the shipment email follow the same rule.
- **Extensibility.** Every service has an interface and is bound by preference; settings have defaults that can
  be overridden in `di.xml`; the places of the invitation decision are an injected list.

## Testing Decisions

- **Good tests** assert outcomes a caller can observe through the public services: the status returned for a
  given subscriber record, the yes/no of the invitation decision for a place, status and settings, and whether
  the order's email was handed to Magento's subscription manager. They never assert private calls, call
  order or internal state.
- **Only Magento's edges are faked:** the subscriber lookup, the subscription manager, the configuration reader
  and the session/order source. Everything inside the module runs for real.
- **Seam 1, status check:** one test per state, plus: the unconfirmed mapping, email normalisation, website
  scoping (subscribed on another website → not subscribed here).
- **Seam 2, invitation decision:** each status × place, place switched off, unsubscribed with the hide setting
  on and off, a guest when guest subscription is not allowed.
- **Seam 3, checkout opt-in:** ticked → subscription manager called with the order email and store; unticked
  or missing → not called; subscription manager failure → logged, no exception.
- **Sales email:** the existing invitation view-model tests are the prior art: per status, without core
  installed, and with a failing status check.
- **Templates and the Hyvä Checkout component** are thin and have no logic of their own; they are verified in
  the lab shop with screenshots for Luma checkout, Hyvä Checkout, the Luma and the Hyvä confirmation page, and
  the order and shipment emails.
- **Prior art:** the unit tests of `magento2-module-sales-email` (view models with faked Magento edges) and of
  `magento2-module-store-pickup`. Quality gates as in those modules: PHPStan, the shared coding standard,
  PHPUnit and Infection (MSI ≥ 85, covered MSI ≥ 90).

## Out of Scope

- Subscribing anyone without the shopper's own action, or skipping Magento's confirmation email.
- Syncing subscribers to an external newsletter service (that stays with whichever integration the shop
  already uses).
- A welcome series or any email after the confirmation.
- The final benefit wording (placeholders until it is decided).
- Newsletter invitations in emails other than the order and shipment email.
- A storefront API or GraphQL query for the status.
- Customer account pages and the footer sign-up form.

## Further Notes

- Work is split across three repositories; `Task:` issues in the Hyvä Checkout companion and in
  `magento2-module-sales-email` are sub-issues of this one.
- Release order: core `0.1.0` first, then the Hyvä Checkout companion and the sales email change.
- An invitation in a transactional email to someone who unsubscribed can be a legal question in some
  countries; the "hide for unsubscribed" setting exists so each shop can decide.
