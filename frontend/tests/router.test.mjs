import assert from 'node:assert/strict'
import test from 'node:test'
import { createMemoryHistory, createRouter } from 'vue-router'

import { notFoundRoute } from '../src/router/notFoundRoute.js'

test('the catch-all route resolves unknown paths to the 404 page', () => {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [notFoundRoute],
    })

    const resolved = router.resolve('/missing/deep/path')

    assert.equal(resolved.name, 'NotFound')
    assert.deepEqual(resolved.params.pathMatch, ['missing', 'deep', 'path'])
})
