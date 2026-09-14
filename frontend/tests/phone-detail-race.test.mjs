// @vitest-environment jsdom
import assert from 'node:assert/strict'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, test, vi } from 'vitest'
import { reactive } from 'vue'

import PhoneDetail from '@/views/PhoneDetail.vue'
import { phone } from './helpers/catalog.mjs'

// A cancelled request may already have a response on the wire. Verify both
// actual cancellation and the guard that prevents late responses being shown.
const api = vi.hoisted(() => ({
    getPhoneById: vi.fn(),
    getPhoneDetail: vi.fn(),
}))

vi.mock('@/services/phoneApi.js', () => api)

let wrapper

beforeEach(() => {
    api.getPhoneById.mockResolvedValue(null)
    api.getPhoneDetail.mockResolvedValue(null)
})

afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
    // restoreAllMocks only puts vi.spyOn targets back; the vi.fn() API doubles
    // keep their call history until they are reset explicitly.
    vi.restoreAllMocks()
    vi.resetAllMocks()
})

function mountDetail(props = {}) {
    const route = reactive({ params: { ...props }, query: {}, name: 'PhoneDetailById' })
    const router = { push: vi.fn(), replace: vi.fn(), go: vi.fn() }

    wrapper = mount(PhoneDetail, {
        props,
        global: { mocks: { $route: route, $router: router } },
    })

    return { route, router, vm: wrapper.vm }
}

// Navigate the mounted component to another phone the way the router does:
// props update first, then the watched `$route.params` object is replaced.
async function navigateTo(route, props) {
    await wrapper.setProps(props)
    route.params = { ...props }
    await settle()
}

async function settle(times = 4) {
    for (let index = 0; index < times; index += 1) {
        await wrapper.vm.$nextTick()
    }
}

function silenceConsoleError() {
    vi.spyOn(console, 'error').mockImplementation(() => {})
}

test('a slow earlier lookup cannot overwrite the phone the route now points at', async () => {
    const slow = Promise.withResolvers()
    const fast = Promise.withResolvers()
    api.getPhoneById.mockReturnValueOnce(slow.promise).mockReturnValueOnce(fast.promise)

    const { route, vm } = mountDetail({ id: '1' })
    await settle()
    assert.equal(api.getPhoneById.mock.calls.length, 1)

    await navigateTo(route, { id: '2' })
    assert.deepEqual(
        api.getPhoneById.mock.calls.map((call) => call[0]),
        ['1', '2'],
    )

    fast.resolve({ id: 2, phonename: 'Find X9' })
    await settle()
    assert.equal(vm.phone.id, 2)

    slow.resolve({ id: 1, phonename: 'Reno 14 (stale)' })
    await settle()

    assert.equal(vm.phone.id, 2)
    assert.equal(vm.loading, false)
    assert.equal(wrapper.get('h1').text(), 'Find X9')
})

test('a stale response cannot drop the spinner of a lookup still in flight', async () => {
    const slow = Promise.withResolvers()
    api.getPhoneById.mockReturnValueOnce(slow.promise).mockReturnValueOnce(new Promise(() => {}))

    const { route, vm } = mountDetail({ id: '1' })
    await settle()

    await navigateTo(route, { id: '2' })

    slow.resolve({ id: 1, phonename: 'Reno 14 (stale)' })
    await settle()

    // The phone the route points at has not arrived yet, so the page must stay
    // in its loading state instead of flashing the previous phone.
    assert.equal(vm.loading, true)
    assert.equal(vm.phone, null)
})

test('a stale failure cannot blank out the phone from a newer lookup', async () => {
    silenceConsoleError()
    const slow = Promise.withResolvers()
    const fast = Promise.withResolvers()
    api.getPhoneById.mockReturnValueOnce(slow.promise).mockReturnValueOnce(fast.promise)

    const { route, vm } = mountDetail({ id: '1' })
    await settle()
    await navigateTo(route, { id: '2' })

    fast.resolve({ id: 2, phonename: 'Find X9' })
    await settle()

    slow.reject(new Error('network down'))
    await settle()

    assert.equal(vm.phone.id, 2)
    assert.equal(vm.loading, false)
})

test('a network failure offers retry rather than claiming the phone does not exist', async () => {
    silenceConsoleError()
    api.getPhoneById.mockRejectedValueOnce(new Error('network down'))

    const { vm } = mountDetail({ id: '1' })
    await settle()

    assert.equal(vm.phone, null)
    assert.equal(vm.loading, false)
    assert.equal(wrapper.get('[role="alert"]').text(), '手机详情加载失败，请稍后重试。')
    const retry = wrapper.get('button')
    assert.equal(retry.text(), '重试')

    api.getPhoneById.mockResolvedValueOnce(phone())
    await retry.trigger('click')
    await settle()
    assert.equal(wrapper.get('h1').text(), 'Find X9')
    assert.equal(wrapper.find('[role="alert"]').exists(), false)
})

