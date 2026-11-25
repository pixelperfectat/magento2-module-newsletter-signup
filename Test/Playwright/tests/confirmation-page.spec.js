// @ts-check
const { test, expect, freshEmail } = require('./helpers/shop');

test.describe('Sign-up block on the order confirmation page', () => {
  test('invites a new guest and signs them up with one click', async ({ shop, page }) => {
    const email = freshEmail('block');
    await shop.placeGuestOrder(email);

    const block = shop.confirmationBlock();
    await expect(block).toBeVisible();
    await expect(block).toContainText(email);
    const form = block.locator('form');
    await expect(form.locator('input[name="nl_source"]')).toHaveValue('confirmation_page');

    await Promise.all([
      page.waitForURL(url => !url.pathname.includes('checkout/onepage/success')),
      form.locator('button[type="submit"]').click(),
    ]);
    // Magento's own sign-up answers with a success message: subscribed, or confirmation email sent
    await expect(page.locator('[data-ui-id="message-success"], .message-success, .message.success').first())
      .toBeVisible();
  });

  test('does not invite an address that is already subscribed', async ({ shop }) => {
    const email = freshEmail('subscribed');
    await shop.subscribeInFooter(email);

    await shop.placeGuestOrder(email);

    await expect(shop.confirmationBlock()).toHaveCount(0);
  });
});
