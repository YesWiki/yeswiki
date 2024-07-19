module.exports = {
  env: {
    browser: true,
    es2015: true,
    jquery: true
  },
  globals: {
    wiki: 'writable',
    Vue: 'readable',
    _t: 'readable',
    ace: 'writable',
    toastMessage: 'readable'
  },
  extends: [
    'airbnb-base'
  ],
  parserOptions: {
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
    'no-new': 'off'
  },
  ignorePatterns: [
    'vendor/',
    'custom/',
    '!/javascripts',
    'javascripts/vendor',
    '!/styles',
    '!/tools',
    '/tools/*',
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
    '!tools/toc',
    'tools/aceditor/presentation/javascripts/ext-searchbox.js'
  ]
}
