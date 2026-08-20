import js from '@eslint/js'
import eslintConfigPrettier from 'eslint-config-prettier'
import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'

export default [
  {
    ignores: ['node_modules/**'],
  },
  js.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  {
    files: ['eslint.config.js', 'vite.config.js', 'vitest.config.js'],
    languageOptions: {
      globals: globals.node,
    },
  },
  {
    files: ['src/**/*.{js,vue}'],
    languageOptions: {
      ecmaVersion: 'latest',
      globals: globals.browser,
      sourceType: 'module',
    },
    rules: {
      'vue/multi-word-component-names': 'off',
    },
  },
  {
    // Tests run in Node; the component tests add a jsdom DOM per file with a
    // `// @vitest-environment jsdom` docblock, so both global sets apply.
    files: ['tests/**/*.{js,mjs}'],
    languageOptions: {
      ecmaVersion: 'latest',
      globals: {
        ...globals.node,
        ...globals.browser,
      },
      sourceType: 'module',
    },
  },
  eslintConfigPrettier,
]
