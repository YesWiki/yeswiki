import { defineConfig, globalIgnores } from 'eslint/config'
import globals from 'globals'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import js from '@eslint/js'
import { FlatCompat } from '@eslint/eslintrc'

const __filename = fileURLToPath(import.meta.url)
const __dirname = path.dirname(__filename)
const compat = new FlatCompat({
  baseDirectory: __dirname,
  recommendedConfig: js.configs.recommended,
  allConfig: js.configs.all
})

export default defineConfig([globalIgnores([
  '**/vendor/',
  '**/custom/',
  '!javascripts',
  'javascripts/vendor',
  '!styles',
  '!tools',
  'tools/*',
  '!tools/autoupdate',
  '!tools/aceditor',
  '!tools/attach',
  '!tools/bazar',
  '!tools/contact',
  '!tools/helloworld',
  '!tools/lang',
  '!tools/login',
  '!tools/progressbar',
  '!tools/rss',
  '!tools/security',
  '!tools/syndication',
  '!tools/tableau',
  '!tools/tags',
  '!tools/templates',
  'tools/aceditor/presentation/javascripts/ext-searchbox.js'
]), {
  extends: compat.extends('airbnb-base'),

  languageOptions: {
    globals: {
      ...globals.browser,
      ...globals.jquery,
      wiki: 'writable',
      Vue: 'readable',
      _t: 'readable',
      ace: 'writable',
      toastMessage: 'readable'
    },

    ecmaVersion: 13,
    sourceType: 'module'
  },

  rules: {
    semi: ['error', 'never'],

    'max-len': ['error', { code: 104 }],

    'vars-on-top': 'off',
    'class-methods-use-this': 'off',
    'import/no-unresolved': 'off',
    'import/extensions': ['error', 'always'],
    'import/prefer-default-export': ['off'],
    'no-use-before-define': ['off'],
    eqeqeq: ['error', 'smart'],
    'comma-dangle': ['error', 'never'],

    'object-curly-newline': ['error', { multiline: true }],

    'func-names': ['error', 'never'],
    'space-before-function-paren': ['error', 'never'],

    'lines-between-class-members': ['error', 'always', { exceptAfterSingleLine: true }],

    'no-new': 'off',
    'no-restricted-syntax': 'off',
    'guard-for-in': 'off'
  }
}])
