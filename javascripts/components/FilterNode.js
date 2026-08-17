import CollapseTransition from '../shared-components/CollapseTransition.js'

const FilterNode = {
  name: 'FilterNode',
  props: ['node'],
  components: { CollapseTransition },
  data: () => ({ expanded: false }),
  computed: {
    displayNode() {
      return [this.node, ...this.node.descendants].some(
        (node) => node.count > 0,
      )
    },
    someDescendantChecked() {
      return this.node.descendants.some((node) => node.checked)
    },
    nodeClasses() {
      return {
        checked: this.node.checked,
        'some-descendant-checked': this.someDescendantChecked,
        expanded: this.expanded,
      }
    },
    nodeTitle() {
      const vLanguage =
        document.documentElement.getAttribute('lang') ?? undefined
      let vLabel

      if (vLanguage) {
        const holder = document.createElement('span')
        holder.innerHTML = this.node.label
        vLabel = Array.from(holder.querySelectorAll(`[lang=${vLanguage}]`))
          .map((el) => el.textContent)
          .join('')
      } else vLabel = this.node.label

      return vLabel
    },
  },
  methods: {
    onChecked() {
      this.node.descendants.forEach((node) => {
        node.checked = false
      })
      this.node.parents.forEach((node) => {
        node.checked = false
      })
    },
    labelClicked(event) {
      if (this.node.children.length > 0) event.preventDefault()
      this.expanded = !this.expanded
    },
  },
  template: `
    <div class="filter-node-container" v-show="displayNode">
      <label :class="['filter-node', nodeClasses]">
        <input type="checkbox" v-model="node.checked" @change="onChecked">

        <!-- Those two spans are needed, the first one contains both the 
              label + the checkbox drawn with css with :after and :before pseudo element. 
              We want the behaviour to differ depending on where the user clicks 
            (checkbox itself or label) -->
        <span>
          <span class="filter-node-label-wrapper" @click="labelClicked"> 
            <span class="filter-node-label">
              <span v-html="node.label" :title="nodeTitle"></span>
              <i v-if="node.children.length > 0" class="chevron-icon fa fa-caret-down"></i>
            </span>
            <span class="count" v-if="node.count"><span>{{ node.count }}</span></span>
          </span>
        </span>
      </label>
      
      <collapse-transition>
        <div v-if="expanded" class="children">
          <FilterNode v-for="childNode, id in node.children" :key="id" :node="childNode" />
        </div>
      </collapse-transition>
    </div>
  `,
}
FilterNode.components.FilterNode = FilterNode
export default FilterNode
