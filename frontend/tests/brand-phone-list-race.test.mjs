// @vitest-environment jsdom
import assert from 'node:assert/strict'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, test, vi } from 'vitest'
import { reactive } from 'vue'

import BrandPhoneList from '@/views/Category/BrandPhoneList.vue'

const api = vi.hoisted(() => ({
    getPhonesByBrand: vi.fn(),
    searchPhonesByBrand: vi.fn(),
}))

vi.mock('@/services/phoneApi.js', () => api)

const DEBOUNCE_MS = 250

let wrapper

beforeEach(() => {
    vi.useFakeTimers()
    api.getPhonesByBrand.mockResolvedValue([])
    api.searchPhonesByBrand.mockResolvedValue([])
})

afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
    vi.useRealTimers()
    // restoreAllMocks only puts vi.spyOn targets back; the vi.fn() API doubles
    // keep their call history until they are reset explicitly.
    vi.restoreAllMocks()
    vi.resetAllMocks()
})

function mountBrandList({ routeName = 'OPPOList' } = {}) {
    const route = reactive({ name: routeName, params: {}, query: {} })
    const router = { push: vi.fn(), replace: vi.fn() }

    wrapper = mount(BrandPhoneList, {
        global: { mocks: { $route: route, $router: router } },
    })

    return { route, router, vm: wrapper.vm }
}

async function settle(times = 4) {
    for (let index = 0; index < times; index += 1) {
        await wrapper.vm.$nextTick()
    }
}

function ids(phones) {
    return phones.map((phone) => phone.id)
}

function abortError() {
    const error = new Error('The operation was aborted.')
    error.name = 'AbortError'
    return error
}

function silenceConsoleError() {
    vi.spyOn(console, 'error').mockImplementation(() => {})
}

test('the route name selects which brand is loaded', async () => {
    api.getPhonesByBrand.mockResolvedValue([{ id: 1, phonename: 'Find X9' }])

    const { vm } = mountBrandList({ routeName: 'XIAOMIList' })
    await settle()

    assert.deepEqual(
        api.getPhonesByBrand.mock.calls.map((call) => call[0]),
        ['XIAOMI'],
    )
    assert.deepEqual(ids(vm.phones), [1])
    assert.equal(vm.loading, false)
})

test('a brand list landing during an active search backs it up without replacing it', async () => {
    const listLoad = Promise.withResolvers()
    api.getPhonesByBrand.mockReturnValueOnce(listLoad.promise)

    const { vm } = mountBrandList()
    await settle()

    const search = Promise.withResolvers()
    api.searchPhonesByBrand.mockReturnValueOnce(search.promise)

    vm.searchKeyword = 'find'
    const searchPending = vm.runBrandSearch()

    search.resolve([{ id: 9, phonename: 'Find X9' }])
    await searchPending
    assert.deepEqual(ids(vm.phones), [9])

    // The brand list request left first and lands second. While a search owns
    // the visible list it may only refresh the backing set — but it does have to
    // refresh it, or clearing the box later would fall back to nothing.
    listLoad.resolve([{ id: 1 }, { id: 2 }, { id: 3 }])
    await settle()

    assert.equal(vm.searchActive, true)
    assert.deepEqual(ids(vm.phones), [9])
    assert.deepEqual(ids(vm.allPhones), [1, 2, 3])
    assert.equal(vm.loading, false)
})

test('a slow earlier in-brand search cannot overwrite a newer one', async () => {
    const { vm } = mountBrandList()
    await settle()

    const stale = Promise.withResolvers()
    const fresh = Promise.withResolvers()
    api.searchPhonesByBrand.mockReturnValueOnce(stale.promise).mockReturnValueOnce(fresh.promise)

    vm.searchKeyword = 'find'
    const stalePending = vm.runBrandSearch()
    vm.searchKeyword = 'find x9'
    const freshPending = vm.runBrandSearch()

    fresh.resolve([{ id: 2, phonename: 'Find X9' }])
    await freshPending
    assert.deepEqual(ids(vm.phones), [2])

    stale.resolve([{ id: 1, phonename: 'Find X (stale)' }])
    await stalePending
    await settle()

    assert.deepEqual(ids(vm.phones), [2])
    assert.equal(vm.errorMessage, '')
})

test('starting a new in-brand search aborts the one still in flight', async () => {
    const { vm } = mountBrandList()
    await settle()

    api.searchPhonesByBrand.mockReturnValue(new Promise(() => {}))

    vm.searchKeyword = 'find'
    vm.runBrandSearch()
    const firstSignal = api.searchPhonesByBrand.mock.calls.at(-1)[2].signal
    assert.equal(firstSignal.aborted, false)

    vm.searchKeyword = 'find x9'
    vm.runBrandSearch()

    assert.equal(firstSignal.aborted, true)
    assert.equal(api.searchPhonesByBrand.mock.calls.at(-1)[2].signal.aborted, false)
})

