import CollapseTransition from './CollapseTransition.js'

export default {
  props: {
    color: {
      type: String,
      default: 'default',
    },
    collapsable: {
      type: Boolean,
      default: true,
    },
    collapsed: {
      type: Boolean,
      default: true,
    },
    dropdown: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['update:collapsed', 'opened'],
  components: { CollapseTransition },
  data() {
    return {
      internalCollapsed: true,
    }
  },
  computed: {
    panelClass() {
      const color = this.color || 'default'
      return `panel-${color}`
    },
  },
  methods: {
    headerClicked() {
      this.internalCollapsed = !this.internalCollapsed
      this.$emit('update:collapsed', this.internalCollapsed)
      if (!this.internalCollapsed) this.$emit('opened')
    },
    close() {
      if (this.internalCollapsed) return
      this.internalCollapsed = true
      this.$emit('update:collapsed', true)
    },
    closeFromOutside(event) {
      if (!this.$el.contains(event.target)) this.close()
    },
    closeOnEscape(event) {
      if (event.key === 'Escape') this.close()
    },
  },
  beforeMount() {
    this.internalCollapsed = this.collapsed
  },
  mounted() {
    if (!this.dropdown) return
    document.addEventListener('click', this.closeFromOutside)
    this.$el.addEventListener('keydown', this.closeOnEscape)
  },
  beforeUnmount() {
    document.removeEventListener('click', this.closeFromOutside)
  },
  template: `
    <div class="panel" :class="[panelClass, {collapsed: internalCollapsed, 'panel--dropdown': dropdown}]">
      <button type="button" class="panel-heading" :class="{collapsed: internalCollapsed}"
           :aria-expanded="collapsable ? String(!internalCollapsed) : null"
           :data-toggle="collapsable ? 'collapse' : ''"
           @click="headerClicked()">
        <slot name="header"></slot>
      </button>
      <collapse-transition>
        <div class="panel-body" v-show="!internalCollapsed" style="padding: 0">
          <slot name="body"></slot>
        </div>
      </collapse-transition>
    </div>
  `,
}
