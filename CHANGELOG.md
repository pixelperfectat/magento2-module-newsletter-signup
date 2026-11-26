# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-11-26

### Added

- Subscription status check for an email address and website (not subscribed, pending, confirmed or unsubscribed), read from Magento's own subscribers, for other modules to call.
- Invitation decision per place: only people who are not subscribed are invited, and by default those who unsubscribed; each place can be switched off or hidden for unsubscribed people, and shops can add places of their own.
- Sign-up line with a short benefit text in the Luma checkout; when ticked, the order's email address is signed up after the order through Magento's own double opt-in. Other checkouts can use the same mechanism through a service or two cart-scoped REST routes.
- Sign-up block with a benefit text and a "Subscribe" button on the order confirmation page, for the Luma and the Hyvä theme, with optional tracking parameters on the page the shopper lands on.
- A "check your inbox" or thank-you note on the confirmation page after a checkout sign-up, instead of the block.
- The place each subscriber signed up from, recorded for every new subscription (including from `nl_source` link parameters on cached pages) and shown as a "Source" column with a filter in the admin subscriber grid; shops can add their own sources.
- Registration with the Hyvä Tailwind build when Hyvä is installed.
- Playwright storefront tests to run against a development or staging store.

[Unreleased]: https://github.com/pixelperfectat/magento2-module-newsletter-signup/compare/0.1.0...HEAD
[0.1.0]: https://github.com/pixelperfectat/magento2-module-newsletter-signup/releases/tag/0.1.0
