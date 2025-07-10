import ListNode from './list-node.js'

new Vue({
  el: '.list-form',
  components: { 'list-node': ListNode },
  data: {
    title: '',
    rootNode: {
      id: '@root@',
      vueRef: '@root@',
      children: []
    },
    allIds: [],
    nodeCreated: 0,
    selected_language: 'default',
    extra_langs: {},
    current_title: '',
    extra_langs_list: [],
    extra_langs_titles: {}
  },
  mounted() {
    const list = JSON.parse(this.$el.dataset.list)
    this.title = list.title
    const nodes = list.nodes || []
    const extraLangs = list.__extra_lang || []
    this.extra_langs_list = JSON.parse(this.$el.dataset.extra_lang)
    for (const lang in extraLangs) {
      this.extra_langs_titles[lang] = extraLangs[lang].title ?? ''
      extraLangs[lang].children = extraLangs[lang].nodes
      delete extraLangs[lang].nodes
    }
    this.extra_langs = extraLangs
    nodes.forEach((node) => this.addVueRefProp(node))
    for (const index in nodes) {
      for (const lang in this.extra_langs) {
        if (this.extra_langs[lang].children
          && this.extra_langs[lang].children[index]) {
          this.MergeTranslations(
            nodes[index],
            lang,
            this.extra_langs[lang].children[index]
          )
        }
      }
    }
    this.rootNode.children = nodes
  },
  computed: {
    jsonNodes() {
      const data = this.rootNode.children.map((child) => this.removeVueRefProps({ ...child }))

      return JSON.stringify(data)
    },
    jsonExtraLangs() {
      const extraLang = {}
      let hasData = false
      for (const lang of this.extra_langs_list) {
        extraLang[lang] = {}
        if (this.extra_langs_titles[lang]) {
          extraLang[lang].title = this.extra_langs_titles[lang]
          hasData = true
        }
        const nodes = this.getExtraLang(this.rootNode, lang)
        if (nodes) {
          extraLang[lang].nodes = nodes.children
          hasData = true
        }
        if (!hasData) {
          delete extraLang[lang]
        }
      }

      return JSON.stringify(extraLang)
    }
  },
  methods: {
    getExtraLang(child, lang) {
      const node = {}
      let empty = true
      if (child[lang]) {
        empty = false
        node.label = child[lang]
      }
      if (child.children && child.children.length > 0) {
        node.children = {}
        for (const index in child.children) {
          const subnode = this.getExtraLang(child.children[index], lang)
          if (subnode != null) {
            node.children[index] = subnode
            empty = false
          }
        }
      }
      if (!empty) {
        return node
      }
      return null
    },
    onSubmit(event) {
      document.getElementById('listTitle').disabled = false
      // check for id presence and uniquness
      this.allIds = []
      this.collectIds(this.rootNode)
      if (this.allIds.some((id) => !id)) {
        toastMessage(_t('LIST_ERROR_MISSING_IDS'), 4000, 'alert alert-danger')
        event.preventDefault()
      }
      const duplicatesIds = this.allIds.filter(
        (item, index) => this.allIds.indexOf(item) !== index
      )
      if (duplicatesIds.length > 0) {
        toastMessage(
          _t('LIST_ERROR_DUPLICATES_IDS') + duplicatesIds.join(', '),
          8000,
          'alert alert-danger'
        )
        event.preventDefault()
      }
    },
    collectIds(node) {
      this.allIds.push(node.id)
      node.children.forEach((childNode) => this.collectIds(childNode))
    },
    onLangChange() {
      if (this.selected_language !== 'default') {
        if (!this.extra_langs[this.selected_language]) {
          this.extra_langs[this.selected_language] = {}
        }
        this.current_title = this.extra_langs_titles[this.selected_language]
      }
    },
    addVueRefProp(node) {
      node.vueRef = node.id
      node.children ||= []
      node.children.forEach((childNode) => this.addVueRefProp(childNode))
    },
    removeVueRefProps(node) {
      delete node.vueRef
      //remove extra_lang values
      for (const lang of this.extra_langs_list) {
        delete node[lang]
      }
      node.children = node.children.map((child) =>
        this.removeVueRefProps({ ...child })
      )
      return node
    },
    MergeTranslations(node, lang, translations) {
      if (translations.label) {
        node[lang] = translations.label
      }
      for (const index in node.children) {
        if (translations.children[index] && translations.children[index]) {
          this.MergeTranslations(node.children[index], lang, translations.children[index])
        }
      }
    }
  }
})
