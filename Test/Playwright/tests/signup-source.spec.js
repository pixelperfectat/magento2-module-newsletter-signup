// @ts-check
const { test, expect } = require('./helpers/shop');

test.describe('Sign-up source parameter', () => {
  test('a link with nl_source hands the source to the shop, also on cached pages', async ({ shop, page }) => {
    const remembered = page.waitForResponse(
      response => response.url().includes('/newslettersignup/source/remember')
    );

    await page.goto('/?nl_source=website_footer');

    const response = await remembered;
    expect(response.status()).toBe(204);
    expect(new URL(response.url()).searchParams.get('nl_source')).toBe('website_footer');
  });

  test('a page without nl_source sends nothing', async ({ shop, page }) => {
    const calls = [];
    page.on('request', request => {
      if (request.url().includes('/newslettersignup/source/remember')) {
        calls.push(request.url());
      }
    });

    await page.goto('/');
    await page.waitForLoadState('networkidle');

    expect(calls).toEqual([]);
  });
});
