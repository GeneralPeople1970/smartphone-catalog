import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { test, expect } from '@playwright/test';

const evidenceDir = process.env.BROWSER_EVIDENCE_DIR || join(process.env.BROWSER_TEST_RUNTIME, 'evidence');

async function capture(page, name) {
    mkdirSync(evidenceDir, { recursive: true });
    await page.screenshot({ path: join(evidenceDir, `${name}.png`), fullPage: false });
}

async function expectReadableCard(page) {
    const card = page.locator('a.phone-card').first();
    await expect(card.locator('h3')).toBeInViewport({ ratio: 1 });
    const contentFits = await card.evaluate(element => {
        const bounds = element.getBoundingClientRect();
        return [...element.querySelectorAll('h3, dt, dd')].every(child => {
            const rect = child.getBoundingClientRect();
            return rect.top >= bounds.top && rect.bottom <= bounds.bottom
                && rect.left >= bounds.left && rect.right <= bounds.right;
        });
    });
    expect(contentFits, 'Phone name and parameters must fit inside their card without clipping').toBe(true);
}

test.beforeEach(async ({ page }) => {
    page.qaErrors = [];
    page.qaConsole = [];
    page.on('pageerror', error => page.qaErrors.push(error.message));
    page.on('console', message => {
        if (['error', 'warning'].includes(message.type())) page.qaConsole.push(message.text());
    });
});

test.afterEach(async ({ page }, testInfo) => {
    expect(page.qaErrors, 'Browser runtime errors').toEqual([]);
    const expectedNetworkErrors = testInfo.annotations.some(annotation => annotation.type === 'expected-network-errors');
    const consoleErrors = page.qaConsole.filter(message => !(expectedNetworkErrors && message.startsWith('Failed to load resource:')));
    expect(consoleErrors, 'Unexpected browser console messages').toEqual([]);
    await expect(page.locator('vite-error-overlay')).toHaveCount(0);
});

test('brand pagination reads 24 at a time without missing or repeating phones', async ({ page }) => {
    const requests = [];
    page.on('request', request => {
        const url = new URL(request.url());
        if (url.pathname === '/api/phones') requests.push(url);
    });
    await page.goto('/XIAOMI');
    await expect(page).toHaveTitle(/智能手机参数站/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('小米');
    await expect(page.locator('a.phone-card')).toHaveCount(24);
    await expectReadableCard(page);
    await capture(page, 'brand-desktop');
    await page.getByRole('button', { name: '加载更多', exact: true }).click();
    await expect(page.locator('a.phone-card')).toHaveCount(48);
    await page.getByRole('button', { name: '加载更多', exact: true }).click();
    await expect(page.locator('a.phone-card')).toHaveCount(53);
    await expect(page.getByRole('button', { name: '加载更多', exact: true })).toHaveCount(0);
    const names = await page.locator('a.phone-card h3').allTextContents();
    expect(new Set(names).size).toBe(53);
    expect(requests).toHaveLength(3);
    expect(requests.every(url => url.searchParams.get('limit') === '24' && url.searchParams.get('paginate') === 'cursor')).toBe(true);
    expect(requests[1].searchParams.get('cursor')).toBeTruthy();
});

test('brand search paginates and returns from detail with its keyword and text price', async ({ page }) => {
    await page.goto('/XIAOMI');
    const search = page.getByRole('searchbox', { name: '搜索小米型号' });
    await search.fill('QA Xiaomi');
    await expect(page).toHaveURL(/q=QA(?:%20|\+)Xiaomi/);
    await expect(page.locator('a.phone-card')).toHaveCount(24);
    await page.getByRole('button', { name: '加载更多', exact: true }).click();
    await expect(page.locator('a.phone-card')).toHaveCount(48);
    const phone = page.locator('a.phone-card').filter({ has: page.getByRole('heading', { name: 'QA Xiaomi 01', exact: true }) });
    await expect(phone).toContainText('3999 起');
    await phone.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('heading', { name: 'QA Xiaomi 01', exact: true })).toBeVisible();
    await expect(page.locator('.summary-grid')).toContainText('3999 起');
    await capture(page, 'detail-text-price');
    await page.getByRole('button', { name: '返回', exact: true }).click();
    await expect(page).toHaveURL(/\/XIAOMI\?q=/);
    await expect(search).toHaveValue('QA Xiaomi');
    await expect(page.locator('a.phone-card')).toHaveCount(24);
    await search.fill('QA Xiaomi 01');
    await expect(page.locator('a.phone-card')).toHaveCount(1);
    await expect(page.getByRole('button', { name: '加载更多', exact: true })).toHaveCount(0);
    await search.fill('');
    await expect(page.locator('a.phone-card')).toHaveCount(24);
});

