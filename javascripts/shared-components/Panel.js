import CollapseTransition from './CollapseTransition.js'

export default {
  props: {
    color: {
      type: String,
      default: 'default'
    },
    collapsable: {
      type: Boolean,
      default: true
    },
    collapsed: {
      type: Boolean,
      default: true
    }
  },
  components: { CollapseTransition },
  data() {
    return {
      // value to work internally, name should not conflict with prop
      internalCollapsed: true
    }
  },
  computed: {
    panelClass() {
      const color = this.color || 'default'
      return `panel-${color}`
    }
  },
  methods: {
    headerClicked() {
      this.internalCollapsed = !this.internalCollapsed
      this.$emit('update:collapsed', this.internalCollapsed)
      if (!this.internalCollapsed) this.$emit('opened')
    }
  },
  beforeMount() {
    this.internalCollapsed = this.collapsed
  },
  template: `
    <div class="panel" :class="[panelClass, {collapsed: internalCollapsed}]">
      <button class="panel-heading" :class="{collapsed: internalCollapsed}"
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
  `
}
