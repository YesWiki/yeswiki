import InputHelper from './InputHelper.js'

const OPERATORS = ['=', '!=', '<', '>', '<=', '>=']

/** One node of the visual query tree: a group with an AND/OR connector holding conditions and nested groups. */
const QueryGroup = {
  name: 'QueryGroup',
  mixins: [InputHelper],
  props: ['group', 'selectedForms', 'values', 'depth'],
  data() {
    return { operators: OPERATORS }
  },
  computed: {
    extraFields() {
      return ['form_id', 'created_at', 'updated_at']
    },
    availableFields() {
      return this.getFieldsFormSelectedForms(
        this.selectedForms,
        this.extraFields,
      )
    },
  },
  methods: {
    isGroup(node) {
      return (
        node !== null &&
        typeof node === 'object' &&
        Array.isArray(node.children)
      )
    },
    addCondition() {
      this.group.children.push({ field: '', operator: '=', value: '' })
    },
    addGroup() {
      this.group.children.push({
        connector: 'AND',
        children: [{ field: '', operator: '=', value: '' }],
      })
    },
    removeChild(index) {
      this.group.children.splice(index, 1)
    },
    valueOptions(fieldId) {
      const field = this.availableFields.find((f) => f.id === fieldId)
      return field &&
        typeof field.options === 'object' &&
        field.options !== null
        ? field.options
        : null
    },
  },
  template: `
    <div class="query-group" :class="depth ? 'query-group--nested' : 'query-group--root'">
      <div v-if="group.children.length > 1" class="query-group__header">
        <svg class="yw-icon query-group__icon" aria-hidden="true"><use href="src/assets/icons.svg#filter"/></svg>
        <select class="form-control qb-input query-group__connector" v-model="group.connector">
          <option value="AND">{{ _t('ACTION_BUILDER_QUERY_AND') }}</option>
          <option value="OR">{{ _t('ACTION_BUILDER_QUERY_OR') }}</option>
        </select>
      </div>
      <div class="query-group__body">
        <template v-for="(child, index) in group.children" :key="index">
          <div v-if="isGroup(child)" class="query-group__nested">
            <button type="button" class="query-group__remove" @click="removeChild(index)">
              <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#x"/></svg>
            </button>
            <query-group :group="child" :selected-forms="selectedForms"
                         :values="values" :depth="(depth || 0) + 1"></query-group>
          </div>
          <div v-else class="query-builder__condition">
            <div class="qb-control query-builder__field">
              <select class="form-control qb-input" v-model="child.field">
                <option value=""></option>
                <template v-for="f in availableFields" :key="f.id">
                  <option v-if="f.label" :value="f.id">{{ f.label }}</option>
                </template>
              </select>
              <label class="qb-label">{{ _t('ACTION_BUILDER_QUERY_FIELD') }}</label>
            </div>
            <div class="qb-control query-builder__operator">
              <select class="form-control qb-input" v-model="child.operator">
                <option v-for="op in operators" :key="op" :value="op">{{ op }}</option>
              </select>
            </div>
            <div class="qb-control query-builder__value">
              <select v-if="valueOptions(child.field)" class="form-control qb-input" v-model="child.value">
                <option value=""></option>
                <option v-for="(label, val) in valueOptions(child.field)" :key="val" :value="val">{{ label }}</option>
              </select>
              <input v-else class="form-control qb-input" type="text" v-model="child.value" />
              <label class="qb-label">{{ _t('ACTION_BUILDER_QUERY_VALUE') }}</label>
            </div>
            <button type="button" class="query-builder__remove" @click="removeChild(index)">
              <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#x"/></svg>
            </button>
          </div>
        </template>
      </div>
      <div class="query-group__actions">
        <button type="button" @click="addCondition" class="btn btn-info btn-sm btn-icon">
          <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#plus"/></svg>
          {{ _t('ACTION_BUILDER_QUERY_ADD_CONDITION') }}
        </button>
        <button type="button" @click="addGroup" class="btn btn-default btn-sm btn-icon">
          <svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#plus"/></svg>
          {{ _t('ACTION_BUILDER_QUERY_ADD_GROUP') }}
        </button>
      </div>
    </div>`,
}

QueryGroup.components = { 'query-group': QueryGroup }

export default QueryGroup
