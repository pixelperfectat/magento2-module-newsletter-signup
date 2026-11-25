// @ts-check
const base = require('@playwright/test');
const { expect } = base;
const crypto = require('crypto');

const env = (name, fallback) => process.env[name] || fallback;

/**
 * Test with the store-view options of the current project (see playwright.config.js)
 */
const test = base.test.extend({
  theme: ['luma', { option: true }],
  checkout: ['luma', { option: true }],
  storeCode: ['', { option: true }],
  /** A page already switched to the project's store view */
  shop: async ({ page, context, baseURL, storeCode, theme, checkout }, use) => {
    if (storeCode) {
      const host = new URL(baseURL || '').hostname;
      await context.addCookies([{ name: 'store', value: storeCode, domain: host, path: '/' }]);
    }
    await use(new Shop(page, { theme, checkout }));
  },
});

/**
 * A fresh email address on a reserved domain, unique per call
 *
 * @param {string} label
 * @returns {string}
 */
function freshEmail(label) {
  const suffix = crypto.randomBytes(4).toString('hex');
  return `pw.${label}.${Date.now().toString(36)}${suffix}@${env('EMAIL_DOMAIN', 'example.com')}`;
}

class Shop {
  /**
   * @param {import('@playwright/test').Page} page
   * @param {{theme: string, checkout: string}} options
   */
  constructor(page, options) {
    this.page = page;
    this.theme = options.theme;
    this.checkout = options.checkout;
  }

  /** Waits until the page and its Magewire or knockout requests have settled */
  async settle() {
    await this.page.waitForLoadState('networkidle');
    await this.page.waitForTimeout(800);
    await this.page.waitForLoadState('networkidle');
  }

  /** Dismisses a cookie banner if one is in the way */
  async dismissCookieBanner() {
    const reject = this.page.getByRole('button', { name: /Reject All|Alle ablehnen/i });
    if (await reject.isVisible().catch(() => false)) {
      await reject.click();
    }
  }

  /** Puts the configured product into the cart of this browser session */
  async addProductToCart() {
    await this.page.goto('/customer/account/login/');
    const formKey = await this.page.locator('input[name="form_key"]').first().inputValue();
    const response = await this.page.request.post('/checkout/cart/add/', {
      form: { product: env('PRODUCT_ID', '1'), qty: '1', form_key: formKey },
    });
    expect(response.ok()).toBeTruthy();
  }

  /**
   * Subscribes an email address through the newsletter form in the page footer
   *
   * @param {string} email
   */
  async subscribeInFooter(email) {
    await this.page.goto('/');
    await this.dismissCookieBanner();
    const form = this.page.locator('footer form[action*="newsletter/subscriber/new"]').first();
    await form.locator('input[name="email"]').fill(email);
    // Magento answers the POST with a redirect back to the page, so wait for the POST, not for a new URL
    const posted = this.page.waitForResponse(
      response => response.url().includes('newsletter/subscriber/new') && response.request().method() === 'POST'
    );
    await form.getByRole('button').first().click();
    await posted;
    await this.page.waitForLoadState('domcontentloaded');
  }

  /**
   * Places a guest order with the email address and lands on the success page
   *
   * @param {string} email
   * @param {{tickNewsletter?: boolean}} options
   * @returns {Promise<{lineVisible: boolean, lineTickedByDefault: boolean|null}>}
   */
  async placeGuestOrder(email, options = {}) {
    await this.addProductToCart();
    return this.checkout === 'hyva'
      ? this.placeHyvaCheckoutOrder(email, options)
      : this.placeLumaCheckoutOrder(email, options);
  }

  /** The newsletter line in Magento's own checkout */
  lumaLine() {
    return this.page.locator('.payment-method._active .newsletter-signup-checkout input[type="checkbox"]');
  }