test('cards open real detail links in a new tab', async ({ page, context }) => {
    await page.goto('/XIAOMI');
    await expect(page.locator('a.phone-card')).toHaveCount(24);
    const phone = page.locator('a.phone-card').first();
    await expect(phone).toHaveAttribute('href', /\/phone\/\d+/);
    const href = await phone.getAttribute('href');
    // Exercise native link navigation with a modifier supported across platforms.
    const [popup] = await Promise.all([
        context.waitForEvent('page'),
        phone.click({ modifiers: ['ControlOrMeta'] }),
    ]);
    await expect(popup).toHaveURL(new URL(href, page.url()).href);
    await expect(popup).toHaveTitle(/智能手机参数站/);
    await expect(popup.getByRole('heading', { name: 'QA Xiaomi 01', exact: true })).toBeVisible();
    await expect(page).toHaveURL(/\/XIAOMI$/);
    await capture(popup, 'card-new-tab');
    await popup.close();
});

test('switching brands cancels a pending search without showing stale results', async ({ page }) => {
    await page.route('**/api/brands/XIAOMI/search?**', async route => {
        await new Promise(resolve => setTimeout(resolve, 700));
        await route.continue().catch(() => {});
    });
    await page.goto('/XIAOMI');
    const requested = page.waitForRequest(request => request.url().includes('/api/brands/XIAOMI/search?'));
    await page.getByRole('searchbox').fill('QA Xiaomi');
    const request = await requested;
    const cancelled = page.waitForEvent('requestfailed', { predicate: failed => failed === request });
    await page.getByRole('link', { name: '分类', exact: true }).click();
    await cancelled;
    await page.locator('a[href="/APPLE"]').click();
    await expect(page.locator('a.phone-card')).toHaveCount(24);
    await expect(page.getByRole('searchbox')).toHaveValue('');
    await expect(page.locator('a.phone-card').first()).toContainText('QA Apple');
    await expect(page.locator('a.phone-card').filter({ hasText: 'QA Xiaomi' })).toHaveCount(0);
});

test('one failed homepage block leaves other blocks usable', async ({ page }, testInfo) => {
    testInfo.annotations.push({ type: 'expected-network-errors', description: 'Injected HTTP 500 for the featured block.' });
    await page.route('**/api/homepage-featured-phones?**', route => route.fulfill({ status: 500, json: { message: 'Injected failure' } }));
    await page.goto('/');
    await expect(page.locator('.hot-phones [role="alert"]')).toBeVisible();
    await expect(page.locator('.recent-phones a.phone-card')).toHaveCount(6);
    await expect(page.locator('#heroCarousel .carousel-item')).toHaveCount(2);
    await expect(page.locator('.brand-link')).not.toHaveCount(0);
    await capture(page, 'homepage-partial-failure');
    await page.getByRole('searchbox', { name: '搜索手机', exact: true }).fill('QA Apple 01');
    await expect(page.locator('.search-results-grid a.phone-card')).toHaveCount(1);
    await expect(page.locator('.search-results-grid')).toContainText('QA Apple 01');
});

test('carousel survives navigation during a slide and reinitializes on return', async ({ page }) => {
    await page.goto('/');
    await expect(page.locator('#heroCarousel .carousel-item.active')).toHaveCount(1);
    await page.getByRole('button', { name: '下一张', exact: true }).click();
    await page.getByRole('link', { name: '分类', exact: true }).click();
    await expect(page.locator('#heroCarousel')).toHaveCount(0);
    await page.getByRole('link', { name: '首页', exact: true }).click();
    const items = page.locator('#heroCarousel .carousel-item');
    await expect(items.first()).toHaveClass(/active/);
    await page.getByRole('button', { name: '下一张', exact: true }).click();
    await expect(items.nth(1)).toHaveClass(/active/);
    await expect(items.first()).not.toHaveClass(/active/);
});

test('detail distinguishes missing, network failure and rate limiting and can retry', async ({ page }, testInfo) => {
    testInfo.annotations.push({ type: 'expected-network-errors', description: 'Injected network failure and HTTP 429, plus a real 404.' });
    await page.goto('/phone/999999');
    await expect(page.locator('[role="alert"]')).toContainText(/找不到|不存在/);
    await expect(page.getByRole('button', { name: '重试', exact: true })).toHaveCount(0);

    const pattern = '**/api/phones/1?**';
    await page.route(pattern, route => route.abort('failed'));
    await page.goto('/phone/1');
    await expect(page.locator('[role="alert"]')).toContainText(/失败|网络/);
    await expect(page.getByRole('button', { name: '重试', exact: true })).toBeVisible();
    await page.unroute(pattern);
    await page.route(pattern, route => route.fulfill({ status: 429, headers: { 'Retry-After': '2' }, json: { message: 'Too Many Attempts.' } }));
    await page.getByRole('button', { name: '重试', exact: true }).click();
    await expect(page.locator('[role="alert"]')).toContainText(/频繁/);
    await page.unroute(pattern);
    await page.getByRole('button', { name: '重试', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'QA Xiaomi 01', exact: true })).toBeVisible();
});

