export default {
  props: [ 'value', 'config' ],
  mounted() {
    $(this.$refs.input).iconpicker(pickerConfig).on('iconpickerSelected', (event) => {
      // handle this event with jquery cause vue does not support camelCase event
      this.$emit('input', event.iconpickerValue)
    });
  },
  watch: {
    value(newVal) {
      $(this.$refs.input).data('iconpicker').setValue(this.value)
      $(this.$refs.input).data('iconpicker').update()
    }
  },
  template: `
    <div class="form-group" :class="config.type" :title="config.hint" >
      <label v-if="config.label" class="control-label">{{ config.label || 'Icone' }}</label>
      <div class="input-group">
        <input type="text" :value="value" v-on:input="$emit('input', $event.target.value)"
               class="form-control" ref="input"/>
        <span class="input-group-addon"></span>
      </div>
    </div>
  `
}

const pickerConfig = {
  title: false, // Popover title (optional) only if specified in the template
  selected: false, // use this value as the current item and ignore the original
  defaultValue: false, // use this value as the current item if input or element value is empty
  placement: 'right', // (has some issues with auto and CSS). auto, top, bottom, left, right
  collision: true, // If true, the popover will be repositioned to another position when collapses with the window borders
  animation: true, // fade in/out on show/hide ?
  //hide iconpicker automatically when a value is picked. it is ignored if mustAccept is not false and the accept button is visible
  hideOnSelect: true,
  showFooter: true,
  searchInFooter: true, // If true, the search will be added to the footer instead of the title
  mustAccept: false, // only applicable when there's an iconpicker-btn-accept button in the popover footer
  selectedCustomClass: 'bg-primary', // Appends this class when to the selected item
  // icons: [], // list of icon objects [{title:String, searchTerms:String}]. By default, all Font Awesome icons are included.
  fullClassFormatter: function(val) {
    return val;
  },
  input: 'input,.iconpicker-input', // children input selector
  inputSearch: false, // use the input as a search box too?
  container: false, //  Appends the popover to a specific element. If not set, the selected element or element parent is used
  component: '.input-group-addon,.iconpicker-component', // children component jQuery selector or object, relative to the container element
  // Plugin templates:
  templates: {
    popover: '<div class="iconpicker-popover popover"><div class="arrow"></div>' +
      '<div class="popover-title"></div><div class="popover-content"></div></div>',
    footer: '<div class="popover-footer"></div>',
    buttons: '<button class="iconpicker-btn iconpicker-btn-cancel btn btn-default btn-sm">Annuler</button>' +
      ' <button class="iconpicker-btn iconpicker-btn-accept btn btn-primary btn-sm">Ok</button>',
    search: '<input type="search" class="form-control iconpicker-search" placeholder="Rechercher" />',
    iconpicker: '<div class="iconpicker"><div class="iconpicker-items"></div></div>',
    iconpickerItem: '<a role="button" class="iconpicker-item"><i></i></a>',
  }
};
