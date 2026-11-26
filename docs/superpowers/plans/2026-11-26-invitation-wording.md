# Invitation Wording Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the checkout sign-up line a visible reason (bold lead sentence and an icon) and let the order confirmation page block take a CMS block for a longer pitch, with new default wording and README guidance.

**Architecture:** Core gets a small splitter service that turns the plain benefit text into a lead sentence and the rest (a value object). The Luma checkout line (core) and the Hyvä Checkout line (companion) render lead in bold, rest in normal weight, behind an optional decorative icon. The confirmation page view model renders an optional CMS block and falls back to the plain benefit text.

**Tech Stack:** Magento 2.4 / Mage-OS, PHP 8.3, Knockout (Luma checkout), Hyvä + Magewire (companion), PHPUnit 9.6, Infection, Playwright (lab).

**Spec:** `docs/specs/invitation-wording.md` (core repo).

## Global Constraints

- Repos: core `magento2-module-newsletter-signup`, companion `magento2-module-newsletter-signup-hyva-checkout`. Sales-email is out of scope.
- One feature branch per repo, `feature/invitation-wording`; PRs to `main`; `composer check` green (MSI gate 85 / covered 90).
- PHP 8.3, `declare(strict_types=1)`, interfaces in `Api/`, services in `Service/`, value objects in `Model/Data/` (`.claude/rules/namespace-conventions.md`).
- Benefit text stays plain text: lead and rest are escaped separately; never output the setting unescaped.
- The checkbox stays unticked by default.
- Icon is decorative: `aria-hidden="true"`, no title, no text alternative.
- No Hyvä template code is copied; the companion only calls Hyvä's `HeroiconsOutline` view model. The Luma inline SVG is the Heroicons outline envelope path (MIT), with a one-line credit comment.
- New checkout default: `Be the first to hear about new products and offers. Sign me up for the newsletter, a few times a month, unsubscribe any time.`
- New confirmation page default: `Be the first to hear about new products and offers. Subscribers get early access to new arrivals, special offers and tips, a few times a month. You can unsubscribe at any time with one click.`
- Never name the shop, its domain or ticket prefix; no AI attribution; never the word "chore".
- The lab is in developer mode: never `setup:di:compile`. After Hyvä template class changes, rebuild the lab Tailwind CSS (memory: lab-hyva-css-rebuild).

## Review Focus

1. Benefit text with markup (`<b>Hi</b>. rest`) — both parts are shown escaped, never rendered as HTML. (Task 1 test + Task 2/4 templates escape.)
2. An abbreviation in the first sentence (`Get 10% off, e.g. on shoes.`) — the lead ends at `e.g.` only if followed by whitespace; documented in the README as "keep the first sentence free of abbreviations". (Task 1 test pins the behaviour.)
3. A CMS block that is missing, inactive, or not assigned to the store view — the plain benefit text shows, nothing is logged as an error for a missing block. (Task 3 tests.)
4. A CMS block whose rendering throws — the error is logged and the plain text shows; the page never breaks. (Task 3 test.)
5. Whitespace-only or empty benefit text — no empty `<strong>` and no stray icon-only line: the line renders nothing bold, and the checkbox label still shows the icon. (Task 1 test: empty lead and rest.)

---

### Task 1: Core — benefit text splitter

**Files:**
- Create: `src/Api/Data/BenefitTextInterface.php`, `src/Model/Data/BenefitText.php`
- Create: `src/Api/Service/BenefitTextSplitterInterface.php`, `src/Service/BenefitTextSplitter.php`
- Modify: `src/etc/di.xml` (two preferences)
- Test: `Test/Unit/Service/BenefitTextSplitterTest.php`

**Interfaces:**
- Produces: `BenefitTextInterface::getLead(): string`, `getRest(): string`; `BenefitTextSplitterInterface::split(string $text): BenefitTextInterface`. `BenefitText` is a readonly value object built with `new BenefitText(string $lead, string $rest)`; the splitter may use `new` for it (no factory needed for a plain readonly value object without DI).

- [ ] **Step 1: Write the failing tests** (data provider, input → [lead, rest]):

