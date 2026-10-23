import ChildList from './child-list-translate.js'
const { createApp } = Vue

const { jsonlist, langs, defaultlanguage } = document.getElementById('translate-list').dataset

const app = createApp({
  components: { 'child-list-translate': ChildList },
  data() {
    const extraLangsList = Object.values(JSON.parse(langs))
    const list = JSON.parse(jsonlist)
    list.children = list.nodes
    let extraLangsValues = list.extralang ?? {}

    for (const lang of extraLangsList) {
      if (extraLangsValues[lang]) {
        extraLangsValues = list.extralang[lang] ?? { title: '' }
        if (extraLangsValues.nodes) {
          extraLangsValues.children = extraLangsValues.nodes
        }
      } else {
        extraLangsValues = { title: '' }
      }
      this.addlanginfo(list, lang, extraLangsValues)
    }
    return {
      selectedLanguage: defaultlanguage,
      extraLangsList,
      list,
      jsonList: jsonlist,
      jsonExtraLangs: '',
      extraLangsValues
    }
  },
  watch: {
    list: {
      handler() {
        const filteredlist = { id: this.list.id, title: this.list.title }
        for (const lang of this.extraLangsList) {
          for (const child in this.list.nodes) {
            filteredlist.nodes ??= {}
            const { childdata, langsvalues } = this.filterLangInfo(this.list.nodes[child], lang)
            filteredlist.nodes[child] = childdata
            filteredlist.extralang ??= {}
            filteredlist.extralang[lang] ??= {}
            filteredlist.extralang[lang].nodes ??= {}
            filteredlist.extralang[lang].nodes[child] = langsvalues
          }
          if (this.list[lang]) {
            filteredlist.extralang ??= {}
            filteredlist.extralang[lang] ??= {}
            filteredlist.extralang[lang].title = this.list[lang]
          }
        }

        this.jsonList = JSON.stringify(filteredlist)
      },
      deep: true
    }
  },
  methods: {
    onSubmit(data) {
      return data
    },
    addlanginfo(node, lang, extraLangs) {
      node[lang] = extraLangs.label ?? extraLangs.title
      const children = {}
      const extraLangsChildren = extraLangs.children ?? []
      for (const child in node.children) {
        children[child] = this.addlanginfo(node.children[child],
          lang, extraLangsChildren[child] ?? { title: '' }
        )
      }
      // node.children = children
      // return node
    },
    filterLangInfo(node, lang) {
      const filterednode = { id: node.id, label: node.label }
      const langnode = {}
      if (node[lang]) {
        langnode.label = node[lang]
      }
      for (const child in node.children) {
        const { childdata, langsvalues } = this.filterLangInfo(node.children[child], lang)
        filterednode.children ??= {}
        filterednode.children[child] = childdata
        if (langsvalues) {
          langnode.children ??= {}
          langnode.children[child] = langsvalues
        }
      }
      return { childdata: filterednode, langsvalues: langnode }
    },
    filterData(value) {
      if (typeof value === 'string' || value instanceof String) {
        if (value !== '') {
          return value
        }
        return null
      }
      const data = {}
      for (const [key, child] of Object.entries(value)) {
        const childData = this.filterData(child)
        if (childData) {
          data[key] = childData
        }
      }
      if (Object.keys(data).length === 0) {
        return null
      }
      return data
    }
  }
})
app.mount('#translate-list')