test('an aborted in-brand search neither reports an error nor drops the newer spinner', async () => {
    const { vm } = mountBrandList()
    await settle()

    const aborted = Promise.withResolvers()
    api.searchPhonesByBrand
        .mockReturnValueOnce(aborted.promise)
        .mockReturnValueOnce(new Promise(() => {}))

    vm.searchKeyword = 'find'
    const abortedPending = vm.runBrandSearch()
    vm.searchKeyword = 'find x9'
    vm.runBrandSearch()

    aborted.reject(abortError())
    await abortedPending
    await settle()

    assert.equal(vm.errorMessage, '')
    assert.equal(vm.loading, true)
})

test('clearing the keyword restores the loaded brand list without a request', async () => {
    api.getPhonesByBrand.mockResolvedValue([{ id: 1 }, { id: 2 }])

    const { vm } = mountBrandList()
    await settle()
    assert.deepEqual(ids(vm.allPhones), [1, 2])

    api.searchPhonesByBrand.mockClear()
    vm.searchKeyword = '   '
    await vm.runBrandSearch()
    await settle()

    assert.equal(api.searchPhonesByBrand.mock.calls.length, 0)
    assert.deepEqual(ids(vm.phones), [1, 2])
    assert.equal(vm.loading, false)
})

test('switching brands clears the keyword without cancelling the new brand list load', async () => {
    const { route, vm } = mountBrandList()
    await settle()

    vm.searchKeyword = 'find'
    await settle()
    api.getPhonesByBrand.mockClear()
    api.searchPhonesByBrand.mockClear()

    const nextBrand = Promise.withResolvers()
    api.getPhonesByBrand.mockReturnValueOnce(nextBrand.promise)

    route.name = 'XIAOMIList'
    await settle()

    // The keyword reset must not be mistaken for the user typing: the pending
    // debounce is dropped instead of firing as a spurious empty search.
    vi.advanceTimersByTime(DEBOUNCE_MS)
    await settle()

    nextBrand.resolve([{ id: 5, phonename: 'Xiaomi 17' }])
    await settle()

    assert.equal(vm.searchKeyword, '')
    assert.equal(api.searchPhonesByBrand.mock.calls.length, 0)
    assert.deepEqual(
        api.getPhonesByBrand.mock.calls.map((call) => call[0]),
        ['XIAOMI'],
    )
    assert.deepEqual(ids(vm.phones), [5])
    assert.deepEqual(ids(vm.allPhones), [5])
})

test('a failed in-brand search reports the error and empties the list', async () => {
    const { vm } = mountBrandList()
    await settle()
    silenceConsoleError()

    api.searchPhonesByBrand.mockRejectedValueOnce(new Error('network down'))
    vm.searchKeyword = 'find x9'
    await vm.runBrandSearch()
    await settle()

    assert.equal(vm.errorMessage, '品牌内搜索失败，请稍后重试。')
    assert.deepEqual(vm.phones, [])
    assert.equal(vm.loading, false)
    assert.equal(wrapper.get('.alert-warning').text(), '品牌内搜索失败，请稍后重试。')
})

test('a failed brand list load reports the error and empties the list', async () => {
    silenceConsoleError()
    api.getPhonesByBrand.mockRejectedValueOnce(new Error('network down'))

    const { vm } = mountBrandList()
    await settle()

    assert.equal(vm.errorMessage, '手机数据加载失败，请稍后重试。')
    assert.deepEqual(vm.allPhones, [])
    assert.deepEqual(vm.phones, [])
    assert.equal(vm.loading, false)
})

test('unmounting aborts the in-brand search still in flight', async () => {
    const { vm } = mountBrandList()
    await settle()

    api.searchPhonesByBrand.mockReturnValue(new Promise(() => {}))
    vm.searchKeyword = 'find x9'
    vm.runBrandSearch()
    const { signal } = api.searchPhonesByBrand.mock.calls.at(-1)[2]
    assert.equal(signal.aborted, false)

    wrapper.unmount()
    wrapper = undefined

    assert.equal(signal.aborted, true)
})

test('the brand list load is cancellable', async () => {
    api.getPhonesByBrand.mockReturnValue(new Promise(() => {}))

    mountBrandList()
    await settle()

    const [brand, options] = api.getPhonesByBrand.mock.calls.at(-1)
    assert.equal(brand, 'OPPO')
    // getPhonesByBrand walks up to MAX_CURSOR_PAGES (40) pages and forwards
    // options.signal to every request in the walk, so the caller has to give it
    // one or the whole chain becomes uninterruptible.
    assert.ok(options?.signal instanceof AbortSignal)
})

