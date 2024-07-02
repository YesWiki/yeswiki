export default {
  props: ['node'],
  data: () => ({ expanded: false }),
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
    },
    labelClicked(event) {
      // if has childrne, then cliking expand them, and do not trigger the checkbox
      if (this.node.children.length > 0) event.preventDefault()
      this.expanded = !this.expanded
    }
  },
  template: `
    <div class="node-container">
      <div :class="['checkbox', {checked: node.checked, 'some-descendant-checked': someDescendantChecked}]">
        <label>
          <input class="filter-checkbox" type="checkbox"
                 v-model="node.checked" @change="onChecked">

          <!-- Those two spans are needed, the first one contains both the 
               label + the checkboxed drawn with css with :after and :before pseudo element. 
               We want the behaviour to differ depending on where the user clicks -->
          <span>
            <span @click="labelClicked"> 
              <span class="node-label">
                <span v-html="node.label"></span>
                <i v-if="node.children.length > 0" 
                   class="chevron-icon fa" 
                   :class="expanded ? 'fa-caret-up' : 'fa-caret-down' "></i>
              </span>
              <span class="count" v-if="node.count"><span>{{ node.count }}</span></span>
            </span>
          </span>
        </label>
      </div>
      <div v-if="expanded" class="children">
        <FilterNode v-for="childNode, id in node.children" :key="id" :node="childNode" ref="children" />
      </div>
    </div>
  `
}
