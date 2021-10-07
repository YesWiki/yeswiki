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

  var bazarTagsInputService = new BazarTagsInputService();
  bazarTagsInputService.init();
});