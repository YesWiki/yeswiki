export default {
  name: 'list-node',
  props: ['node', 'depth', 'index'],
  emits: ['delete'],
  data() {
    return {
      expanded: false,
      newNodeLabel: ''
    }
  },
  methods: {
    addChildNode() {
      // vueRef is used to give a unique and fixed ID to each node
      this.node.children.push({
        label: this.newNodeLabel,
        id: this.slugify(this.newNodeLabel),
        children: [],
        vueRef: Date.now()
      })
      this.newNodeLabel = ''
      this.expanded = true
    },
    deleteChildNode(nodeToDelete) {
      this.node.children = this.node.children.filter((node) => node !== nodeToDelete)
    },
    slugify(val) {
      return val.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z^A-Z^_^0-9^{^}]/g, '_') // "test !" => "test__"
        .replace(/_+/g, '_') // "te__st" => "te_st"
        .replace(/^_+|_+$/g, '') // "___test__" => "test"
        .toLowerCase()
    }
  },
  template: `
    <div class="list-node-container" :data-depth="depth" :data-index="index">
      <div v-if="depth > 0" class="list-node input-prepend input-append input-group">
        <!-- Chevron to expand children -->
        <button type="button" @click="expanded = !expanded" 
                class="btn btn-icon btn-primary input-group-addon btn-expand" >
          <i :class="expanded ? 'fa fa-chevron-up' : 'fa fa-chevron-down'"></i>
        </button>
        <!-- Label -->
        <input type="text" v-model="node.label" placeholder="${_t('LIST_TEXT')}" 
              class="form-control" @keydown.enter.prevent />
        <!-- Id -->
        <div class="input-group-addon" >
          ${_t('LIST_KEY')} :
        </div>
        <input type="text" v-model="node.id" placeholder="${_t('LIST_KEY')}" 
              class="form-control input-id" :class="{ error: !node.id }"
              @keydown.enter.prevent />
        <!-- Delete Icon -->
        <button type="button" @click="$emit('delete', node)" 
                class="btn btn-danger btn-icon input-group-addon">
          <i class="fa fa-trash icon-trash"></i>
        </button>
      </div>
      <div v-show="depth == 0 || expanded" class="list-node-children" 
           :class="{root: depth == 0, first: depth == 0 && node.children.length == 0}">
        <draggable v-model="node.children" group="nodes">
          <list-node v-for="(childNode, index) in node.children" :key="childNode.vueRef" 
                     :node="childNode" @delete="deleteChildNode" :depth="depth + 1" 
                     :index="index"></list-node>
        </draggable>
        <div class="list-new-node input-group input-prepend">
          <button type="button" @click="addChildNode" 
                  class="btn btn-neutral btn-icon input-group-addon btn-add-child" >
            <i class="fa fa-plus"></i>
          </button>
          <input type="text" v-model="newNodeLabel" 
                 :placeholder="depth > 0 ? '${_t('LIST_ADD_CHILD_NODE')}' : '${_t('LIST_ADD_NODE')}'" 
                 class="form-control" @keydown.enter.prevent="addChildNode" />
          
        </div>
        
      </div>
    </div>
  `
}
