import { defineConfig, globalIgnores } from 'eslint/config'
import globals from 'globals'
import js from '@eslint/js'
import prettier from 'eslint-config-prettier'

export default defineConfig([
  globalIgnores([
    '**/vendor/',
    'javascripts/ext-searchbox.js',
    'javascripts/yw-icon-map.js',
    '**/custom/',
  ]),
  {
    extends: [js.configs.recommended, prettier],

    languageOptions: {
      globals: {
        ...globals.browser,
        wiki: 'writable',
        ywInit: 'readable',
        ywInitEach: 'readable',
        ywAssets: 'readable',
        htmx: 'readable',
        Vue: 'readable',
        Sortable: 'readable',
        // Leaflet global (javascripts/vendor/leaflet)
        L: 'readable',
        Vditor: 'readable',
        _t: 'readable',
        ace: 'writable',
        toastMessage: 'readable',
        multiDeleteService: 'readable',
        usersTableService: 'readable',
        // page-level config globals injected by forms_form.twig (form designer)
        groupsList: 'readable',
        formAndListIds: 'readable',
        // page-level config globals injected by theme-selector-with-form.twig
        themeSelectorTranslation: 'readable',
        customCSSPresetsPrefix: 'readable',
        geolocationHelper: 'readable',
        // page-level config global injected by aceditor.twig (the actions palette)
        actionsBuilderData: 'readable',
        // page-level config globals injected by doc.twig
        locale: 'readable',
        baseUrl: 'readable',
        i18n: 'readable',
        extensions: 'readable',
        // vendored libraries with a global entry point (javascripts/vendor/)
        FullCalendar: 'readable',
        opening_hours: 'readable',
        module: 'writable',
        // page-level config globals injected by reactions templates
        blockReactionRemove: 'readable',
        blockReactionRemoveMessage: 'readable',
        Html5Qrcode: 'readable',
        Html5QrcodeSupportedFormats: 'readable',
        // p5.js "global mode" API, used by javascripts/qrcodetroc-visualisation.js
        p5: 'readable',
        resizeCanvas: 'readable',
        createCanvas: 'readable',
        random: 'readable',
        color: 'readable',
        width: 'readable',
        height: 'readable',
        sin: 'readable',
        cos: 'readable',
        noStroke: 'readable',
        noFill: 'readable',
        fill: 'readable',
        stroke: 'readable',
        ellipse: 'readable',
        rect: 'readable',
        line: 'readable',
        dist: 'readable',
        mouseX: 'readable',
        mouseY: 'readable',
        textAlign: 'readable',
        text: 'readable',
        background: 'readable',
        CENTER: 'readable',
        LEFT: 'readable',
      },

      ecmaVersion: 13,
      sourceType: 'module',
    },

    // its `eslint-disable` comments name -- a rule that is off makes every disable of it a
    rules: {
      eqeqeq: ['error', 'smart'],
      'no-empty': ['error', { allowEmptyCatch: true }],
      'no-param-reassign': 'error',
      'no-restricted-globals': ['error', 'event', 'isNaN', 'isFinite'],
      'no-eval': 'error',
      'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    },
  },
])
