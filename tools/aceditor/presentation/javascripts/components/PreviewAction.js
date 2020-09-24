export default {
  props: [ 'actionParams' ],
  computed: {
    previewIframeUrl() {
      if (!this.actionParams) return ""
      let result = '/?BazaR/iframe'
      for(var key in this.actionParams) {
        result += `&${key}=${encodeURIComponent(this.actionParams[key])}`
      }
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
