/**
 * Which of this wiki's forms a list is pointed at -- one of them, or several.
 *
 * The forms are not part of the setting: the rail is handed every form the wiki has
 * (`actionsBuilderData.forms`), so a component that carried the list would carry the same
 * list again for every other component. What a component declares is that one of its
 * settings IS the form -- and this reads the list from the app.
 *
 * `id="3,7"` lists two forms at once and always could; a single select could not say so, so
 * the only way to ask for it was to type the parameter by hand -- and the hint under the
 * box said exactly that, which is a hint admitting the control is not the whole control.
 * The picker is the whole control now, and the entries of every form picked are merged into
 * one list (BazarListService), their fields offered together in every field setting, and
 * `form_id` becomes available as a field to group or colour by.
 */
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
    /**
     * The forms named by the parameter, as option objects -- and setting it writes them
     * back as the comma-separated list the action reads.
     *
     * A computed with a setter rather than local state: what the control shows is the
     * parameter, and a copy of it here would be a second place for the truth to live (the
     * mapping inputs have that shape and it is what makes them need re-parsing).
     */
    picked: {
      get() {
        const ids = String(this.value ?? '')
          .split(',')
          .map((id) => id.trim())
          .filter(Boolean)

        return this.options.filter((option) => ids.includes(option.id))
      },
      set(chosen) {
        // written in the order the forms are offered in, which is the order the chips are
        // drawn in: the getter re-derives them from the value, so keeping the click order
        // in the parameter would show a list that does not match what it says
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
