import { legacyIconToSprite } from '../yw-icon-map.js'

/** The little icon beside a setting's input. */
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
