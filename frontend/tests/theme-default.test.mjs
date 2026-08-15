import test from 'node:test'
import assert from 'node:assert/strict'
import { access, readFile } from 'node:fs/promises'
import { constants } from 'node:fs'
import { URL } from 'node:url'

const sharedThemeCss = await readFile(
    new URL('../../resources/css/shared-navigation.css', import.meta.url),
    'utf8',
)

const systemThemeFiles = [
    '../index.html',
    '../../resources/views/layouts/app.blade.php',
    '../../resources/views/layouts/guest.blade.php',
]

const noManualThemeFiles = [
    '../index.html',
    '../../resources/js/app.js',
    '../../resources/views/layouts/app.blade.php',
    '../../resources/views/layouts/guest.blade.php',
    '../../resources/views/layouts/navigation.blade.php',
    '../src/components/NavBar.vue',
    '../src/App.vue',
]

function cssVariable(name) {
    const match = sharedThemeCss.match(new RegExp(`--${name}:\\s*([^;]+);`))

    assert.ok(match, `Missing CSS variable --${name}`)

    return match[1].trim().toLowerCase()
}

test('shared CSS fixes the light primary color and softens it in dark mode', () => {
    assert.equal(cssVariable('app-primary'), '#007bff')
    assert.equal(cssVariable('app-primary-rgb'), '0, 123, 255')
    assert.equal(cssVariable('app-primary-hover'), '#0069d9')
    assert.equal(cssVariable('app-primary-contrast'), '#ffffff')
    assert.match(
        sharedThemeCss,
        /\[data-bs-theme='dark'\]\s*\{[^}]*--app-primary:\s*#3b82c4;[^}]*--app-primary-rgb:\s*59, 130, 196;[^}]*--app-primary-hover:\s*#4c94d3;[^}]*--app-primary-contrast:\s*#111827;/s,
    )
    assert.doesNotMatch(
        sharedThemeCss,
        /data-primary-color|theme-(?:blue|emerald|violet|rose|amber)/,
    )
})

test('runtime follows the system setting without storing or selecting a theme', async () => {
    const systemThemeContents = await Promise.all(
        systemThemeFiles.map((path) => readFile(new URL(path, import.meta.url), 'utf8')),
    )
    const noManualThemeContents = await Promise.all(
        noManualThemeFiles.map((path) => readFile(new URL(path, import.meta.url), 'utf8')),
    )

    for (const content of systemThemeContents) {
        assert.match(content, /prefers-color-scheme: dark/)
        assert.match(content, /addEventListener\('change', applySystemTheme\)/)
    }

    for (const content of noManualThemeContents) {
        assert.doesNotMatch(
            content,
            /smartphone_catalog_theme|primaryColor|data-primary-color|ThemeControl|theme-control/,
        )
    }
})

test('manual theme components and utilities are removed', async () => {
    for (const path of [
        '../src/components/ThemeControl.vue',
        '../src/utils/theme.js',
        '../../resources/views/components/theme-control.blade.php',
    ]) {
        await assert.rejects(access(new URL(path, import.meta.url), constants.F_OK))
    }
})
