export default {
  props: ['wikiCode', 'height'],
  computed: {
    previewIframeUrl() {
      if (!this.wikiCode) return ''
      const result = wiki.url('wiki/render', { content: this.wikiCode })
      return result
    }
  },
  template: `
    <div class="widget-iframe-container" v-if="height != '0'">
      <h3>${wiki.lang.ACTION_BUILDER_PREVIEW}</h3>
      <iframe class="iframe-preview" width="100%" :height="height || '350px'" frameborder="0" :src="previewIframeUrl"></iframe>
      <div class="iframe-blocker"></div>
    </div>
  `
}
