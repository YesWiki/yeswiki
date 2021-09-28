export default {
  props: [ 'isEditing', 'editor', 'wikiCode' ],
  methods: {
    selectFullText() {
      var range = $(this.$refs.input);
      range.select();
    },
    copyContent() {
      this.selectFullText();
      document.execCommand('copy');
    },
    insertCodeInEditor() {
      $('#actions-builder-modal').modal('hide')
      if (this.isEditing) {
        this.editor.replaceCurrentActionBy(this.wikiCode)
      } else {
        this.editor.insert(this.wikiCode)
      }
    }
  },
  template: `
    <div class="input-group">
      <div class="input-group-addon btn btn-primary" @click="insertCodeInEditor">
        {{ isEditing ? '`+wiki.lang['ACTION_BUILDER_UPDATE_CODE']+`' : '`+wiki.lang['ACTION_BUILDER_INSERT_CODE']+`' }}
      </div>
      <input type="text" class="result form-control" @click="selectFullText" :value="wikiCode" ref="input">
      <div class="input-group-addon btn btn-default" @click="copyContent">`+wiki.lang['ACTION_BUILDER_COPY']+`</div>
    </div>
  `
}
