export default {
  props: ['height'],
  computed: {
    spinnerHeight() {
      return `${this.height || 200}px`
    }
  },
  template: `
    <div class="spinner-loader" :style="{height: spinnerHeight}">
      <svg class="yw-icon yw-icon--2x yw-icon--spin" aria-hidden="true"><use href="src/assets/icons.svg#loader-2"/></svg>
    </div>
  `
}
