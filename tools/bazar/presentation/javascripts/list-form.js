import ListNode from './list-node.js'

new Vue({
  el: '.list-form',
  components: { 'list-node': ListNode },
  data: {
    title: '',
    rootNode: { children: [] },
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
      // TODO: check for id uniquness
    }
  }
})
