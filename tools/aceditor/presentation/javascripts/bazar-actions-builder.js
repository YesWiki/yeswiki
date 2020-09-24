console.log("data", data)
// data global variable has been defined in bazar-actions-builder.tpl.html
new Vue({
  el: "#bazar-actions-builder-app",
  data: {
    formIds: data.forms,
    selectedFormId: "2",
    forms: {},
    selectedForm: null,
    actions: data.actions,
    selectedActionId: "bazarcarto",
    filtersProperties: ['groups', 'groupsexpanded', 'titles', 'groupicons', 'filterposition', 'filtercolsize'],
    values: { iconfield: 'checkboxListeCategories' },
    filterGroups: [],
    actionParams: {},
    iconMapping: []
  },
  computed: {
    selectedAction() {
      return this.actions[this.selectedActionId]
    },
    previewIframeUrl() {
      if (!this.selectedFormId || !this.selectedActionId) return ""
      let result = '/?BazaR/iframe'
      for(var key in this.actionParams) {
        result += `&${key}=${encodeURIComponent(this.actionParams[key])}`
      }
      return result
    },
    wikiCode() {
      var result = `{{bazarliste`
      for(var key in this.actionParams) {
        result += ` ${key}="${this.actionParams[key]}"`
      }
      result += ' }}'
      return result
    },
    iconOptions() {
      if (!this.selectedForm || !this.values.iconfield) return []
      var config = this.selectedForm.prepared.filter(e => e.id == this.values.iconfield)[0]
      return config ? config.values.label : []
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
    },
    insertCodeInEditor() {
      $('textarea#body').surroundSelectedText(this.wikiCode, '')
      $('#bazar-actions-modal').modal('hide')
    },
    getSelectedForm() {
      if (!this.selectedFormId) return;
      if (this.forms[this.selectedFormId]) this.selectedForm = this.forms[this.selectedFormId]
      else {
        $.getJSON(`/?root/bazar_api&object=form&id=${this.selectedFormId}`, data => {
          this.forms[this.selectedFormId] = data
          this.selectedForm = data
        })
      }
    },
    initActionValues() {
      if (!this.selectedActionId) return;
      // Populate the values field from the config
      for(var propName in this.selectedAction.properties) {
        var configValue = this.selectedAction.properties[propName].value
        if (configValue && !this.values[propName]) this.values[propName] = configValue
      }
      this.values.template = this.selectedAction.properties.template.value
      this.updateActionParams()
    },
    addEmptyFilterGroup() {
      this.filterGroups.push({field: '', title: '', icon: ''})
    },
    removeFilterGroup(group) {
      this.filterGroups = this.filterGroups.filter( el => el.field != group.field)
    },
    updateActionParams() {
      let result = {}
      this.values.id = this.selectedFormId
      this.values.groups = this.filterGroups.map(g => g.field).filter(e => e != "").join(',')
      this.values.titles = this.filterGroups.map(g => g.title).filter(e => e != "").join(',')
      this.values.groupicons = this.filterGroups.map(g => g.icon).filter(e => e != "").join(',')
      this.values.icon = this.iconMapping.filter(m => m.id && m.icon).map(m => `${m.icon}=${m.id}`).join(',')
      for(var key in this.values) {
        let value = this.values[key]
        if (value === false || value === "") continue
        result[key] = value
      }
      this.actionParams = result
    },
    addEmptyIconMapping() {
      this.iconMapping.push({id: '', icon: ''})
    },
    removeIconMapping(mapping) {
      this.iconMapping = this.iconMapping.filter(el => el.id != mapping.id)
    }
  },
  watch: {
    selectedFormId: function() { this.getSelectedForm() },
    selectedActionId: function() { this.initActionValues() },
    values: {
      handler(val){ this.updateActionParams(val) },
      deep: true
    },
    filterGroups: {
      handler(val){ this.updateActionParams(val) },
      deep: true
    },
    iconMapping: {
      handler(val){ this.updateActionParams(val) },
      deep: true
    },
  },
  mounted() {
    this.addEmptyIconMapping()
    this.getSelectedForm()
    this.initActionValues()
  }
});
