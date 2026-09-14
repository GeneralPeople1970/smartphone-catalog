import assert from 'node:assert/strict'
import { afterEach, beforeEach, test, vi } from 'vitest'
import {
    getPhonesByBrand,
    searchPhonesByBrand,
    getPhoneById,
    getPhoneDetail,
} from '@/services/phoneApi.js'
import { cursorPage, phone } from './helpers/catalog.mjs'

let fetchMock

beforeEach(() => {
    fetchMock = vi
        .fn()
        .mockResolvedValue(new Response(JSON.stringify(cursorPage([phone()], 'next-page'))))
    vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => vi.unstubAllGlobals())

function requestUrl(index = 0) {
    return new URL(fetchMock.mock.calls[index][0], 'http://catalog.test')
}

test('brand browsing reads only one 24-phone page even when another cursor is available', async () => {
    const signal = new AbortController().signal
    const page = await getPhonesByBrand('OPPO', { signal })

    assert.equal(fetchMock.mock.calls.length, 1)
    assert.equal(requestUrl().pathname, '/api/phones')
    assert.equal(requestUrl().searchParams.get('brand'), 'OPPO')
    assert.equal(requestUrl().searchParams.get('limit'), '24')
    assert.equal(requestUrl().searchParams.get('paginate'), 'cursor')
    assert.equal(requestUrl().searchParams.has('cursor'), false)
    assert.equal(fetchMock.mock.calls[0][1].signal, signal)
    assert.equal(page.meta.nextCursor, 'next-page')
    assert.equal(page.meta.hasMore, true)
})

test('the next brand page forwards its opaque cursor exactly', async () => {
    await getPhonesByBrand('XIAOMI', { cursor: 'a+b/=opaque' })
    assert.equal(requestUrl().searchParams.get('cursor'), 'a+b/=opaque')
    assert.equal(fetchMock.mock.calls.length, 1)
})

test('brand search requests 24 results and forwards both query and cursor', async () => {
    const signal = new AbortController().signal
    await searchPhonesByBrand('OPPO', 'Find X', { cursor: 'search-page-2', signal })

    assert.equal(requestUrl().pathname, '/api/brands/OPPO/search')
    assert.equal(requestUrl().searchParams.get('q'), 'Find X')
    assert.equal(requestUrl().searchParams.get('paginate'), 'cursor')
    assert.equal(requestUrl().searchParams.get('limit'), '24')
    assert.equal(requestUrl().searchParams.get('cursor'), 'search-page-2')
    assert.equal(fetchMock.mock.calls[0][1].signal, signal)
})

test('both detail URLs forward cancellation and request the shared display price', async () => {
    const signal = new AbortController().signal
    await getPhoneById(1, { signal })
    fetchMock.mockResolvedValueOnce(new Response(JSON.stringify(phone())))
    await getPhoneDetail('OPPO', 'find-x9', { signal })

    assert.equal(requestUrl(0).pathname, '/api/phones/1')
    assert.equal(requestUrl(1).pathname, '/api/phones/detail')
    for (let index = 0; index < 2; index++) {
        assert.ok(requestUrl(index).searchParams.get('fields').split(',').includes('displayPrice'))
        assert.equal(fetchMock.mock.calls[index][1].signal, signal)
    }
})