test('mobile brand and detail layouts fit the viewport and navigation responds', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/XIAOMI');
    await expect(page.locator('a.phone-card')).toHaveCount(24);
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await expectReadableCard(page);
    await capture(page, 'brand-mobile');
    await page.locator('a.phone-card').first().click();
    await expect(page.locator('.summary-grid')).toContainText('3999 起');
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
    await capture(page, 'detail-mobile');
    await page.getByRole('button', { name: 'Toggle navigation' }).click();
    await page.getByRole('link', { name: '分类', exact: true }).click();
    await expect(page).toHaveURL(/\/category$/);
    await expect(page.getByRole('button', { name: 'Toggle navigation' })).toHaveAttribute('aria-expanded', 'false');
});

async function login(page) {
    await page.goto('/login');
    await page.getByLabel('电子邮件', { exact: true }).fill('browser-owner@example.test');
    await page.getByLabel('密码', { exact: true }).fill('browser-test-password');
    await page.getByRole('button', { name: '登录', exact: true }).click();
    await expect(page).toHaveURL(/\/dashboard$/);
}

test('admin picker loads at most 20 and retains the selection after searching', async ({ page }) => {
    await login(page);
    const loaded = page.waitForResponse(response => new URL(response.url()).pathname === '/api/phones');
    await page.goto('/admin/homepage');
    const response = await loaded;
    expect(new URL(response.url()).searchParams.get('limit')).toBe('20');
    expect(await response.json()).toHaveLength(20);
    const picker = page.locator('[data-product-picker]');
    const select = picker.getByRole('combobox');
    await expect(select.locator('option')).toHaveCount(21);
    const selectedId = await select.locator('option').nth(1).getAttribute('value');
    const selectedLabel = await select.locator('option').nth(1).textContent();
    await select.selectOption(selectedId);
    await picker.getByRole('searchbox').fill('QA Xiaomi 01');
    await expect(select.locator('option')).toHaveCount(3);
    await expect(select).toHaveValue(selectedId);
    await expect(select.locator('option:checked')).toHaveText(selectedLabel);
    await expect(select).toContainText('QA Xiaomi 01');
    await select.selectOption('1');
    await page.getByRole('button', { name: '添加热门机型', exact: true }).click();
    await expect(page.locator('[role="alert"]')).toBeVisible();
    await expect(select).toHaveValue('1');
    await expect(select.locator('option:checked')).toContainText('QA Xiaomi 01');
    await capture(page, 'admin-picker');
});

test('failed admin edits restore only the submitted row and unchecked slides stay unpublished', async ({ page }) => {
    await login(page);
    await page.goto('/admin/homepage-slides');
    await page.locator('#title-1').fill('待修正的第一张轮播');
    await page.locator('#image-url-1').fill('javascript:invalid');
    await page.locator('#slide-update-1 input[type="checkbox"]').uncheck();
    await page.locator('button[form="slide-update-1"]').click();
    await expect(page.locator('[role="alert"]')).toBeVisible();
    await expect(page.locator('#title-1')).toHaveValue('待修正的第一张轮播');
    await expect(page.locator('#title-2')).toHaveValue('QA 轮播2');
    await expect(page.locator('#title')).toHaveValue('');
    await expect(page.locator('#slide-update-1 input[type="checkbox"]')).not.toBeChecked();
    await expect(page.locator('#slide-update-2 input[type="checkbox"]')).toBeChecked();
    await capture(page, 'admin-row-error');

    const create = page.locator('form').filter({ has: page.locator('#title') });
    await create.locator('#title').fill('QA 下架轮播');
    await create.locator('#image_url').fill('/assets/logo.png');
    await create.locator('input[type="checkbox"]').uncheck();
    await create.getByRole('button', { name: '添加轮播图', exact: true }).click();
    await expect(page.locator('[role="status"]').first()).toContainText('已添加');
    const slides = await (await page.request.get('/api/homepage-slides')).json();
    expect(slides.some(slide => slide.title === 'QA 下架轮播')).toBe(false);
});
