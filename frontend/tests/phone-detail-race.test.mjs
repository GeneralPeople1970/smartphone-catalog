// @vitest-environment jsdom
import assert from 'node:assert/strict'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, test, vi } from 'vitest'
import { reactive } from 'vue'

import PhoneDetail from '@/views/PhoneDetail.vue'

// The detail endpoints take no abort signal, so the requestId guard in
// fetchPhoneDetails is the only thing standing between a detail -> detail
// navigation and a stale response winning the race.
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

test('a failed lookup clears the phone and shows the not-found notice', async () => {
    silenceConsoleError()
    api.getPhoneById.mockRejectedValueOnce(new Error('network down'))

    const { vm } = mountDetail({ id: '1' })
    await settle()

    assert.equal(vm.phone, null)
    assert.equal(vm.loading, false)
    assert.equal(wrapper.get('.alert-warning').text(), '找不到该手机的详细信息。')
})

test('a brand and slug route resolves through the detail endpoint', async () => {
    api.getPhoneDetail.mockResolvedValue({ id: 7, phonename: 'Find X9', price: '3999' })

    const { vm } = mountDetail({ brandName: 'OPPO', phoneNameSlug: 'find-x9' })
    await settle()

    assert.deepEqual(api.getPhoneDetail.mock.calls, [['OPPO', 'find-x9']])
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
