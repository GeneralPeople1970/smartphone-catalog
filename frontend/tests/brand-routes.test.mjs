// @vitest-environment jsdom
import assert from 'node:assert/strict'
import { test } from 'vitest'
import router from '@/router/index.js'
import { getBrandByRouteName } from '@/constants/brands.js'
import brands from '../../resources/data/brands.json'

test('every brand path in the shared catalog resolves to the same brand', () => {
    for (const brand of brands) {
        const route = router.resolve(brand.path)
        const resolvedBrand = getBrandByRouteName(route.name)
        assert.equal(resolvedBrand.code, brand.code)
        assert.equal(resolvedBrand.displayName, brand.displayName)
    }
})

test('the legacy Lenovo routes retain their names and canonical brand', () => {
    for (const path of ['/LIANXIANG', '/LENOVO_XIAOXIN']) {
        const route = router.resolve(path)
        assert.equal(route.name, `${path.slice(1)}List`)
        assert.equal(getBrandByRouteName(route.name).code, 'LENOVO')
    }
})
