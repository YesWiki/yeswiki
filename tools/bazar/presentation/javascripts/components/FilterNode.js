export default {
  props: ['node'],
  computed: {
    // A parent node should be display if some of it's children has count > 0
    displayNode() {
      return [this.node, ...this.node.descendants].some((node) => node.count > 0)
    },
    someDescendantChecked() {
      return this.node.descendants.some((node) => node.checked)
    }
  },
  methods: {
    onChecked() {
      // uncheck all parents & descendants
      // (this logic is not easy to understand, play with the UI to see how it works)

      // Why unchecking parents?
      // Given this tree France: [ Paris, Bordeaux], Spain: [ Madrid, Barcelone ]
      // Given those entries entryCapitaleFR: Paris|France, entryLyon: France
      // If France is checked, I want both entry to be display
      // If Paris is checked, I want only entryCapitaleFR to be displayed.
      // So when clicking Paris, I need to uncheck France (otherwise both will be displayed)

      // Why unchecking descendants?
      // If Paris is checked, and I check France. All entries from France will be displayed
      // then no need to have Paris checked, it's misleading
      this.node.descendants.forEach((node) => { node.checked = false })
      this.node.parents.forEach((node) => { node.checked = false })
    }
  },
  template: `
    <div class="node-container" v-show="displayNode">
      <div :class="['checkbox', {checked: node.checked, 'some-descendant-checked': someDescendantChecked}]">
        <label>
          <input class="filter-checkbox" type="checkbox"
                 v-model="node.checked" @change="onChecked">
          <span>
            <span class="node-label" v-html="node.label"></span>
            <span class="nb" v-if="node.count">{{ node.count }}</span>
          </span>
        </label>
      </div>
      <div class="children">
        <FilterNode v-for="childNode, id in node.children" :key="id" :node="childNode" ref="children" />
      </div>
    </div>
  `
}
