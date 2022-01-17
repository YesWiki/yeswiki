export default {
  props: [ 'value', 'config' ],
  data() {
    return {
      palette: [
        ["#1a89a0","#d8604c","#d78958","#4e5056","#87898e","#f2f2f2","#3cab3b","#d8604c"],
        ["#f4cccc","#fce5cd","#fff2cc","#d9ead3","#d0e0e3","#cfe2f3","#d9d2e9","#ead1dc"],
        ["#ea9999","#f9cb9c","#ffe599","#b6d7a8","#a2c4c9","#9fc5e8","#b4a7d6","#d5a6bd"],
        ["#e06666","#f6b26b","#ffd966","#93c47d","#76a5af","#6fa8dc","#8e7cc3","#c27ba0"],
        ["#cc0000","#e69138","#f1c232","#6aa84f","#45818e","#3d85c6","#674ea7","#a64d79"],
        ["#990000","#b45f06","#bf9000","#38761d","#134f5c","#0b5394","#351c75","#741b47"],
        ["#660000","#783f04","#7f6000","#274e13","#0c343d","#073763","#20124d","#4c1130"],
        ["#000000","#444444","#5b5b5b","#999999","#bcbcbc","#eeeeee","#f3f6f4","#ffffff"],
        ["#cb3626","#7e3200","#bD720e","#1e8065","#009a9C","#00537e","#8e36a5","#ab0061"],
        ["#f44336","#744700","#ce7e00","#8fce00","#2986cc","#16537e","#6a329f","#c90076"],
      ]
    }
  },
  mounted() {
    $(this.$refs.input).spectrum({
      type: "component",
      palette: this.palette,
      selectionPalette: []
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
      <input-hint :config="config"></input-hint>
    </div>
    `
}
