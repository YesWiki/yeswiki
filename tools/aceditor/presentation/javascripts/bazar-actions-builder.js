console.log("data", data)
// data global variable has been defined in bazar-actions-builder.tpl.html
new Vue({
  el: "#bazar-actions-builder-app",
  data: {
    forms: data.forms,
    lists: data.lists,
    actions: data.actions,
    formId: "",
    selectedActionId: "",
    values: {}
  },
  computed: {
    selectedAction() {
      return this.actions[this.selectedActionId]
    },
    wikiCode() {
      var result = `{{ ${this.selectedActionId} id="${this.formId}" `
      for(var key in this.values) result += `${key}="${this.values[key]}" `
      result += '}}'
      return result
    }
  },
  methods: {
    selectFullText() {
      var range = document.createRange();
      range.selectNode(this.$refs.wikiCode);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
    },
    copyContent() {
      this.selectFullText();
      document.execCommand('copy');
    }
  },
  watch: {
    selectedActionId(newVal) {
      // Populate the values field from the config
      for(var propName in this.selectedAction.properties) {
        var configValue = this.selectedAction.properties[propName].value
        if (configValue && !this.values[propName]) this.values[propName] = configValue
      }
    }
  }
});
