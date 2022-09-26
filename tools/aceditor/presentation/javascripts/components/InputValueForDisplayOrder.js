export default {
    props: [ 'value', 'config', 'values', 'elementKey' ],
    computed: {
        active: function(){
            let active = (this.values.hasOwnProperty("displayorder") &&
            typeof this.values.displayorder[this.elementKey] != "undefined" &&
            !['pages','logpages'].includes(this.values.displayorder[this.elementKey].type));
            return active;
        }
    },
    mounted() {
      if (!this.value && this.config.value) this.$emit('input', this.config.value)
    },
    template: `
      <div class="form-group input-group" :class="config.type" :title="config.hint" v-if="active">
        <addon-icon :config="config" v-if="config.icon"></addon-icon>  
        <label v-if="config.label" class="control-label">{{ config.label }}</label>
        <input :type="config.type" :value="value"
               v-on:input="$emit('input', $event.target.value)" class="form-control"
               :required="config.required" :min="config.min" :max="config.max" ref="input"
        />
        <input-hint :config="config"></input-hint>
      </div>
      `
}
  