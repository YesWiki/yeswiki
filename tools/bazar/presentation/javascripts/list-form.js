let nodeCreated = 0

new Vue({
  el: '.list-form',
  data: {
    title: '',
    nodes: []
  },
  mounted() {
    const list = JSON.parse(this.$el.dataset.list)
    this.title = list.title
    // vueRef is used to give a unique and fixed ID to each node
    this.nodes = (list.nodes || []).map((n) => ({ ...n, ...{ vueRef: n.id } }))
    if (this.nodes.length === 0) this.addNode()
  },
  methods: {
    addNode() {
      // vueRef is used to give a unique and fixed ID to each node
      this.nodes.push({ vueRef: `@ref${nodeCreated}` })
      nodeCreated += 1
    },
    deleteNode(nodeToDelete) {
      this.nodes = this.nodes.filter((node) => node !== nodeToDelete)
    }
  }
})
