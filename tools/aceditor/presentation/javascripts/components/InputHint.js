export default {
  props: ['config'],
  template: `
    <div>
      <div class="hint" v-if="config.hint">{{ config.hint }}</div>
      <div class="hint" v-if="config.doclink"><a target="_blank" :href="config.doclink">`+wiki.lang['ACTION_BUILDER_ONLINEDOC']+`</a></div>
    </div>
  `
}