// @vitest-environment jsdom
import assert from 'node:assert/strict'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, test, vi } from 'vitest'
import { reactive } from 'vue'

// /search redirects to Home with its query preserved; exercise the debounce
// and cancellation visible on that shared search surface.
import Home from '@/views/Home.vue'

const api = vi.hoisted(() => ({
    getBrands: vi.fn(),
    getFeaturedPhones: vi.fn(),
    getHomepageFeaturedPhones: vi.fn(),
    getHomepageSlides: vi.fn(),
    searchPhones: vi.fn(),
}))

vi.mock('@/services/phoneApi.js', () => api)

const DEBOUNCE_MS = 250
const SEARCH_INPUT = 'input[aria-label="搜索手机"]'

let wrapper

beforeEach(() => {
    // Fake timers stop the 250ms search debounce from firing behind a test's
    // back. Vue's nextTick is microtask-based, so it stays unaffected.
    vi.useFakeTimers()
    api.getBrands.mockResolvedValue([])
    api.getFeaturedPhones.mockResolvedValue([])
    api.getHomepageFeaturedPhones.mockResolvedValue([])
    api.getHomepageSlides.mockResolvedValue([])
    api.searchPhones.mockResolvedValue([])
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

function mountHome({ q = '' } = {}) {
    const route = reactive({ query: q ? { q } : {}, hash: '', params: {}, name: 'Home' })
    const router = {
        // Mirrors the real router closely enough that the
        // keyword -> route -> debounce -> search chain runs end to end.
        replace: vi.fn((target) => {
            route.query = { ...(target.query ?? {}) }
            route.hash = target.hash ?? ''
        }),
        push: vi.fn(),
    }

    wrapper = mount(Home, {
        global: {
            mocks: { $route: route, $router: router },
            stubs: { RouterLink: { template: '<a><slot /></a>' } },
        },
    })

    return { route, router, vm: wrapper.vm }
}

// Drains the microtask queue (and with it Vue's render flush) without touching
// the faked timers.
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

test('a slow earlier search cannot overwrite the results of a newer one', async () => {
    const { vm } = mountHome()
    await settle()

    const stale = Promise.withResolvers()
    const fresh = Promise.withResolvers()
    api.searchPhones.mockReturnValueOnce(stale.promise).mockReturnValueOnce(fresh.promise)

    const stalePending = vm.runSearch('find x')
    const freshPending = vm.runSearch('find x9')

    fresh.resolve([{ id: 2, phonename: 'Find X9' }])
    await freshPending
    assert.deepEqual(ids(vm.results), [2])

    stale.resolve([{ id: 1, phonename: 'Find X (stale)' }])
    await stalePending

    assert.deepEqual(ids(vm.results), [2])
    assert.equal(vm.loading, false)
    assert.equal(vm.errorMessage, '')
})

test('starting a new search aborts the request still in flight', async () => {
    const { vm } = mountHome()
    await settle()

    api.searchPhones.mockReturnValue(new Promise(() => {}))

    vm.runSearch('find x')
    const firstSignal = api.searchPhones.mock.calls.at(-1)[1].signal
    assert.equal(firstSignal.aborted, false)

    vm.runSearch('find x9')

    assert.equal(firstSignal.aborted, true)
    assert.equal(api.searchPhones.mock.calls.at(-1)[1].signal.aborted, false)
})

test('an aborted request neither reports an error nor drops the newer spinner', async () => {
    const { vm } = mountHome()
    await settle()

    const aborted = Promise.withResolvers()
    api.searchPhones.mockReturnValueOnce(aborted.promise).mockReturnValueOnce(new Promise(() => {}))

    const abortedPending = vm.runSearch('find x')
    vm.runSearch('find x9')

    aborted.reject(abortError())
    await abortedPending
    await settle()

    assert.equal(vm.errorMessage, '')
    assert.deepEqual(vm.results, [])
    // The newer search is still in flight, so the spinner has to stay up.
    assert.equal(vm.loading, true)
})

test('a failed search reports the error and empties the results', async () => {
    const { vm } = mountHome()
    await settle()
    silenceConsoleError()

    api.searchPhones.mockRejectedValueOnce(new Error('network down'))
    await vm.runSearch('find x9')
    await settle()

    assert.equal(vm.errorMessage, '搜索失败，请稍后重试。')
    assert.deepEqual(vm.results, [])
    assert.equal(vm.loading, false)
    assert.equal(wrapper.get('.alert-warning').text(), '搜索失败，请稍后重试。')
})

test('a stale failure cannot clobber the results of a newer search', async () => {
    const { vm } = mountHome()
    await settle()
    silenceConsoleError()

    const stale = Promise.withResolvers()
    const fresh = Promise.withResolvers()
    api.searchPhones.mockReturnValueOnce(stale.promise).mockReturnValueOnce(fresh.promise)

    const stalePending = vm.runSearch('find x')
    const freshPending = vm.runSearch('find x9')

    fresh.resolve([{ id: 2, phonename: 'Find X9' }])
    await freshPending

    stale.reject(new Error('slow network'))
    await stalePending
    await settle()

    assert.deepEqual(ids(vm.results), [2])
    assert.equal(vm.errorMessage, '')
})

test('an empty keyword clears the results without issuing a request', async () => {
    const { vm } = mountHome()
    await settle()
    api.searchPhones.mockClear()

    await vm.runSearch('   ')
    await settle()

    assert.equal(api.searchPhones.mock.calls.length, 0)
    assert.deepEqual(vm.results, [])
    assert.equal(vm.searched, false)
    assert.equal(vm.loading, false)
})

test('typing collapses into a single debounced request for the final keyword', async () => {
    const { router } = mountHome()
    await settle()
    api.searchPhones.mockClear()

    const input = wrapper.get(SEARCH_INPUT)
    for (const value of ['fin', 'find', 'find x9']) {
        await input.setValue(value)
        await settle(2)
    }

    // Nothing may leave for the network until the debounce window closes.
    assert.equal(api.searchPhones.mock.calls.length, 0)

    vi.advanceTimersByTime(DEBOUNCE_MS)
    await settle()

    assert.equal(api.searchPhones.mock.calls.length, 1)
    assert.equal(api.searchPhones.mock.calls[0][0], 'find x9')
    // Every keystroke still syncs the query string, so the search is linkable.
    assert.equal(router.replace.mock.calls.length, 3)
})

test('unmounting aborts the search still in flight', async () => {
    const { vm } = mountHome()
    await settle()

    api.searchPhones.mockReturnValue(new Promise(() => {}))
    vm.runSearch('find x9')
    const { signal } = api.searchPhones.mock.calls.at(-1)[1]
    assert.equal(signal.aborted, false)

    wrapper.unmount()
    wrapper = undefined

    assert.equal(signal.aborted, true)
})

test('a failed homepage section leaves successful sections visible', async () => {
    silenceConsoleError()
    api.getHomepageFeaturedPhones.mockRejectedValue(new Error('service down'))
    api.getFeaturedPhones.mockResolvedValue([{ id: 1, phonename: 'Find X9' }])
    api.getBrands.mockResolvedValue([
        { code: 'OPPO', name: 'OPPO', logo: '/assets/brands/OPPO.png', sort: 1 },
    ])

    const { vm } = mountHome()
    await settle(8)

    assert.deepEqual(vm.homepageFeaturedPhones, [])
    assert.deepEqual(ids(vm.recentPhones), [1])
    assert.deepEqual(
        vm.popularBrands.map((brand) => brand.code),
        ['OPPO'],
    )
    assert.equal(wrapper.get('.hot-phones [role="alert"]').text(), '热门机型加载失败。')
    assert.equal(wrapper.get('.recent-phones .phone-card h3').text(), 'Find X9')
    assert.equal(wrapper.get('.brands-section .brand-link p').text(), 'OPPO')
    assert.equal(vm.recentLoading, false)
})

test('popular brands are ordered by sort order and capped at eight', async () => {
    api.getBrands.mockResolvedValue(
        Array.from({ length: 12 }, (unused, index) => ({
            code: `B${index}`,
            name: `Brand ${index}`,
            logo: `/assets/brands/b${index}.png`,
            sort: 12 - index,
        })),
    )

    const { vm } = mountHome()
    await settle(8)

    assert.equal(vm.popularBrands.length, 8)
    assert.deepEqual(
        vm.popularBrands.map((brand) => brand.code),
        ['B11', 'B10', 'B9', 'B8', 'B7', 'B6', 'B5', 'B4'],
    )
    // displayName falls back to name when the API omits it.
    assert.equal(vm.popularBrands[0].displayName, 'Brand 11')
})

test('returning to a cancelled keyword before the debounce still issues a fresh search', async () => {
    api.searchPhones.mockReturnValueOnce(new Promise(() => {}))
    const { vm } = mountHome()
    await settle()
    const input = wrapper.get(SEARCH_INPUT)
    await input.setValue('find')
    vi.advanceTimersByTime(DEBOUNCE_MS)
    await settle()
    const firstSignal = api.searchPhones.mock.calls[0][1].signal

    await input.setValue('reno')
    await settle()
    assert.equal(firstSignal.aborted, true)
    await input.setValue('find')
    api.searchPhones.mockResolvedValueOnce([{ id: 9, phonename: 'Find X9' }])
    vi.advanceTimersByTime(DEBOUNCE_MS)
    await settle()

    assert.equal(api.searchPhones.mock.calls.length, 2)
    assert.equal(api.searchPhones.mock.calls[1][0], 'find')
    assert.deepEqual(ids(vm.results), [9])
    assert.equal(wrapper.get('.phone-card h3').text(), 'Find X9')
})

test('a failed carousel does not hide phones and reports its own failure', async () => {
    api.getHomepageSlides.mockRejectedValueOnce(new Error('slides unavailable'))
    api.getFeaturedPhones.mockResolvedValueOnce([{ id: 1, phonename: 'Find X9' }])
    mountHome()
    await settle()

    assert.equal(wrapper.get('[role="alert"]').text(), '轮播图加载失败。')
    assert.equal(wrapper.get('.recent-phones .phone-card h3').text(), 'Find X9')
})

test('leaving the homepage cancels its independent data requests', async () => {
    api.getHomepageSlides.mockReturnValueOnce(new Promise(() => {}))
    api.getHomepageFeaturedPhones.mockReturnValueOnce(new Promise(() => {}))
    api.getFeaturedPhones.mockReturnValueOnce(new Promise(() => {}))
    api.getBrands.mockReturnValueOnce(new Promise(() => {}))
    mountHome()
    const signals = [
        api.getHomepageSlides,
        api.getHomepageFeaturedPhones,
        api.getFeaturedPhones,
        api.getBrands,
    ].map((fetcher) => fetcher.mock.calls[0][0].signal)
    assert.ok(signals.every((signal) => !signal.aborted))

    wrapper.unmount()
    wrapper = undefined
    assert.ok(signals.every((signal) => signal.aborted))
})

test('clearing a keyword during its debounce returns immediately to home sections', async () => {
    const { vm } = mountHome()
    await settle()
    const input = wrapper.get(SEARCH_INPUT)
    await input.setValue('find')
    await input.setValue('')
    await settle()
    vi.advanceTimersByTime(DEBOUNCE_MS)
    await settle()

    assert.equal(api.searchPhones.mock.calls.length, 0)
    assert.equal(vm.loading, false)
    assert.equal(wrapper.find('.recent-phones').exists(), true)
    assert.equal(wrapper.text().includes('正在搜索...'), false)
})
