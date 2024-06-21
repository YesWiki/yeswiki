export default {
  props: ['node'],
  data() {
    return { mounted: false }
  },
  computed: {
    // A parent node should be display if some of it's children has nb > 0
    displayNode() {
      this.mounted // force recalculate this cmputed prop when mounted
      if (this.node.nb > 0) {
        return true
      }
      if (this.$refs.children) {
        return this.$refs.children.some((childComponent) => childComponent.displayNode)
      }
      return false
    }
  },
  mounted() {
    this.mounted = true
  },
  template: `
    <div class="node-container" v-show="displayNode">
      <div class="checkbox">
        <label>
          <input class="filter-checkbox" type="checkbox"
                v-model="node.checked">
          <span>
            <span v-html="node.label"></span>
            <span class="nb" v-if="node.nb">({{ node.nb }})</span>
          </span>
        </label>
      </div>
      <div class="children">
        <FilterNode v-for="childNode, id in node.children" :key="id" :node="childNode" ref="children" />
      </div>
    </div>
  `
}
