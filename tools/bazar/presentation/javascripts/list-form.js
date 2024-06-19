import ListNode from './list-node.js'

new Vue({
  el: '.list-form',
  components: { 'list-node': ListNode },
  data: {
    title: '',
    rootNode: { id: '@root@', vueRef: '@root@', children: [] },
    allIds: [],
    nodeCreated: 0
  },
  mounted() {
    const list = JSON.parse(this.$el.dataset.list)
    this.title = list.title
    // vueRef is used to give a unique and fixed ID to each node
    this.rootNode.children = (list.nodes || []).map((n) => ({ ...{ vueRef: n.id }, ...n }))
  },
  methods: {
    onSubmit(event) {
      // check for id presence and uniquness
      this.allIds = []
      this.collectIds(this.rootNode)
      if (this.allIds.some((id) => !id)) {
        toastMessage(_t('LIST_ERROR_MISSING_IDS'), 4000, 'alert alert-danger')
        event.preventDefault()
      }
      const duplicatesIds = this.allIds.filter((item, index) => this.allIds.indexOf(item) !== index)
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
    removeVueRefProps(node) {
      delete node.vueRef;
      (node.children || []).forEach((child) => this.removeVueRefProps(child))
      return node
    }
  }
})
