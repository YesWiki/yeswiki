export default {
  name: 'child-list-translate',
  props: ['node', 'depth', 'selectedlanguage', 'langs'],
  data() {
    return {
      expanded: false,
    }
  },
  template: `
    <div class="list-node-container" :data-depth="depth">
      <div class="list-node input-prepend input-append input-group" v-if="depth>0">
        <!-- Chevron to expand children -->
        <button type="button" @click="expanded = !expanded"
                class="btn btn-icon btn-primary input-group-addon btn-expand" >
          <i :class="expanded ? 'fa fa-chevron-up' : 'fa fa-chevron-down'"></i>
        </button>
        <!-- Label -->
        <input type="text" v-model="node.label" placeholder="${_t('LIST_TEXT')}"  disabled
              class="form-control" />
        <template v-for="lang in langs">
          <input  v-model="node[lang]" type="text" class="form-control extra_lang_input"
          placeholder="{{ _t('BAZ_TRANSLATION') }}" v-if="selectedlanguage === lang"/>
        </template>
      </div>
      <div v-show="depth == 0 || expanded" class="list-node-children">
          <template v-for="child, key in node.children">
            <child-list-translate :node="child" :selectedlanguage="selectedlanguage"
            :langs="langs" :depth="depth + 1">
            </child-list-translate>
          </template>
      </div>
    </div>
  `
}
