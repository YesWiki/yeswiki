export default {
  data() {
    return {
      entry: {}
    }
  },
  methods: {
    displayEntry(entry) {
      this.entry = entry
      this.$root.getEntryRender(entry)
      $('.modal:visible').modal('hide') // if other modals
      $(this.$el).modal('show')
    }
  },
  template: `
    <div class="modal fade" role="dialog">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-body entry-container">
            <div class="btn-close" data-dismiss="modal"><i class="fa fa-times"></i></div>
            <div v-html="entry.html_render"></div>
          </div>
        </div>
      </div>
    </div>
  `
}
