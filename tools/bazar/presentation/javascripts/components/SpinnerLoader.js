export default {
  props: ['height'],
  computed: {
    spinnerHeight() {
      return (this.height || 200) + 'px'
    }
  },
  template: `
    <div class="spinner-loader" :style="{height: spinnerHeight}">
      <i class="fas fa-4x fa-circle-notch fa-spin"></i>
    </div>
  `
}