import { defineConfig, globalIgnores } from 'eslint/config'
import js from '@eslint/js'
import globals from 'globals'
import prettier from 'eslint-config-prettier'

export default defineConfig([
  globalIgnores([
    '**/vendor/',
    '**/custom/',
    'tools/aceditor/presentation/javascripts/ext-searchbox.js',
  ]),
  {
    extends: [js.configs.recommended, prettier],

    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.jquery,
        wiki: 'writable',
        Vue: 'readable',
        _t: 'readable',
        ace: 'writable',
        toastMessage: 'readable',
        multiDeleteService: 'readable',
        usersTableService: 'readable',
        // Leaflet global (javascripts/vendor/leaflet)
        L: 'readable',
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
        // page-level config globals injected by bazar templates
        yesWikiTypes: 'readable',
        bazarlistTagsInputsData: 'readable',
        autocompleteFieldnames: 'readable',
        pageTags: 'readable',
        DATATABLE_OPTIONS: 'readable',
        // page-level config globals injected by reactions templates
        blockReactionRemove: 'readable',
        blockReactionRemoveMessage: 'readable',
        // vendored libraries with a global entry point (javascripts/vendor/)
        FullCalendar: 'readable',
        opening_hours: 'readable',
        formBuilder: 'readable',
        formBuilderFields: 'readable',
        Modernizr: 'readable',
        module: 'writable',
        define: 'readable',
        require: 'readable',
      },

      ecmaVersion: 13,
      sourceType: 'module',
    },

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
