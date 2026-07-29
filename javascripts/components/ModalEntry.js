import SpinnerLoader from './SpinnerLoader.js'

export default {
  components: { SpinnerLoader },
  data() {
    return { entry: {} }
  },
  methods: {
    displayEntry(entry) {
      this.entry = entry
      this.$root.getEntryRender(entry)
      // if other modals are open, close them first
      document.querySelectorAll('.yw-modal--open').forEach((modal) => {
        modal.classList.remove('yw-modal--open')
      })
      this.$el.classList.add('yw-modal--open')
    }
  },
  template: `
    <div class="yw-modal" role="dialog">
      <div class="yw-modal__dialog">
        <div class="yw-modal__content">
          <div class="yw-modal__body entry-container">
            <div class="btn-close" data-yw-dismiss="modal"><svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#x"/></svg></div>
            <div v-if="entry.html_render" v-html="entry.html_render"></div>
            <div v-else>
              <spinner-loader height="500"></spinner-loader>
            </div>
          </div>
        </div>
      </div>
    </div>
  `
}
