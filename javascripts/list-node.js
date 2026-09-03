export default {
  name: 'list-node',
  props: ['node', 'depth', 'index'],
  emits: ['delete'],
  data() {
    return {
      expanded: false,
      newNodeLabel: '',
    }
  },
  methods: {
    addChildNode() {
      this.node.children.push({
        label: this.newNodeLabel,
        id: this.slugify(this.newNodeLabel),
        color: '',
        icon: { source: 'sprite', value: '' },
        children: [],
        vueRef: Date.now(),
      })
      this.newNodeLabel = ''
      this.expanded = true
    },
    /** A value carries its own colour and icon, so nothing has to map them per action call. */
    iconOf(node) {
      node.icon ||= { source: 'sprite', value: '' }
      return node.icon
    },
    deleteChildNode(nodeToDelete) {
      this.node.children = this.node.children.filter(
        (node) => node !== nodeToDelete,
      )
    },
    slugify(val) {
      return val
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z^A-Z^_^0-9^{^}]/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_+|_+$/g, '')
        .toLowerCase()
    },
  },
  template: `
    <div class="list-node-container" :data-depth="depth" :data-index="index">
      <div v-if="depth > 0" class="list-node input-prepend input-append input-group">
        <!-- Chevron to expand children -->
        <button type="button" @click="expanded = !expanded" 
                class="btn btn-icon btn-primary input-group-addon btn-expand" >
          <svg class="yw-icon" aria-hidden="true"><use :href="expanded ? 'src/assets/icons.svg#chevron-up' : 'src/assets/icons.svg#chevron-down'"/></svg>
        </button>
        <!-- Label -->
        <input type="text" v-model="node.label" placeholder="${_t('LIST_TEXT')}" 
              class="yw-input" @keydown.enter.prevent />
        <!-- Id -->
        <div class="input-group-addon" >
          ${_t('LIST_KEY')} :
        </div>
        <input type="text" v-model="node.id" placeholder="${_t('LIST_KEY')}" 
              class="yw-input input-id" :class="{ error: !node.id }"
              @keydown.enter.prevent />
        <!-- The colour and the icon this value carries wherever it is drawn (ticket 64) -->
        <input type="color" v-model="node.color" class="yw-input input-color"
               :title="'${_t('LIST_VALUE_COLOR')}'" />
        <select v-model="iconOf(node).source" class="yw-input input-icon-source"
                :title="'${_t('MENU_ENTRY_ICON_SOURCE')}'">
          <option value="sprite">${_t('MENU_ENTRY_ICON_SPRITE')}</option>
          <option value="emoji">${_t('MENU_ENTRY_ICON_EMOJI')}</option>
          <option value="file">${_t('MENU_ENTRY_ICON_FILE')}</option>
        </select>
        <input type="text" v-model="iconOf(node).value" class="yw-input input-icon"
               :placeholder="'${_t('MENU_ENTRY_ICON')}'" @keydown.enter.prevent />
        <!-- Delete Icon -->
        <button type="button" @click="$emit('delete', node)" 
                class="btn btn-danger btn-icon input-group-addon">
          <svg class="yw-icon icon-trash" aria-hidden="true"><use href="src/assets/icons.svg#trash"/></svg>
        </button>
      </div>
      <div v-show="depth == 0 || expanded" class="list-node-children" 
           :class="{root: depth == 0, first: depth == 0 && node.children.length == 0}">
        <draggable v-model="node.children" group="nodes" item-key="vueRef">
          <template #item="{ element: childNode, index }">
            <list-node :node="childNode" :key="childNode.vueRef" @delete="deleteChildNode"
                       :depth="depth + 1" :index="index"></list-node>
          </template>
        </draggable>
        <div class="list-new-node input-group input-prepend">
          <button type="button" @click="addChildNode" 
                  class="btn btn-neutral btn-icon input-group-addon btn-add-child" >
            <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#plus"/></svg>
          </button>
          <input type="text" v-model="newNodeLabel" 
                 :placeholder="depth > 0 ? '${_t('LIST_ADD_CHILD_NODE')}' : '${_t('LIST_ADD_NODE')}'" 
                 class="yw-input" @keydown.enter.prevent="addChildNode" />
          
        </div>
        
      </div>
    </div>
  `,
}
