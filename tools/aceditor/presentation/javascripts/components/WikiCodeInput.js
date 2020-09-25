export default {
  props: [ 'isEditing', 'editor', 'wikiCode' ],
  methods: {
    selectFullText() {
      var range = document.createRange();
      range.selectNode(this.$refs.input);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
    },
    copyContent() {
      this.selectFullText();
      document.execCommand('copy');
    },
    insertCodeInEditor() {
      $('#actions-builder-modal').modal('hide')
      if (this.isEditing) {
        this.editor.replaceCurrentLineBy(this.wikiCode)
      } else {
        this.editor.insert(this.wikiCode)
      }
    }
  },
  template: `
    <div class="input-group">
      <div class="input-group-addon btn btn-primary" @click="insertCodeInEditor">
        {{ isEditing ? 'Mettre à jour le code' : 'Insérer dans la page' }}
      </div>
      <input type="text" class="result form-control" @click="selectFullText" :value="wikiCode" ref="input">
      <div class="input-group-addon btn btn-default" @click="copyContent">Copier</div>
    </div>
  `
}
