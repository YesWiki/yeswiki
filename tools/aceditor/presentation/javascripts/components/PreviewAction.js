export default {
  props: [ 'wikiCode' ],
  computed: {
    previewIframeUrl() {
      if (!this.wikiCode) return ""
      let result = `/?root/render&content=${encodeURIComponent(this.wikiCode)}`
      return result
    },
  },
  template: `
    <div class="widget-iframe-container">
      <h3>Aperçu (non clickable)</h3>
      <iframe class="iframe-preview" width="100%" height="350px" frameborder="0" :src="previewIframeUrl"></iframe>
      <div class="iframe-blocker"></div>
    </div>
  `
}
