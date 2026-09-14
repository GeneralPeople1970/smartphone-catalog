// @vitest-environment jsdom
import assert from 'node:assert/strict'
import { mount } from '@vue/test-utils'
import Carousel from 'bootstrap/js/dist/carousel'
import { afterEach, beforeEach, test, vi } from 'vitest'
import HomepageCarousel from '@/components/HomepageCarousel.vue'

const images = [
    { id: 1, title: 'First phone', image: '/slide-1.png' },
    { id: 2, title: 'Second phone', image: '/slide-2.png' },
]
let wrapper
let errors

function captureError(event) {
    errors.push(event.error?.message || event.message)
    event.preventDefault()
}

beforeEach(() => {
    vi.useFakeTimers()
    errors = []
    window.addEventListener('error', captureError)
})

afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
    vi.clearAllTimers()
    vi.useRealTimers()
    vi.unstubAllGlobals()
    window.removeEventListener('error', captureError)
    document.body.replaceChildren()
})

async function mountCarousel() {
    wrapper = mount(HomepageCarousel, { props: { images }, attachTo: document.body })
    await wrapper.vm.$nextTick()
    return wrapper.get('.carousel').element
}

for (const queuedIndicator of [false, true]) {
    test(`an animated slide can be left safely${queuedIndicator ? ' with a queued indicator click' : ''}`, async () => {
        const element = await mountCarousel()
        await wrapper.get('.carousel-control-next').trigger('click')
        assert.ok(element.querySelector('.carousel-item-next'))
        if (queuedIndicator) await wrapper.get('.carousel-indicators button').trigger('click')

        wrapper.unmount()
        wrapper = undefined
        assert.equal(Carousel.getInstance(element), null)
        await vi.advanceTimersByTimeAsync(6000)
        assert.deepEqual(errors, [])

        const returned = await mountCarousel()
        await wrapper.get('.carousel-control-next').trigger('click')
        await vi.advanceTimersByTimeAsync(10)
        assert.equal(returned.querySelector('.carousel-item.active img').alt, 'Second phone')
        assert.deepEqual(errors, [])
    })
}

test('leaving after a touch clears the delayed carousel restart', async () => {
    // Exercise Bootstrap's touch fallback as on browsers without PointerEvent.
    vi.stubGlobal('PointerEvent', undefined)
    const element = await mountCarousel()
    element.dispatchEvent(new Event('touchend', { bubbles: true }))

    wrapper.unmount()
    wrapper = undefined
    assert.equal(Carousel.getInstance(element), null)
    await vi.advanceTimersByTimeAsync(6000)
    assert.deepEqual(errors, [])
})
