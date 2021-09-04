export default {
  props: [ 'value', 'config' ],
  computed: {
    pageList() {
      var baseUrl = actionsBuilderData.baseUrl;
      $.ajax({
        url: baseUrl + `root/json&demand=pages`,
        async: true,
        dataType: "json",
        type: 'GET',
        cache: true,
        success: data => {
          let pages = [];
          for (var key in data) {
            let pageTag = data[key].tag;
            if (pageTag){
              pages.push(pageTag);
            }
          }
          // remove previous typeahead and refresh source
          $(this.$refs.input).typeahead('destroy');
          $(this.$refs.input).typeahead({ source: pages, items: 5});
        }
      })
      return []
    }
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label }}</label>
      <input type="text" autocomplete="off" :value="value" class="form-control"
            data-provide="typeahead" data-items="5" :data-source='pageList'
             v-on:input="$emit('input', $event.target.value)" 
             :required="config.required" :min="config.min" :max="config.max" ref="input"
      />
      <input-hint :config="config"></input-hint>
    </div>
    `
}