test('a brand and slug route resolves through the detail endpoint', async () => {
    api.getPhoneDetail.mockResolvedValue({ id: 7, phonename: 'Find X9', price: '3999' })

    const { vm } = mountDetail({ brandName: 'OPPO', phoneNameSlug: 'find-x9' })
    await settle()

    assert.deepEqual(
        api.getPhoneDetail.mock.calls.map((call) => call.slice(0, 2)),
        [['OPPO', 'find-x9']],
    )
    assert.ok(api.getPhoneDetail.mock.calls[0][2].signal instanceof AbortSignal)
    assert.equal(api.getPhoneById.mock.calls.length, 0)
    assert.equal(vm.phone.id, 7)
    assert.equal(wrapper.get('h1').text(), 'Find X9')
})

test('a route with neither an id nor a brand and slug pair issues no request', async () => {
    const { vm } = mountDetail()
    await settle()

    assert.equal(api.getPhoneById.mock.calls.length, 0)
    assert.equal(api.getPhoneDetail.mock.calls.length, 0)
    assert.equal(vm.phone, null)
    assert.equal(vm.loading, false)
})

test('a 404 reports that the phone was not found without a retry prompt', async () => {
    api.getPhoneById.mockRejectedValueOnce(Object.assign(new Error('Not Found'), { status: 404 }))
    mountDetail({ id: '404' })
    await settle()

    assert.equal(wrapper.get('[role="alert"]').text(), '找不到该手机的详细信息。')
    assert.equal(wrapper.find('button').exists(), false)
})

test('a rate limit is distinguished from missing data and network failures', async () => {
    api.getPhoneById.mockRejectedValueOnce(
        Object.assign(new Error('Too Many Requests'), { status: 429 }),
    )
    mountDetail({ id: '1' })
    await settle()

    assert.equal(wrapper.get('[role="alert"]').text(), '请求频繁，请稍后重试。')
    assert.equal(wrapper.get('button').text(), '重试')
})

test('the detail page retains text prices and prefers an explicit display price', async () => {
    api.getPhoneById.mockResolvedValueOnce(phone({ price: '3999 起', displayPrice: '3999 起' }))
    const { route } = mountDetail({ id: '1' })
    await settle()
    assert.equal(wrapper.findAll('.summary-item strong')[0].text(), '3999 起')

    api.getPhoneById.mockResolvedValueOnce(phone({ id: 2, price: 0, displayPrice: 'US$799' }))
    await navigateTo(route, { id: '2' })
    assert.equal(wrapper.findAll('.summary-item strong')[0].text(), 'US$799')
})

test('navigating to another detail cancels the request being left', async () => {
    api.getPhoneById.mockReturnValue(new Promise(() => {}))
    const { route } = mountDetail({ id: '1' })
    const firstSignal = api.getPhoneById.mock.calls[0][1].signal

    await navigateTo(route, { id: '2' })
    assert.equal(firstSignal.aborted, true)
    assert.equal(api.getPhoneById.mock.calls[1][1].signal.aborted, false)
})

test('leaving the detail cancels its request', async () => {
    api.getPhoneById.mockReturnValueOnce(new Promise(() => {}))
    mountDetail({ id: '1' })
    const signal = api.getPhoneById.mock.calls[0][1].signal
    assert.equal(signal.aborted, false)

    wrapper.unmount()
    wrapper = undefined
    assert.equal(signal.aborted, true)
})

test('an image that fails on one detail does not prevent a new phone image from loading', async () => {
    api.getPhoneById.mockResolvedValueOnce(phone({ imgurl: '/missing.png' }))
    const { route } = mountDetail({ id: '1' })
    await settle()
    await wrapper.get('.detail-media img').trigger('error')
    assert.equal(wrapper.get('.detail-media img').attributes('src'), '/assets/logo.png')

    api.getPhoneById.mockResolvedValueOnce(phone({ id: 2, imgurl: '/other-phone.png' }))
    await navigateTo(route, { id: '2' })
    assert.equal(wrapper.get('.detail-media img').attributes('src'), '/other-phone.png')
    await wrapper.get('.detail-media img').trigger('error')
    assert.equal(wrapper.get('.detail-media img').attributes('src'), '/assets/logo.png')
})

test('the return button goes back to the previous catalog route', async () => {
    vi.spyOn(window.history, 'state', 'get').mockReturnValue({ back: '/OPPO' })
    api.getPhoneById.mockResolvedValueOnce(phone())
    const { router } = mountDetail({ id: '1' })
    await settle()
    await wrapper.get('.back-button').trigger('click')

    assert.deepEqual(router.go.mock.calls, [[-1]])
    assert.equal(router.push.mock.calls.length, 0)
})

test('a direct detail visit returns to categories even if the browser has unrelated history', async () => {
    vi.spyOn(window.history, 'state', 'get').mockReturnValue({ back: null })
    vi.spyOn(window.history, 'length', 'get').mockReturnValue(3)
    api.getPhoneById.mockResolvedValueOnce(phone())
    const { router } = mountDetail({ id: '1' })
    await settle()
    await wrapper.get('.back-button').trigger('click')

    assert.deepEqual(router.push.mock.calls, [['/category']])
    assert.equal(router.go.mock.calls.length, 0)
})
