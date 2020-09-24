console.log("data", data)
// data global variable has been defined in bazar-actions-builder.tpl.html
new Vue({
  el: "#bazar-actions-builder-app",
  data: {
    formIds: data.forms,
    selectedFormId: "",
    forms: {},
    selectedForm: null,
    actions: data.actions,
    selectedActionId: "",
    values: {},
    filterGroups: [],
    actionParams: {},
    iconMapping: [],
    editor: null, // aceditor
    isEditingExistingAction: false,
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
    editorCurrLineNumber() {
      return this.editor.selection.getRange().start.row
    },
    init() {
      let line = this.editor.session.getLine(this.editorCurrLineNumber())
      let newValues = {}
      if (line.match(/^\s*\{\{\s*bazar.*/g) != null) {
        this.isEditingExistingAction = true
        const attributes = $(line.replace(/\s*{{\s*/, '<').replace('}}', '/>'))[0].attributes
        for(let attribute of attributes) {
          newValues[attribute.name] = attribute.value
        }
        this.selectedFormId = newValues.id
        for(let actionId in this.actions) {
          let action = this.actions[actionId]
          if (action && action.properties && action.properties.template.value == newValues.template) {
            this.selectedActionId = actionId
          }
        }
        if (newValues.icon) {
          this.iconMapping = []
          newValues.icon.split(',').each(el => {
            this.iconMapping.push({icon: el.split('=')[0], id: el.split('=')[1]})
          })
        }
        if (newValues.groups) {
          this.filterGroups = []
          let groups = newValues.groups.split(',')
          let titles = newValues.titles ? newValues.titles.split(',') : []
          let icons = newValues.groupicons ? newValues.groupicons.split(',') : []
          for(var i = 0; i < groups.length; i++) {
            this.filterGroups.push({
              field: groups[i],
              title: titles.length >= i ? titles[i] : '' ,
              icon: icons.length >= i ? icons[i] : ''
            })
          }
        }
        this.values.groups = this.filterGroups.map(g => g.field).filter(e => e != "").join(',')
        this.values.titles = this.filterGroups.map(g => g.title).filter(e => e != "").join(',')
        this.values.groupicons = this.filterGroups.map(g => g.icon).filter(e => e != "").join(',')
        this.values.icon = this.iconMapping.filter(m => m.id && m.icon).map(m => `${m.icon}=${m.id}`).join(',')

        this.values = newValues
        this.updateActionParams();
      } else {
        this.isEditingExistingAction = false
        this.values = {}
        this.selectedFormId = ''
        this.selectedActionId = ''
      }
    },
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
      $('#bazar-actions-modal').modal('hide')
      if (this.isEditingExistingAction) {
        let line = this.editorCurrLineNumber()
        this.editor.session.replace(new ace.Range(line, 0, line + 1, 0), this.wikiCode + "\n");
        this.editor.gotoLine(line + 1)
      } else {
        this.editor.insert(this.wikiCode)
      }
      this.editor.selection.selectLine()
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
    $(document).ready(() => {
      this.editor = $('textarea#body').data('aceditor');
      var flyingActionBar = $(`<div class="flying-action-bar">
        <a data-toggle="modal" data-target="#bazar-actions-modal" class="aceditor-btn-actions-bazar btn btn-primary btn-icon">
          <i class="fa fa-pencil-alt"></i>
        </a>
      </div>`)
      $('textarea#body').before(flyingActionBar);

      this.editor.selection.on('changeCursor', (event) => {
        console.log(event)
        let line = this.editor.session.getLine(this.editorCurrLineNumber())
        let isBazarLine = line.match(/^\s*\{\{\s*bazar.*/g) != null
        // wait for editor to change cursor
        setTimeout(() => {
          flyingActionBar.toggleClass('active', isBazarLine);
          if (isBazarLine) {
            let top = $('.ace_gutter-active-line').offset().top - $('.ace-editor-container').offset().top + flyingActionBar.height()
            console.log("top", top)
            flyingActionBar.css('top', top + 'px')
          }
        }, 100)
      })

      $('.aceditor-btn-actions-bazar').click(this.init)
    })
    this.addEmptyIconMapping()
    this.getSelectedForm()
    this.initActionValues()
  }
});
