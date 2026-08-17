/** Which of this wiki's forms a list is pointed at -- one of them, or several. */
export default {
  props: ['value', 'config'],
  emits: ['input'],
  computed: {
    /** id => label, as the wiki handed them over. */
    forms() {
      return this.$root.formIds || {}
    },
    options() {
      return Object.entries(this.forms).map(([id, label]) => ({
        id: String(id),
        label: String(label),
      }))
    },
    /** The forms named by the parameter, as option objects -- and setting it writes them back as the comma-separated list the action reads. */
    picked: {
      get() {
        const ids = String(this.value ?? '')
          .split(',')
          .map((id) => id.trim())
          .filter(Boolean)

        return this.options.filter((option) => ids.includes(option.id))
      },
      set(chosen) {
        const chosenIds = (chosen || []).map((option) => option.id)
        this.$emit(
          'input',
          this.options
            .filter((option) => chosenIds.includes(option.id))
            .map((option) => option.id)
            .join(','),
        )
      },
    },
  },
  template: `
    <div class="yw-form-group input-group" :class="config.type" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      <v-select v-model="picked" :options="options" label="label" :multiple="true"
                :close-on-select="false">
      </v-select>
      <input-hint :config="config"></input-hint>
    </div>
    `,
}
