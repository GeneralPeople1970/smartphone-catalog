import { defineConfig, mergeConfig } from 'vitest/config'

import viteConfig from './vite.config.js'

// Reuse the app's own Vite config (the Vue SFC plugin and the "@" alias) so
// component tests resolve and compile imports exactly like the built app does.
export default mergeConfig(
  viteConfig({ mode: 'test', command: 'serve' }),
  defineConfig({
    test: {
      include: ['tests/**/*.test.mjs'],
      // Node is the default on purpose: the pure-function security tests
      // install their own minimal `window` stub (tests/image-url-safety.test.mjs)
      // and a real jsdom window would change what they exercise. Component
      // tests opt into a DOM per file with `// @vitest-environment jsdom`.
      environment: 'node',
    },
  }),
)
