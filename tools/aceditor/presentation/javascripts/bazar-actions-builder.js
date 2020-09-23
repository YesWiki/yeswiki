console.log("data", data)
// data global variable has been defined in bazar-actions-builder.tpl.html
new Vue({
  el: "#bazar-actions-builder-app",
  data: {
    formIds: data.forms,
    selectedFormId: "1",
    forms: {},
    selectedForm: null,
    actions: data.actions,
    selectedActionId: "bazarcarto",
    showFiltersConfig: true,
    filtersProperties: ['groups', 'groupsexpanded', 'titles', 'groupicons', 'filterposition', 'filtercolsize'],
    values: {},
    filterGroups: []
  },
  computed: {
    selectedAction() {
      return this.actions[this.selectedActionId]
    },
    actionParams() {
      let result = {}
      this.values.id = this.selectedFormId
      this.values.groups = this.filterGroups.map(g => g.field).filter(e => e != "").join(',')
      this.values.titles = this.filterGroups.map(g => g.title).filter(e => e != "").join(',')
      this.values.groupicons = this.filterGroups.map(g => g.icon).filter(e => e != "").join(',')
      for(var key in this.values) {
        if (!this.showFiltersConfig && this.filtersProperties.includes(key)) continue;
        let value = this.values[key]
        if (value === true) value = 1
        else if (value === false || value === "") continue
        result[key] = value
      }
      return result
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
      var result = `{{ bazarliste`
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
      range.selectNode(this.$refs.wikiCode);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
    },
    copyContent() {
      this.selectFullText();
      document.execCommand('copy');
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
    },
    addEmptyFilterGroup() {
      this.filterGroups.push({field: '', title: '', icon: ''})
    },
    removeFilterGroup(group) {
      this.filterGroups = this.filterGroups.filter( el => el.field != group.field)
    }
  },
  watch: {
    selectedFormId: this.getSelectedForm,
    selectedActionId: this.initActionValues
  },
  mounted() {
    this.addEmptyFilterGroup()
    this.getSelectedForm()
    this.initActionValues()
  }
});
