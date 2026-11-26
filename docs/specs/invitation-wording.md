# Invitation wording: a visible reason to sign up

Status: approved 2026-11-26. Builds on [newsletter-signup.md](newsletter-signup.md), which left "the final
benefit wording" open.

## Problem

The checkout line reads like fine print: an unticked box with one plain sentence and no reason to tick it. The
confirmation page block and the email invitation carry placeholder text too. Shops get no guidance on how to
word the invitation for each place.

## Intent

Every place shows a reason to sign up, short where the shopper is busy (checkout), fuller where they have time
(confirmation page, emails). The module ships good standard reasons; each shop swaps in its own in the admin,
without touching code. The README shows how.

- **Goals:** a reason visible at a glance in the checkout; a fuller pitch on the confirmation page; text per place
  and per store view, simple to edit; documented suggestions.
- **Non-goals:** a modal or "more info" view in the checkout; A/B testing; incentives such as vouchers built into
  the module (a shop writes them into its own text).
- **Anti-goals:** a checkout that feels heavier or slower; a pre-ticked box; anything that hides what the shopper
  signs up for.

## Approaches considered

- **Rich text (CMS block) everywhere:** most freedom, but the checkout line becomes HTML editing for a single
  line. Rejected for the checkout.
- **A details modal in the checkout:** room for the full pitch, but one more thing in the way of placing the
  order. Rejected (owner: "no modal").
- **Chosen:** checkout keeps one plain text field with light automatic emphasis and an icon; the confirmation
  page may use a CMS block for the longer pitch; the emails keep their CMS blocks.

## Design

### Checkout line (Luma checkout in core, Hyvä Checkout in the companion)

- An icon, then the benefit text. The text up to and including the first full stop shows in bold; the rest in
  normal weight. Text without a full stop shows in normal weight.
- The text stays one plain text field ("Benefit Text", per store view). Still escaped; no HTML.
- New default, leading with the reason: "Be the first to hear about new products and offers. Sign me up for the
  newsletter, a few times a month, unsubscribe any time." Configuration defaults are not translated per locale,
  so the default is English only; the README gives German (formal "Sie") examples for shops to paste.
- The box stays unticked.

### Icon

- The Heroicons outline envelope, about text height, in the theme's accent colour
  (`currentColor` on a coloured wrapper). Decorative: `aria-hidden="true"`, no title.
- Hyvä: from the theme's Heroicons set through its icon view model; no Hyvä template code is copied. Luma: the
  same outline as inline SVG in the module's own template.
- The same icon on the checkout line and the confirmation page block.
- New setting per place, per store view: "Show Icon", default Yes.

### Confirmation page block

- New optional setting "CMS Block" (per store view) beside "Benefit Text". When set and the block renders
  content, the block replaces the benefit text; otherwise the plain text shows. The "Subscribe" button stays
  the module's own, below the content.
- The block content is shop-owned HTML (heading, points, an image).
- Default stays plain text, with a fuller default reason (English, as above).

### Email invitation (sales-email)

No change. Its default invitation block already leads with the reason ("Be the first to hear about new products
and offers."), lists three points and says how to unsubscribe. The new checkout and confirmation page defaults
use the same reasons.

### README (core, and a pointer from the companion and sales-email)

A "Wording the invitation" section:

- where each text lives (setting or block, per store view);
- suggested reasons and how long per place: one sentence in the checkout, a short paragraph or a block on the
  confirmation page and in the emails;
- what a shopper should be told: what they get, how often, that they can unsubscribe any time, a link to the
  privacy policy where the shop needs one;
- short and long examples in English and German.

## Left to the builder

- The German README examples (formal "Sie").
- How the bold part is split (first sentence end) and edge cases such as abbreviations; keep it predictable and
  documented.
- Tests: template output for the bold split, the icon switch, the CMS block fallback; lab check in Luma and Hyvä.

## Release

Core and the companion each get a release; versions follow the commits. Sales-email needs none.