  /**
   * @param {string} email
   * @param {{tickNewsletter?: boolean}} options
   */
  async placeLumaCheckoutOrder(email, options) {
    const page = this.page;
    await page.goto('/checkout/');
    await this.dismissCookieBanner();
    await page.locator('#customer-email').waitFor({ state: 'visible', timeout: 60000 });
    await page.fill('#customer-email', email);
    const form = page.locator('#shipping-new-address-form');
    await form.waitFor({ state: 'visible', timeout: 30000 });
    const field = name => form.locator(`[name="${name}"]`);
    await field('firstname').fill('Jane');
    await field('lastname').fill('Doe');
    await field('street[0]').fill(env('ADDRESS_STREET', 'Example Street 1'));
    await field('city').fill(env('ADDRESS_CITY', 'Exampletown'));
    await field('country_id').selectOption(env('ADDRESS_COUNTRY', 'AT'));
    await field('postcode').fill(env('ADDRESS_POSTCODE', '1010'));
    await field('telephone').fill(env('ADDRESS_TELEPHONE', '+43 000 000000'));
    const rate = page.locator(`input[value="${env('SHIPPING_METHOD', 'flatrate_flatrate')}"]`);
    await rate.waitFor({ state: 'visible', timeout: 30000 });
    await rate.check();
    await page.click('#shipping-method-buttons-container button.continue');
    await page.locator('.payment-method').first().waitFor({ state: 'visible', timeout: 60000 });
    // With a single payment method Magento hides its radio button and selects it already
    const payment = page.locator(`#${env('PAYMENT_METHOD', 'checkmo')}`);
    if (await payment.isVisible()) {
      await payment.check();
    }
    await this.settle();

    const line = this.lumaLine();
    // The answer can take a moment to render; a hidden line simply never shows up
    await line.waitFor({ state: 'visible', timeout: 10000 }).catch(() => null);
    const lineVisible = await line.isVisible();
    const lineTickedByDefault = lineVisible ? await line.isChecked() : null;
    if (lineVisible && options.tickNewsletter) {
      await line.check();
      await this.settle();
    }
    await page.locator('.payment-method._active button.action.checkout').click();
    await page.waitForURL(/checkout\/onepage\/success/, { timeout: 60000 });
    return { lineVisible, lineTickedByDefault };
  }

  /**
   * @param {string} email
   * @param {{tickNewsletter?: boolean}} options
   */
  async placeHyvaCheckoutOrder(email, options) {
    const page = this.page;
    await page.goto('/checkout/');
    await this.settle();
    await this.dismissCookieBanner();
    await page.fill('#guest_details-email_address', email);
    await page.locator('#guest_details-email_address').blur();
    await this.settle();
    const fields = [
      ['firstname', 'Jane'],
      ['lastname', 'Doe'],
      ['street-0', env('ADDRESS_STREET', 'Example Street 1')],
      ['postcode', env('ADDRESS_POSTCODE', '1010')],
      ['city', env('ADDRESS_CITY', 'Exampletown')],
      ['telephone', env('ADDRESS_TELEPHONE', '+43 000 000000')],
    ];
    for (const [id, value] of fields) {
      await page.fill(`#shipping-${id}`, value);
    }
    await page.selectOption('#shipping-country_id', env('ADDRESS_COUNTRY', 'AT'));
    await this.settle();
    await page.locator('#shipping-telephone').blur();
    await this.settle();
    await page.locator(`input[value="${env('SHIPPING_METHOD', 'flatrate_flatrate')}"]`).check();
    await this.settle();
    const proceed = page.getByRole('button', { name: /Proceed to Review|Weiter/i });
    await proceed.waitFor({ state: 'visible', timeout: 30000 });
    await this.settle();
    await proceed.click();
    await this.settle();
    const placeOrder = page.getByRole('button', { name: /Place Order|Bestellung aufgeben/i });
    await placeOrder.waitFor({ state: 'visible', timeout: 30000 });
    const payment = page.locator(`input[value="${env('PAYMENT_METHOD', 'checkmo')}"]`);
    if (await payment.count()) {
      await payment.check();
      await this.settle();
    }

    // The newsletter line in Hyvä Checkout comes from a separate companion module with its own tests.
    if (options.tickNewsletter) {
      throw new Error('Ticking the newsletter line is only covered for Magento\'s own checkout here');
    }
    await this.settle();
    await placeOrder.click();
    await page.waitForURL(/checkout\/onepage\/success/, { timeout: 60000 });
    await page.waitForLoadState('networkidle');
    return { lineVisible: false, lineTickedByDefault: null };
  }

  /** The newsletter block on the order confirmation page, in either theme */
  confirmationBlock() {
    return this.page.locator('.newsletter-signup-confirmation');
  }
}

module.exports = { test, expect, freshEmail };
