export default {
  props: ['value', 'config'],
  emits: ['input'],
  mounted() {
    fetch(wiki.url('?api/pages'))
      .then((response) => (response.ok ? response.json() : Promise.reject(response)))
      .then((data) => {
        const pages = []
        for (const key in data) {
          const pageTag = data[key].tag
          if (pageTag) {
            pages.push(pageTag)
          }
        }
        window.ywAutocomplete(this.$refs.input, {
          items: 5,
          source: (query) => pages.filter(
            (page) => page.toLowerCase().includes(query.toLowerCase())
          ),
          onSelect: (item) => {
            this.$refs.input.value = item
            this.$emit('input', item)
          }
        })
      })
      .catch(() => {
        /* no suggestions when the list cannot be fetched */
      })
  },
  watch: {
    value(newVal) {
      this.$emit('input', newVal.replace(/\s+/g, '-'))
    }
  },
  template: `
    <div class="yw-form-group input-group" :class="config.type" :title="config.hint" >
      <addon-icon :config="config" v-if="config.icon"></addon-icon>
      <label v-if="config.label" class="yw-form-label">{{ config.label }}</label>
      <input type="text" autocomplete="off" :value="value" class="yw-input"
             @input="$emit('input', $event.target.value)"
             @blur="$emit('input', $event.target.value)"
             :required="config.required" :min="config.min" :max="config.max" ref="input"
      />
      <input-hint :config="config"></input-hint>
    </div>
    `
}
