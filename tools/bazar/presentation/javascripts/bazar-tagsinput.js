// Jquery needed
// tools/tags/libs/vendor/bootstrap-tagsinput.min.js needed
$(document).ready(function () {
  function BazarTagsInputService() {
    this.init = function() {
      if (typeof bazarlistTagsInputsData === "undefined" || bazarlistTagsInputsData.length < 1) return null;
      let propertiesNames = Object.keys(bazarlistTagsInputsData);
      propertiesNames.forEach(propertyName => {
        let existingTags = bazarlistTagsInputsData[propertyName].existingTags;
        let existingTagsArray = Object.values(existingTags);
        let limit = bazarlistTagsInputsData[propertyName].limit ?? 0;
        let selectedOptions = bazarlistTagsInputsData[propertyName].selectedOptions;

        let anchor = $('#formulaire .yeswiki-input-entries'+propertyName);
        if (anchor.length == 0){
          console.log('#formulaire .yeswiki-input-entries'+propertyName+' NOT FOUND in bazar-tagsinput.js !');
        } else {
          let options = {
            itemValue: 'id',
            itemText: 'title',
            typeahead: {
                afterSelect: function(val) { anchor.tagsinput('input').val(""); },
                source: existingTagsArray,
                autoSelect: false,
            },
            freeInput: false,
            confirmKeys: [13, 186, 188]
          };
          if (limit === 1){
            options = {
              ...options,
              ...{
                maxTags: 1
              }
            };
          }
          anchor.tagsinput(options);
          selectedOptions.forEach(selectedOption => {
            anchor.tagsinput('add', existingTags[selectedOption]);
          });
        }
      });
    };
  }

  var BazarTagsInputRefresh = {
    bazarTagsInputService: {},
    bazarlistTagsInputsData: {},
    getFormId: function(){
      let input = $('input[name=id_typeannonce]').first();
      return input ? input.val() : null;
    },
    refresh: function (element){
      let parent = this;
      let propertyName = $(element).data('property-name');
      let formId = this.getFormId();
      let concernedInput = $(`input[name=${propertyName}]`);
      if (propertyName && formId && concernedInput) {
        $.get(
          wiki.url('api/forms/'+formId),
          function (data){
            if (data.prepared) {
              let fields = (typeof data.prepared == 'object') 
                ? Object.values(data.prepared) 
                : data.prepared;
              fields.forEach(field => {
                if (field.propertyname && field.propertyname == propertyName){
                  let options = field.options;
                  if (options){
                    // get current
                    let currentOption = concernedInput.tagsinput('items');
                    // reset tagsinput
                    concernedInput.tagsinput('destroy');
                    let existingTags = new Object();
                    for (let key in options) {
                      existingTags[key] = {
                        id: key,
                        title: options[key],
                      }
                    }
                    let previousbazarlistTagsInputsData = {};
                    for (let key in parent.bazarlistTagsInputsData) {
                      previousbazarlistTagsInputsData[key] = parent.bazarlistTagsInputsData[key]
                      delete parent.bazarlistTagsInputsData[key];
                    }
                    parent.bazarlistTagsInputsData[propertyName] = {
                      existingTags: existingTags,
                      limit: 1,
                      selectedOptions: currentOption[0] ? ( currentOption[0].id ? [currentOption[0].id] : []) : [],
                    };
                    // add tagsinput
                    parent.bazarTagsInputService.init();
                    // reset bazarlistTagsInputsData
                    for (let key in previousbazarlistTagsInputsData) {
                      parent.bazarlistTagsInputsData[key] = previousbazarlistTagsInputsData[key] ;
                    }
                  }
                }
              });
            }
          }
        )
      }
    },
    init: function(bazarTagsInputService,bazarlistTagsInputsData){
      let parent = this;
      this.bazarTagsInputService = bazarTagsInputService;
      this.bazarlistTagsInputsData = bazarlistTagsInputsData;
      $('.tagsinput-refresh').each(function (){
        $(this).click(function(){
          parent.refresh(this);
        });
      });
    },
  }

  var bazarTagsInputService = new BazarTagsInputService();
  bazarTagsInputService.init();

  BazarTagsInputRefresh.init(bazarTagsInputService,bazarlistTagsInputsData ?? {});
});