// This code come from https://github.com/BinarCode/vue2-transitions

const BaseTransitionMixin = {
  inheritAttrs: false,
  props: {
    /**
     * Transition duration. Number for specifying the same duration for enter/leave transitions
     * Object style {enter: 300, leave: 300} for specifying explicit durations for enter/leave
     */
    duration: {
      type: [Number, Object],
      default: 200
    },
    /**
     * Transition delay. Number for specifying the same delay for enter/leave transitions
     * Object style {enter: 300, leave: 300} for specifying explicit durations for enter/leave
     */
    delay: {
      type: [Number, Object],
      default: 0
    },
    /**
     * Whether the component should be a `transition-group` component.
     */
    group: Boolean,
    /**
     * Transition tag, in case the component is a `transition-group`
     */
    tag: {
      type: String,
      default: 'span'
    },
    /**
     *  Transform origin property https://tympanus.net/codrops/css_reference/transform-origin/.
     *  Can be specified with styles as well but it's shorter with this prop
     */
    origin: {
      type: String,
      default: ''
    },
    /**
     * Element styles that are applied during transition. These styles are applied on @beforeEnter and @beforeLeave hooks
     */
    styles: {
      type: Object,
      default: () => ({
        animationFillMode: 'both',
        animationTimingFunction: 'ease-out'
      })
    }
  },
  computed: {
    componentType() {
      return this.group ? 'transition-group' : 'transition'
    },
    hooks() {
      return {
        ...this.$listeners,
        beforeEnter: this.beforeEnter,
        afterEnter: (el) => {
          this.cleanUpStyles(el)
          this.$emit('after-enter', el)
        },
        beforeLeave: this.beforeLeave,
        leave: this.leave,
        afterLeave: (el) => {
          this.cleanUpStyles(el)
          this.$emit('after-leave', el)
        }
      }
    }
  },
  methods: {
    beforeEnter(el) {
      const enterDuration = this.duration.enter ? this.duration.enter : this.duration
      el.style.animationDuration = `${enterDuration}ms`

      const enterDelay = this.delay.enter ? this.delay.enter : this.delay
      el.style.animationDelay = `${enterDelay}ms`

      this.setStyles(el)
      this.$emit('before-enter', el)
    },
    cleanUpStyles(el) {
      Object.keys(this.styles).forEach((key) => {
        const styleValue = this.styles[key]
        if (styleValue) {
          el.style[key] = ''
        }
      })
      el.style.animationDuration = ''
      el.style.animationDelay = ''
    },
    beforeLeave(el) {
      const leaveDuration = this.duration.leave ? this.duration.leave : this.duration
      el.style.animationDuration = `${leaveDuration}ms`

      const leaveDelay = this.delay.leave ? this.delay.leave : this.delay
      el.style.animationDelay = `${leaveDelay}ms`

      this.setStyles(el)
      this.$emit('before-leave', el)
    },
    leave(el, done) {
      this.setAbsolutePosition(el)
      this.$emit('leave', el, done)
    },
    setStyles(el) {
      this.setTransformOrigin(el)
      Object.keys(this.styles).forEach((key) => {
        const styleValue = this.styles[key]
        if (styleValue) {
          el.style[key] = styleValue
        }
      })
    },
    setAbsolutePosition(el) {
      if (this.group) {
        el.style.position = 'absolute'
      }
      return this
    },
    setTransformOrigin(el) {
      if (this.origin) {
        el.style.transformOrigin = this.origin
      }
      return this
    }
  }
}

export default {
  name: 'collapse-transition',
  mixins: [BaseTransitionMixin],
  methods: {
    transitionStyle(duration = 300) {
      const durationInSeconds = duration / 1000
      const style = `${durationInSeconds}s height ease-in-out, ${durationInSeconds}s padding-top ease-in-out, ${durationInSeconds}s padding-bottom ease-in-out`
      return style
    },
    beforeEnter(el) {
      const enterDuration = this.duration.enter ? this.duration.enter : this.duration
      el.style.transition = this.transitionStyle(enterDuration)
      if (!el.dataset) el.dataset = {}

      el.dataset.oldPaddingTop = el.style.paddingTop
      el.dataset.oldPaddingBottom = el.style.paddingBottom

      el.style.height = '0'
      el.style.paddingTop = 0
      el.style.paddingBottom = 0
      this.setStyles(el)
    },

    enter(el) {
      el.dataset.oldOverflow = el.style.overflow
      if (el.scrollHeight !== 0) {
        el.style.height = `${el.scrollHeight}px`
        el.style.paddingTop = el.dataset.oldPaddingTop
        el.style.paddingBottom = el.dataset.oldPaddingBottom
      } else {
        el.style.height = ''
        el.style.paddingTop = el.dataset.oldPaddingTop
        el.style.paddingBottom = el.dataset.oldPaddingBottom
      }

      el.style.overflow = 'hidden'
    },

    afterEnter(el) {
      // for safari: remove class then reset height is necessary
      el.style.transition = ''
      el.style.height = ''
      el.style.overflow = el.dataset.oldOverflow
    },

    beforeLeave(el) {
      if (!el.dataset) el.dataset = {}
      el.dataset.oldPaddingTop = el.style.paddingTop
      el.dataset.oldPaddingBottom = el.style.paddingBottom
      el.dataset.oldOverflow = el.style.overflow

      el.style.height = `${el.scrollHeight}px`
      el.style.overflow = 'hidden'
      this.setStyles(el)
    },

    leave(el) {
      const leaveDuration = this.duration.leave ? this.duration.leave : this.duration
      if (el.scrollHeight !== 0) {
        // for safari: add class after set height, or it will jump to zero height suddenly, weired
        el.style.transition = this.transitionStyle(leaveDuration)
        el.style.height = 0
        el.style.paddingTop = 0
        el.style.paddingBottom = 0
      }
      // necessary for transition-group
      this.setAbsolutePosition(el)
    },

    afterLeave(el) {
      el.style.transition = ''
      el.style.height = ''
      el.style.overflow = el.dataset.oldOverflow
      el.style.paddingTop = el.dataset.oldPaddingTop
      el.style.paddingBottom = el.dataset.oldPaddingBottom
    }
  },
  template: `
    <component :is="componentType"
            :tag="tag"
            v-bind="$attrs"
            v-on="$listeners"
            @before-enter="beforeEnter"
            @after-enter="afterEnter"
            @enter="enter"
            @before-leave="beforeLeave"
            @leave="leave"
            @after-leave="afterLeave"
            move-class="collapse-move">
      <slot></slot>
    </component>`
}
