// @vitest-environment jsdom
import assert from 'node:assert/strict'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, test, vi } from 'vitest'

// The username chip is the single front/back switch: signed out it points at
// Laravel's /login, signed in it jumps to the backend /dashboard. The logout
// button next to it posts to Laravel's /logout. The backend top bar
// (resources/views/layouts/navigation.blade.php) is the other half: same two
// controls in the same order, with the chip pointing back at "/".
import NavBar from '@/components/NavBar.vue'

const api = vi.hoisted(() => ({
    getCurrentUser: vi.fn(),
}))

vi.mock('@/services/phoneApi.js', () => api)

const routerLinkStub = { template: '<a><slot /></a>' }

let wrapper

function mountNavBar() {
    return mount(NavBar, {
        global: {
            mocks: { $route: { name: 'Home' } },
            stubs: { RouterLink: routerLinkStub, 'router-link': routerLinkStub },
        },
    })
}

function logoutForms() {
    return wrapper.findAll('form')
}

beforeEach(() => {
    delete window.__SMARTPHONE_CATALOG_AUTH__
    api.getCurrentUser.mockResolvedValue({ authenticated: false, user: null, csrfToken: null })
})

afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
    delete window.__SMARTPHONE_CATALOG_AUTH__
    vi.restoreAllMocks()
    vi.resetAllMocks()
})

test('guests see a register/login chip pointing at the Laravel login page', async () => {
    wrapper = mountNavBar()
    await flushPromises()

    const chips = wrapper.findAll('a.shared-user-chip')
    // One for the desktop top bar, one inside the collapsed mobile menu.
    assert.equal(chips.length, 2)

    for (const chip of chips) {
        assert.equal(chip.attributes('href'), '/login')
        assert.equal(chip.text(), '注册/登录')
    }

    // Nothing to log out of yet.
    assert.equal(logoutForms().length, 0)
})

test('signed-in users see a chip that switches to the backend dashboard', async () => {
    const user = { name: '小明' }
    window.__SMARTPHONE_CATALOG_AUTH__ = { authenticated: true, user, csrfToken: 'boot-token' }
    api.getCurrentUser.mockResolvedValue({ authenticated: true, user, csrfToken: 'boot-token' })

    wrapper = mountNavBar()
    await flushPromises()

    const chips = wrapper.findAll('a.shared-user-chip')
    assert.equal(chips.length, 2)

    for (const chip of chips) {
        assert.equal(chip.attributes('href'), '/dashboard')
        assert.equal(chip.attributes('title'), '前往后台控制台')
        assert.equal(chip.text(), '小明')
    }
})

test('signed-in users get a logout form in the top bar and the mobile menu', async () => {
    const user = { name: '小明' }
    window.__SMARTPHONE_CATALOG_AUTH__ = { authenticated: true, user, csrfToken: 'boot-token' }
    api.getCurrentUser.mockResolvedValue({ authenticated: true, user, csrfToken: 'fresh-token' })

    wrapper = mountNavBar()
    await flushPromises()

    const forms = logoutForms()
    // Mirrors the two chips: desktop top bar plus the collapsed mobile menu.
    assert.equal(forms.length, 2)

    for (const form of forms) {
        assert.equal(form.attributes('method'), 'POST')
        assert.equal(form.attributes('action'), '/logout')
        // The token is refreshed from /api/me, so a stale bootstrap payload
        // cannot leave the form posting a dead token.
        assert.equal(form.find('input[name="_token"]').attributes('value'), 'fresh-token')
        assert.equal(form.find('button.shared-nav-logout').text(), '退出登录')
    }
})

test('the logout form stays hidden while no CSRF token is known', async () => {
    const user = { name: '小明' }
    window.__SMARTPHONE_CATALOG_AUTH__ = { authenticated: true, user }
    api.getCurrentUser.mockResolvedValue({ authenticated: true, user })

    wrapper = mountNavBar()
    await flushPromises()

    // Chip still works (it is a plain link); a token-less form would only 419.
    assert.equal(wrapper.findAll('a.shared-user-chip').length, 2)
    assert.equal(logoutForms().length, 0)
})

test('a stale bootstrap payload is dropped when /api/me reports a guest', async () => {
    window.__SMARTPHONE_CATALOG_AUTH__ = {
        authenticated: true,
        user: { name: '小明' },
        csrfToken: 'boot-token',
    }

    wrapper = mountNavBar()
    await flushPromises()

    for (const chip of wrapper.findAll('a.shared-user-chip')) {
        assert.equal(chip.attributes('href'), '/login')
        assert.equal(chip.text(), '注册/登录')
    }

    assert.equal(logoutForms().length, 0)
})
