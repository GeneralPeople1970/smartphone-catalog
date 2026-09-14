// @vitest-environment jsdom
import assert from 'node:assert/strict'
import { mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import { afterEach, test } from 'vitest'
import PhoneCard from '@/components/PhoneCard.vue'
import PhoneImage from '@/components/PhoneImage.vue'
import { formatPrice, formatBattery } from '@/utils/phone.js'
import { phone } from './helpers/catalog.mjs'

let wrapper
afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
})

const router = createRouter({
    history: createMemoryHistory(),
    routes: [
        { path: '/', component: { template: '<div />' } },
        { path: '/phone/:id', name: 'PhoneDetailById', component: { template: '<div />' } },
        {
            path: '/:brandName/:phoneNameSlug',
            name: 'PhoneDetail',
            component: { template: '<div />' },
        },
    ],
})

test('shared formatting preserves textual prices and handles missing or zero prices consistently', () => {
    for (const [value, expected] of [
        [{ price: '3999 起' }, '3999 起'],
        [{ price: 3999 }, '￥3999'],
        [{ price: 3999, displayPrice: 'US$799' }, 'US$799'],
        [{ price: '3999', displayPrice: '   ' }, '￥3999'],
        [{ price: '0.00' }, '暂无价格'],
        [{ price: null }, '暂无价格'],
        [{}, '暂无价格'],
    ]) {
        assert.equal(formatPrice(value), expected)
    }
    assert.equal(formatBattery(5000), '5000 mAh')
    assert.equal(formatBattery(null), '待补充')
})

test('all card variants use the same price, battery and native detail link', async () => {
    await router.push('/')
    wrapper = mount(PhoneCard, {
        props: { phone: phone({ price: '3999 起' }) },
        global: { plugins: [router] },
    })
    for (const variant of ['featured', 'brand', 'search']) {
        await wrapper.setProps({ variant })
        const link = wrapper.get('a')
        assert.equal(link.attributes('href'), '/phone/1')
        assert.ok(link.text().includes('3999 起'))
        assert.ok(link.text().includes('5000 mAh'))
    }

    // Modified clicks must retain native browser behavior for opening a tab.
    const modifiedClick = new MouseEvent('click', {
        button: 0,
        ctrlKey: true,
        cancelable: true,
        bubbles: true,
    })
    let preventedByRouter
    wrapper.get('a').element.addEventListener(
        'click',
        (event) => {
            preventedByRouter = event.defaultPrevented
            // jsdom cannot open a new document; stop it after observing RouterLink.
            event.preventDefault()
        },
        { once: true },
    )
    wrapper.get('a').element.dispatchEvent(modifiedClick)
    assert.equal(preventedByRouter, false)
    assert.equal(router.currentRoute.value.path, '/')
})

test('an older record without an id retains its brand and slug detail link', async () => {
    await router.push('/')
    wrapper = mount(PhoneCard, {
        props: { phone: phone({ id: null, slug: 'find-x9-pro' }) },
        global: { plugins: [router] },
    })
    assert.equal(wrapper.get('a').attributes('href'), '/OPPO/find-x9-pro')
})

test('a shared image retries its new source after an earlier source failed', async () => {
    wrapper = mount(PhoneImage, { props: { src: '/missing.png', alt: 'Find X9' } })
    const img = wrapper.get('img')
    await img.trigger('error')
    assert.equal(img.attributes('src'), '/assets/logo.png')
    await img.trigger('error')
    assert.equal(img.attributes('src'), '/assets/logo.png')

    await wrapper.setProps({ src: '/new-phone.png', alt: 'Xiaomi 17' })
    assert.equal(img.attributes('src'), '/new-phone.png')
    assert.equal(img.attributes('alt'), 'Xiaomi 17')
})
