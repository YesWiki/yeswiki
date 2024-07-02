export default {
  props: ['node'],
  template: `
    <div class="node-container" v-show="node.nb > 0">
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
        <FilterNode v-for="childNode, id in node.children" :key="id" :node="childNode" />
      </div>
    </div>
  `
}
