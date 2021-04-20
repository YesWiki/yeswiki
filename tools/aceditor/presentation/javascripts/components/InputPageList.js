export default {
  props: [ 'value', 'config' ],
  data() {
    return {importedPages: null}
  },
  computed: {
    pageList() {
      if (!this.importedPages){
        $.ajax({
          url: location.origin + location.pathname + `?root/json&demand=pages`,
          async: false,
          dataType: "json",
          type: 'GET',
          cache: true,
          success: data => {
          let pages = [];
          for (var key in data) {
            let pageTag = data[key].tag;
            if (pageTag){
              pages.push('"'+pageTag+'"');
            }
          }
          this.importedPages = "["+pages.toString()+"]" ;
        }})
      }
      return this.importedPages
    }
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <input type="text" autocomplete="off" :value="value"
            data-provide="typeahead" data-items="5" :data-source='pageList'
             v-on:input="$emit('input', $event.target.value)" class="form-control"
             v-on:focus="$emit('input', $event.target.value)"
             v-on:blur="$emit('input', $event.target.value)"
             v-on:select="$emit('input', $event.target.value)"
             :required="config.required" :min="config.min" :max="config.max" ref="input"
      />
      <input-hint :config="config"></input-hint>
    </div>
    `
}
