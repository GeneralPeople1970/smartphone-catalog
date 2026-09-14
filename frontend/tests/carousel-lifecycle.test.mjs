// @vitest-environment jsdom
import assert from 'node:assert/strict'
import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, test, vi } from 'vitest'
import HomepageCarousel from '@/components/HomepageCarousel.vue'

const carousel = vi.hoisted(() => ({ getOrCreateInstance: vi.fn() }))
vi.mock('bootstrap/js/dist/carousel', () => ({ default: carousel }))

const slides = [
    { id: 1, title: 'First phone', image: '/slide-1.png', linkUrl: '/phone/1' },
    { id: 2, title: 'Second phone', image: '/slide-2.png', linkUrl: 'javascript:alert(1)' },
]
let wrapper
let instances

beforeEach(() => {
    instances = []
    carousel.getOrCreateInstance.mockImplementation(() => {
        const instance = { pause: vi.fn(), dispose: vi.fn() }
        instances.push(instance)
        return instance
    })
})

afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
    vi.resetAllMocks()
})

test('loading multiple slides starts a carousel and leaving the page disposes it', async () => {
    wrapper = mount(HomepageCarousel)
    assert.equal(carousel.getOrCreateInstance.mock.calls.length, 0)
    await wrapper.setProps({ images: slides })
    assert.equal(carousel.getOrCreateInstance.mock.calls.length, 1)
    assert.equal(wrapper.findAll('.carousel-item').length, 2)
    assert.equal(wrapper.get('.carousel-item a').attributes('href'), '/phone/1')
    assert.equal(wrapper.findAll('.carousel-item a').length, 1)

    wrapper.unmount()
    wrapper = undefined
    assert.equal(instances[0].pause.mock.calls.length, 1)
    assert.equal(instances[0].dispose.mock.calls.length, 1)
})

test('removing slides releases the old instance and a single slide needs no timer', async () => {
    wrapper = mount(HomepageCarousel, { props: { images: slides } })
    await wrapper.vm.$nextTick()
    assert.equal(instances.length, 1)

    await wrapper.setProps({ images: [slides[0]] })
    assert.equal(instances[0].dispose.mock.calls.length, 1)
    assert.equal(instances.length, 1)
    assert.equal(wrapper.find('.carousel-control-next').exists(), false)

    await wrapper.setProps({ images: [] })
    assert.equal(wrapper.find('.carousel').exists(), false)
})
