export default {
  props: [ 'actionParams', 'isEditing', 'editor' ],
  computed: {
    wikiCode() {
      var result = `{{bazarliste`
      for(var key in this.actionParams) {
        result += ` ${key}="${this.actionParams[key]}"`
      }
      result += ' }}'
      return result
    }
  },
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
      $('#bazar-actions-modal').modal('hide')
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
