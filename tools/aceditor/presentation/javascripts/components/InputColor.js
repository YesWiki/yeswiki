export default {
  props: [ 'value', 'config' ],
  mounted() {
    $(this.$refs.input).spectrum({
      type: "text"
    }).change((event) => {
      this.$emit('input', event.target.value)
    });
  },
  watch: {
    value(newVal) {
      $(this.$refs.input).spectrum('set', this.value)
    }
  },
  template:  `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <input :value="value" v-on:input="$emit('input', $event.target.value)" class="form-control"
             :required="config.required" :min="config.min" :max="config.max" ref="input"
      />
    </div>
    `
}