test("switching brands aborts the previous brand's cursor walk", async () => {
    api.getPhonesByBrand.mockReturnValue(new Promise(() => {}))

    const { route } = mountBrandList()
    await settle()
    const firstSignal = api.getPhonesByBrand.mock.calls.at(-1)[1].signal
    assert.equal(firstSignal.aborted, false)

    route.name = 'XIAOMIList'
    await settle()

    assert.equal(firstSignal.aborted, true)
    assert.equal(api.getPhonesByBrand.mock.calls.at(-1)[1].signal.aborted, false)
})

test('unmounting aborts the brand list load still in flight', async () => {
    api.getPhonesByBrand.mockReturnValue(new Promise(() => {}))

    mountBrandList()
    await settle()
    const { signal } = api.getPhonesByBrand.mock.calls.at(-1)[1]
    assert.equal(signal.aborted, false)

    wrapper.unmount()
    wrapper = undefined

    assert.equal(signal.aborted, true)
})

test('clearing the search box falls back to the brand list that landed during it', async () => {
    const listLoad = Promise.withResolvers()
    api.getPhonesByBrand.mockReturnValueOnce(listLoad.promise)

    const { vm } = mountBrandList()
    await settle()

    // The user types before the brand list has come back.
    const search = Promise.withResolvers()
    api.searchPhonesByBrand.mockReturnValueOnce(search.promise)
    vm.searchKeyword = 'find'
    const searchPending = vm.runBrandSearch()
    search.resolve([{ id: 9, phonename: 'Find X9' }])
    await searchPending

    listLoad.resolve([{ id: 1 }, { id: 2 }, { id: 3 }])
    await settle()

    api.searchPhonesByBrand.mockClear()
    vm.searchKeyword = ''
    await vm.runBrandSearch()
    await settle()

    // Emptying the box shows the brand again instead of claiming it has no
    // phones, and needs no second round trip to do it.
    assert.deepEqual(ids(vm.phones), [1, 2, 3])
    assert.equal(api.searchPhonesByBrand.mock.calls.length, 0)
    assert.equal(vm.loading, false)
    assert.equal(vm.errorMessage, '')
})

test('clearing the search box while the brand list is still loading keeps the spinner up', async () => {
    const listLoad = Promise.withResolvers()
    api.getPhonesByBrand.mockReturnValueOnce(listLoad.promise)

    const { vm } = mountBrandList()
    await settle()

    const search = Promise.withResolvers()
    api.searchPhonesByBrand.mockReturnValueOnce(search.promise)
    vm.searchKeyword = 'find'
    const searchPending = vm.runBrandSearch()
    search.resolve([{ id: 9, phonename: 'Find X9' }])
    await searchPending

    // Box emptied while the brand list is *still* in flight.
    vm.searchKeyword = ''
    await vm.runBrandSearch()
    await settle()

    assert.equal(vm.loading, true)
    assert.equal(vm.errorMessage, '')
    assert.equal(wrapper.get('.text-muted').text(), '正在加载手机数据...')

    listLoad.resolve([{ id: 1 }, { id: 2 }])
    await settle()

    assert.deepEqual(ids(vm.phones), [1, 2])
    assert.equal(vm.loading, false)
})

test("switching brands stops an in-flight search from landing on the new brand's list", async () => {
    const { route, vm } = mountBrandList()
    await settle()

    const search = Promise.withResolvers()
    api.searchPhonesByBrand.mockReturnValueOnce(search.promise)
    vm.searchKeyword = 'find'
    const searchPending = vm.runBrandSearch()
    const searchSignal = api.searchPhonesByBrand.mock.calls.at(-1)[2].signal

    const nextBrand = Promise.withResolvers()
    api.getPhonesByBrand.mockReturnValueOnce(nextBrand.promise)
    route.name = 'XIAOMIList'
    await settle()

    assert.equal(searchSignal.aborted, true)

    // Resolve the new brand's list FIRST, then let the abandoned brand's search
    // response arrive last. Aborting cannot call back a response that was
    // already on the wire, so the requestId bump is what has to stop it from
    // painting over the brand the user is now on.
    nextBrand.resolve([{ id: 5, phonename: 'Xiaomi 17' }])
    await settle()
    assert.deepEqual(ids(vm.phones), [5])

    search.resolve([{ id: 9, phonename: 'Find X9' }])
    await searchPending
    await settle()

    assert.deepEqual(ids(vm.phones), [5])
    assert.deepEqual(ids(vm.allPhones), [5])
})