```php
['Be first. Sign me up.', ['Be first.', 'Sign me up.']],
['Be first! Sign me up.', ['Be first!', 'Sign me up.']],
['Be first? Sign me up.', ['Be first?', 'Sign me up.']],
['One sentence only.', ['One sentence only.', '']],
['No full stop here', ['', 'No full stop here']],
['Version 2.0 is out. Join.', ['Version 2.0 is out.', 'Join.']],   // a stop not followed by whitespace does not end the lead
['Get it, e.g. today. Join.', ['Get it, e.g.', 'today. Join.']],    // pinned, documented limitation
["  Be first.\n Sign up.  ", ['Be first.', 'Sign up.']],            // trimmed, any whitespace
['', ['', '']],
['   ', ['', '']],
['<b>Hi</b>. Rest', ['<b>Hi</b>.', 'Rest']],                        // split only; escaping is the template's job
```

- [ ] **Step 2: Run** `vendor/bin/phpunit --filter BenefitTextSplitterTest` — expect FAIL (class not found).
- [ ] **Step 3: Implement.** Trim the text; the lead ends at the first `.`, `!` or `?` that is followed by whitespace or the end of the text; lead and rest are trimmed. One `preg_match` with `/^(.*?[.!?])(?:\s+(.*))?$/su` is enough.
- [ ] **Step 4: Run** the test — PASS; then `composer check` — green.
- [ ] **Step 5: Commit** `Add a splitter for the lead sentence of the benefit text`.

### Task 2: Core — Luma checkout line: bold lead, icon, new default

**Files:**
- Modify: `src/Service/CheckoutSignupConfigProvider.php` (constructor gains `BenefitTextSplitterInterface $splitter`; new const `XML_PATH_SHOW_ICON = 'newsletter/signup_checkout/show_icon'`)
- Modify: `src/view/frontend/web/js/view/checkout-signup.js`, `src/view/frontend/web/template/checkout-signup.html`, `src/view/frontend/web/css/source/_module.less`
- Modify: `src/etc/config.xml` (`signup_checkout/benefit_text` new default, `signup_checkout/show_icon` = `1`), `src/etc/adminhtml/system.xml` (field `show_icon`, Yes/No, sortOrder 35, label "Show Icon", comment "A small envelope before the text.", depends on enabled; `benefit_text` comment becomes "The label of the box. Plain text. The first sentence shows in bold.")
- Test: `Test/Unit/Service/CheckoutSignupConfigProviderTest.php`

**Interfaces:**
- Consumes: Task 1 `BenefitTextSplitterInterface::split()`.
- Produces: checkout config keys under `pixelperfectNewsletterSignup`: `benefitText` (unchanged, full text), `benefitLead`, `benefitRest`, `showIcon` (bool). The disabled shape stays `['enabled' => false]`.

- [ ] **Step 1: Write failing tests:** with benefit text `Be first. Sign up.` and show_icon `1`, `getConfig()` holds `benefitLead` = `Be first.`, `benefitRest` = `Sign up.`, `showIcon` = `true`, and `benefitText` = the full text; with show_icon `0`, `showIcon` = `false`; disabled line still returns exactly `['enabled' => false]`.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement** the provider; JS reads `benefitLead`, `benefitRest`, `showIcon` (falls back to `benefitText` as rest when `benefitLead` is absent). Template: inside the label after the checkbox, `<span class="newsletter-signup-checkout-icon" aria-hidden="true">` with the inline Heroicons outline envelope SVG (`width="20" height="20"`, `stroke="currentColor"`, `fill="none"`), shown with `<!-- ko if: showIcon -->`; then `<span><strong data-bind="text: benefitLead, visible: benefitLead"></strong> <span data-bind="text: benefitRest"></span></span>`. Less: icon `flex: none; color: @link__color; margin-top: 1px`.
- [ ] **Step 4: Run** the tests — PASS; `composer check` — green.
- [ ] **Step 5: Commit** `Add the bold lead sentence and the icon to the checkout sign-up line`.

### Task 3: Core — confirmation page: optional CMS block, icon, new default

