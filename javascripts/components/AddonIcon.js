import { legacyIconToSprite } from '../yw-icon-map.js'

/**
 * The little icon beside a setting's input.
 *
 * It used to emit `<i class="fa fa-{name}">`, which stopped drawing anything when
 * FontAwesome left core (ADR-0004) -- and an empty `<i>` inside a bordered
 * `.input-group-addon` is not nothing on screen, it is a short horizontal rule sitting
 * where an icon should be. Half the settings rail was wearing one.
 *
 * Renders the sprite instead, and renders *nothing at all* when the name maps to no
 * symbol: an icon nobody can see is worth less than the space it takes, and a missing one
 * must not leave a box behind to be mistaken for a divider.
 */
export default {
  props: ['config'],
  computed: {
    svg() {
      return legacyIconToSprite(this.config?.icon) || ''
    },
  },
  template: `
    <span class="input-group-addon addon-icon" v-if="svg" v-html="svg"></span>
  `,
}
