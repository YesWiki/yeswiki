import FilePickerPanel from '../file-picker-panel.js'

// One picker for the document, built on first use -- the same instance the editor toolbars
// use, since it binds to the single #YesWikiFilePickerPanel the page carries.
let filePicker = null

/**
 * Every file this wiki holds, fetched once for the whole page -- and only ever for the
 * legacy case below.
 *
 * A setting stores the picture's **tag**, which is what an action resolves: `{{attach}}`
 * loads the row for it and serves the bytes through the ACL-checked download route, and
 * `{{section}}` does the same since ticket 36. Storing the original filename instead put a
 * name in the tag that no action could resolve -- the page said `paramètre "file"
 * obligatoire` and the editor previewed nothing.
 *
 * A tag needs no lookup to be shown: the download route is built from it. The listing is
 * for the other direction -- a page written before any of this holds a legacy *filename*,
 * and this is how one is recognised and shown rather than left as a broken picture.
 */
let allFiles = null
const files = () =>
  (allFiles ??= fetch(wiki.url('api/files'))
    .then((response) => (response.ok ? response.json() : []))
    .then((entries) => (Array.isArray(entries) ? entries : []))
    .catch(() => []))

/**
 * A picture, chosen through the file manager and then shown.
 *
 * The setting it replaces was a text field whose hint had to explain the rule: type the name
 * of an image already uploaded to this page, or the name of one that is not there yet to
 * make its upload button appear. That is a filename someone has to remember and spell, for
 * a picture they can see in the file manager two clicks away.
 *
 * There is no text field here at all. Empty, it is the one button that fills it; full, it is
 * the picture and the one control that empties it. Changing one's mind is removing it and
 * choosing again, which is one more click than a "change" button and one less thing on
 * screen for the whole time nobody is changing their mind.
 */
export default {
  props: ['value', 'config'],
  emits: ['input'],
  data() {
    return { url: '' }
  },
  computed: {
    hasPicker() {
      return document.getElementById('YesWikiFilePickerPanel') !== null
    },
  },
  watch: {
    value: { immediate: true, handler: 'resolveUrl' },
  },
  methods: {
    /** Where the picture named by `value` is served from, or '' if nothing matches it. */
    async resolveUrl() {
      if (!this.value) {
        this.url = ''

        return
      }
      const wanted = this.value
      const entries = await files()
      // the value may have changed again while the listing was in flight
      if (this.value !== wanted) return
      // a tag is the normal case; a filename is what a page written before ticket 36 holds
      const entry =
        entries.find((file) => file.tag === wanted) ??
        entries.find((file) => file.original_filename === wanted)
      this.url = entry
        ? wiki.url(`api/files/${encodeURIComponent(entry.tag)}/download`)
        : ''
    },
    choose() {
      filePicker ??= new FilePickerPanel()
      filePicker.open({
        only: 'image',
        // the TAG, not the filename: it is what an action resolves, and what the
        // ACL-checked download route is built from
        onPick: ({ tag }) => this.$emit('input', tag),
      })
    },
    clear() {
      this.$emit('input', '')
    },
  },
  // `wiki` is a page global rather than something on the Vue instance, so its strings are
  // interpolated into the template here, at module load -- the same way InputHint does it.
  // Referencing `wiki` inside the template throws during render instead.
  template: `
    <div class="yw-form-group input-group image" :title="config.hint">
      <addon-icon :config="config" v-if="config.icon"></addon-icon>
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      <div class="input-image">
        <button type="button" class="yw-btn yw-btn--sm" @click="choose"
                v-if="!value && hasPicker">${wiki.lang.CHOOSE_AN_IMAGE}</button>
        <template v-if="value">
          <img class="input-image__thumb" v-if="url" :src="url" :alt="value" :title="value" />
          <span class="input-image__name" v-else v-text="value"></span>
          <button type="button" class="yw-btn yw-btn--sm input-image__remove" @click="clear"
                  title="${wiki.lang.REMOVE_IMAGE}">&times;</button>
        </template>
      </div>
      <input-hint :config="config"></input-hint>
    </div>`,
}