**Files:**
- Create: `src/Model/Config/Source/CmsBlock.php` (same behaviour as sales-email's: `-- None --` then active blocks, one per identifier, valued by identifier, label `Title (identifier)`)
- Modify: `src/ViewModel/ConfirmationPageInvitation.php` (constructor gains `Magento\Cms\Api\GetBlockByIdentifierInterface $getBlock`, `Magento\Cms\Model\Template\FilterProvider $filterProvider`; consts `XML_PATH_CMS_BLOCK = 'newsletter/signup_confirmation_page/cms_block'`, `XML_PATH_SHOW_ICON = 'newsletter/signup_confirmation_page/show_icon'`)
- Modify: `src/view/frontend/templates/confirmation-page-invitation.phtml`, `src/view/frontend/templates/hyva/confirmation-page-invitation.phtml`, `src/view/frontend/web/css/source/_module.less`
- Modify: `src/etc/config.xml` (new `benefit_text` default, `cms_block` empty, `show_icon` = `1`), `src/etc/adminhtml/system.xml` (`cms_block` select with the new source, sortOrder 32, label "CMS Block", comment "Optional. Replaces the benefit text with a longer pitch: heading, points, an image. The Subscribe button stays below it."; `show_icon` sortOrder 35 as in Task 2)
- Modify: `composer.json` (require `magento/module-cms: ^104.0`), `src/etc/module.xml` (sequence `Magento_Cms`)
- Test: `Test/Unit/ViewModel/ConfirmationPageInvitationTest.php`, `Test/Unit/Model/Config/Source/CmsBlockTest.php`

**Interfaces:**
- Produces: `ConfirmationPageInvitation::getBenefitHtml(): string` (rendered block HTML, or `''`), `isIconShown(): bool`. `getBenefitText()` is unchanged.

- [ ] **Step 1: Write failing tests:**
  - no block configured → `getBenefitHtml()` is `''` and `GetBlockByIdentifier` is never called;
  - block `newsletter_pitch` configured, store id 1 → returns the block filter's output of the block content, with the filter's store id set to 1;
  - `GetBlockByIdentifier` throws `NoSuchEntityException` → `''`, logger `error` not called;
  - block found but `isActive()` false → `''`;
  - the filter throws `\RuntimeException('boom')` → `''`, logger `error` called once with a message containing `boom`;
  - rendered content that is only whitespace → `''`;
  - `isIconShown()` follows `show_icon` for the store view;
  - `CmsBlockTest`: first option `['value' => '', ...]`, duplicate identifiers listed once.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement.** Store id from `StoreManagerInterface::getStore()->getId()`. Templates: when `getBenefitHtml()` is not `''`, output it `/* @noEscape */` (shop-owned CMS content) in place of the benefit paragraph; otherwise the escaped plain text as today. Before the heading, when `isIconShown()`: Luma the same inline SVG as Task 2 (class `newsletter-signup-confirmation-icon`); Hyvä `$viewModels->require(\Hyva\Theme\ViewModel\HeroiconsOutline::class)->envelopeHtml('text-primary shrink-0', 24, 24, ['aria-hidden' => 'true'])` in a `flex items-center gap-2` heading row. Hyvä template keeps its Tailwind classes in the module (already in the Tailwind config via the existing observer).
- [ ] **Step 4: Run** — PASS; `composer check` — green.
- [ ] **Step 5: Commit** `Add an optional CMS block and the icon to the confirmation page sign-up block`.

### Task 4: Core — README and changelog

**Files:**
- Modify: `README.md`, `CHANGELOG.md`

- [ ] **Step 1:** README: in "Checkout sign-up line" and "Order confirmation page block", add the new settings rows (Show Icon; CMS Block) and the bold-lead rule. Add a section "Wording the invitation" after "Order confirmation page block":
  - where each text lives (checkout: Benefit Text; confirmation page: Benefit Text or CMS Block; emails: the sales-email module's invitation blocks), all per store view;
  - length per place: one or two short sentences in the checkout, a short paragraph or a block on the confirmation page and in the emails;
  - put the reason in the first sentence, it shows in bold; keep that sentence free of abbreviations ("e.g.", "z. B.") because the bold part ends at the first full stop;
  - tell shoppers what they get, how often, and that they can unsubscribe any time; link the privacy policy where the shop needs one (CMS block);
  - two short and two long examples, English and German (formal "Sie"), for example `Exklusive Angebote zuerst erhalten. Ja, ich möchte den Newsletter, wenige Male im Monat, jederzeit abbestellbar.`
- [ ] **Step 2:** CHANGELOG `[Unreleased]` → `### Added`: bold lead sentence and icon on the checkout line; optional CMS block and icon on the confirmation page block; `### Changed`: new default wording for both places.
- [ ] **Step 3:** Leak/PII scan of the diff (`.claude/rules/no-real-pii.md` grep) — clean.
- [ ] **Step 4: Commit** `Add the wording guide for the invitation to the README`.

### Task 5: Companion — bold lead and icon in Hyvä Checkout

**Files:**
- Modify: `src/Api/Service/CheckoutSignupLineInterface.php` (add `getBenefit(): BenefitTextInterface`, `isIconShown(): bool`; keep `getBenefitText()`)
- Modify: `src/Service/CheckoutSignupLine.php` (constructor gains core's `BenefitTextSplitterInterface`; reads core's `CheckoutSignupConfigProvider::XML_PATH_SHOW_ICON`)
- Modify: `src/Magewire/Checkout/SignupLine.php` (`getBenefit(): BenefitTextInterface`, `isIconShown(): bool`, delegating)
- Modify: `src/view/frontend/templates/checkout/signup-line.phtml`
- Modify: `README.md` (one line pointing to core's "Wording the invitation"), `CHANGELOG.md` `[Unreleased]`
- Modify: `composer.local.json` (dev only, not committed): path repository to the core checkout on `feature/invitation-wording`
- Test: `Test/Unit/Service/CheckoutSignupLineTest.php`, `Test/Unit/Magewire/Checkout/SignupLineTest.php`

**Interfaces:**
- Consumes: core Task 1 `BenefitTextSplitterInterface`, `BenefitTextInterface`; core Task 2 `CheckoutSignupConfigProvider::XML_PATH_SHOW_ICON`.

- [ ] **Step 1: Write failing tests:** service: benefit text `Be first. Sign up.` → `getBenefit()` lead `Be first.`, rest `Sign up.`; `isIconShown()` follows the store view flag. Magewire: `getBenefit()` and `isIconShown()` return what the service gives.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement.** Template inside the label after the checkbox: when `isIconShown()`, `$viewModels->require(\Hyva\Theme\ViewModel\HeroiconsOutline::class)->envelopeHtml('text-primary shrink-0', 20, 20, ['aria-hidden' => 'true'])`; then `<span>` with `<strong>` escaped lead (only when not empty), a space, and the escaped rest.
- [ ] **Step 4: Run** — PASS; `composer check` (with `COMPOSER=composer.local.json`) — green.
- [ ] **Step 5: Commit** `Add the bold lead sentence and the icon to the Hyvä Checkout sign-up line`.

Release note for the executor: the companion's `composer.json` moves to `pixelperfectat/magento2-module-newsletter-signup: ^0.2` in its own release PR, after core `0.2.0` is on Packagist. Until then its CI resolves core `0.1.0` from Packagist and fails on the missing interface: open the companion PR only after core `0.2.0` is released, or have its release PR carry the constraint change.

### Task 6: Lab check, Luma and Hyvä

**Files:**
- Modify: `Test/Playwright/tests/checkout-line.spec.js`, `confirmation-page.spec.js` (core), `Test/Playwright/tests/signup-line.spec.js` (companion)
- Lab copy of both modules (owner uid 501; `docker exec -u root` for writes)

- [ ] **Step 1:** Extend the Playwright specs: the line shows a `strong` with the first sentence and an `svg[aria-hidden="true"]`; with show_icon off there is no svg; the confirmation page shows a configured CMS block's text and the Subscribe button, and the plain text when the block is removed.
- [ ] **Step 2:** Put both feature branches in the lab, `bin/magento setup:upgrade` (no di:compile), cache flush, rebuild the lab Hyvä Tailwind CSS.
- [ ] **Step 3:** Run the core suite against the Luma store view and the companion suite against the Hyvä store view — all green; screenshot the line in both themes for the PR.
- [ ] **Step 4: Commit** the spec changes in each repo: `Add Playwright checks for the bold lead sentence, the icon and the CMS block`.
