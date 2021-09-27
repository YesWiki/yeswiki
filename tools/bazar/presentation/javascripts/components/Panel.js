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
    },
  },
  data() {
    return {
      internalCollapsed: true // value to work internally, name should not conflict with prop
    }
  },
  computed: {
    panelClass() {
      let color = this.color || 'default'
      return `panel-${color}`
    }
  },
  methods: {
    headerClicked() {
      this.internalCollapsed = !this.internalCollapsed
      this.$emit('update:collapsed', this.internalCollapsed)
      if (!this.internalCollapsed) this.$emit('opened')
    },    
  },
  mounted() {
    this.internalCollapsed = this.collapsed
  },
  template: `
    <div class="panel" :class="[panelClass, {collapsed: internalCollapsed}]">
      <div class="panel-heading" :class="{collapsed: internalCollapsed}"
           :data-toggle="collapsable ? 'collapse' : ''"
           @click="headerClicked()">
        <slot name="header"></slot>
      </div>
      <div class="panel-body" v-show="!internalCollapsed" style="padding: 0">
        <slot name="body"></slot>
      </div>
      </div>
    </div>
  `
}