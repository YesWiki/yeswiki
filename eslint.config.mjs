import { defineConfig, globalIgnores } from 'eslint/config'
import js from '@eslint/js'
import globals from 'globals'
import prettier from 'eslint-config-prettier'

export default defineConfig([
  globalIgnores([
    '**/vendor/',
    '**/custom/',
    // third-party libraries kept as shipped: their style is not ours to judge
    '**/*.min.js',
    'tools/aceditor/presentation/javascripts/ext-searchbox.js',
    'tools/attach/presentation/javascripts/qq.js',
    'tools/bazar/presentation/javascripts/jquery.photobox.js',
    'tools/bazar/presentation/javascripts/jquery.galleriffic.js',
    'tools/bazar/presentation/javascripts/jquery.colorbox-min.js',
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
        locale: 'writable',
        baseUrl: 'readable',
        i18n: 'readable',
        extensions: 'readable',
        // globals another script on the page defines
        qq: 'readable',
        imagesExtensions: 'readable',
        facettetext: 'readable',
        existingTags: 'readable',
        arrInfoWindows: 'readable',
        arrMarkers: 'readable',
        map: 'readable',
        initialize: 'readable',
        ConditionsChecking: 'readable',
        showPopup: 'writable',
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
        formBuilder: 'writable',
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
      // a script that defines one of the globals declared above is not redeclaring it
      'no-redeclare': ['error', { builtinGlobals: false }],
      'no-restricted-globals': ['error', 'event', 'isNaN', 'isFinite'],
      'no-eval': 'error',
      'no-unused-vars': [
        'error',
        { argsIgnorePattern: '^_', caughtErrorsIgnorePattern: '^_' },
      ],
    },
  },
  // Classic scripts: the browser loads these with a plain <script> tag, so their top-level
  // functions are globals the templates call through onclick and friends, not dead code.
  {
    files: [
      'javascripts/documentation.js',
      'javascripts/favorites.js',
      'javascripts/handlers/duplicate.js',
      'javascripts/multidelete.js',
      'javascripts/users-table.js',
      'javascripts/yeswiki-base.js',
      'javascripts/yeswiki-base-no-defer.js',
      'tools/aceditor/presentation/javascripts/mode-yeswiki.js',
      'tools/attach/presentation/javascripts/pdf-object.js',
      'tools/attach/presentation/javascripts/pointimage.js',
      'tools/bazar/presentation/javascripts/bazar-api-helper.js',
      'tools/bazar/presentation/javascripts/bazar-import.js',
      'tools/bazar/presentation/javascripts/checkcontent.js',
      'tools/bazar/presentation/javascripts/components/BazarTableEntrySelector.js',
      'tools/bazar/presentation/javascripts/components/TimelineYear.js',
      'tools/bazar/presentation/javascripts/fields/opening_hours.js',
      'tools/bazar/presentation/javascripts/forms-import.js',
      'tools/bazar/presentation/javascripts/inputs/checkbox-drag-and-drop.js',
      'tools/bazar/presentation/javascripts/inputs/checkbox-tags.js',
      'tools/bazar/presentation/javascripts/inputs/checkbox-tree.js',
      'tools/bazar/presentation/javascripts/inputs/conditions-checking.js',
      'tools/bazar/presentation/javascripts/inputs/file-field.js',
      'tools/bazar/presentation/javascripts/inputs/image-field.js',
      'tools/bazar/presentation/javascripts/inputs/map-autocomplete.js',
      'tools/bazar/presentation/javascripts/inputs/map-geolocation-helper.js',
      'tools/bazar/presentation/javascripts/inputs/opening_hours.js',
      'tools/bazar/presentation/javascripts/inputs/recurrent-event.js',
      'tools/bazar/presentation/javascripts/inputs/tabs.js',
      'tools/bazar/presentation/javascripts/inputs/user-field-update-email.js',
      'tools/bazar/presentation/javascripts/jquery.colorbox-min.js',
      'tools/bazar/presentation/javascripts/jquery.opacityrollover.js',
      'tools/bazar/presentation/javascripts/list-import.js',
      'tools/bazar/presentation/javascripts/services/recurrence-calculator.js',
      'tools/bazar/presentation/javascripts/tableau.js',
      'tools/bazar/presentation/javascripts/timeline.js',
      'tools/bazar/presentation/javascripts/widget.js',
      'tools/contact/libs/contact.js',
      'tools/helloworld/javascripts/greeting.js',
      'tools/syndication/presentation/javascripts/syndication.js',
      'tools/tags/javascripts/edit-tags.js',
      'tools/tags/libs/exportpages.js',
      'tools/tags/libs/filtertags.js',
      'tools/tags/libs/tag.js',
      'tools/templates/javascripts/change-theme.js',
      'tools/templates/javascripts/reload-gerer-droits.js',
      'tools/templates/javascripts/template-edit.js',
      'tools/templates/presentation/javascripts/preset-sidenav.js',
    ],
    languageOptions: {
      sourceType: 'script',
    },
  },
])
