import FilePickerPanel from '../file-picker-panel.js'

let filePicker = null

/** Every file this wiki holds, fetched once for the whole page -- and only ever for the legacy case below. */
let allFiles = null
const files = () =>
  (allFiles ??= fetch(wiki.url('api/files'))
    .then((response) => (response.ok ? response.json() : []))
    .then((entries) => (Array.isArray(entries) ? entries : []))
    .catch(() => []))

/** A picture, chosen through the file manager and then shown. */
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
      if (this.value !== wanted) return
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
        onPick: ({ tag }) => this.$emit('input', tag),
      })
    },
    clear() {
      this.$emit('input', '')
    },
  },
  // `wiki` is a page global rather than something on the Vue instance, so its strings are
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
