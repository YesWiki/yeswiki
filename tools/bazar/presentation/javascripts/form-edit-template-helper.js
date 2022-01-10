// function to render help for tabs and tabchange
const templateHelper = {
    cache: {},
    holders:{},
    ids:{},
    formFields:{},
    getFormField: function (fieldId) {
      if (!this.formFields.hasOwnProperty(fieldId)){
        let formField = $(`.field-${fieldId}`).closest("li.form-field")
        var newFormField = {};
        newFormField[fieldId] = (formField.length == 0) ? false : formField;
        this.formFields = {...this.formFields,...newFormField};
      }
      return this.formFields[fieldId];
    },
    getHolder: function (field) {
      let fieldId = field.id;
      if (!this.holders.hasOwnProperty(fieldId)){
        let formField = this.getFormField(fieldId);
        var newHolder = {};
        var newId = {};
        let id = false;
        if (formField){
          id = $(formField).attr('id');
          let anchor = $(`#${id}-holder`);
          if (typeof anchor === "undefined" 
              || anchor.length == 0) {
                newHolder[fieldId] = false;
                newId[fieldId] = false;
          } else {
            newHolder[fieldId] = anchor.first();
            newId[fieldId] = id;
          }
        } else {
          newHolder[fieldId] = false;
          newId[fieldId] = false;
        }
        this.holders = {...this.holders,...newHolder};
        this.ids = {...this.ids,...newId};
      }
      return this.holders[fieldId];
    },
    getId: function (field) {
      let fieldId = field.id;
      if (!this.ids.hasOwnProperty(fieldId)){
        this.getHolder(field);
      }
      return this.ids[fieldId];
    },
    prependHint: function (field,message){
      let holder = this.getHolder(field);
      if (holder){
        if (!holder.hasClass("hint-already-defined")){
          let formElements = holder.find('.form-elements').first();
          let helpMsg = $('<div/>')
            .addClass('custom-hint')
            .append(message);
            formElements.prepend(helpMsg);
          holder.addClass('hint-already-defined');
        }
      }
    },
    prependHTMLBeforeGroup: function (field,formGroupName,html){
      let holder = this.getHolder(field);
      if (holder){
        let formGroup = holder.find('.'+formGroupName+'-wrap');
        if (typeof formGroup !== undefined && formGroup.length > 0){
          if (!formGroup.hasClass('prepended-html-already-defined')) {
            formGroup.before(html);
            formGroup.addClass('prepended-html-already-defined');
          }
        }
      }
    },
    defineLabelHintForGroup: function (field,formGroupName,message){
      let holder = this.getHolder(field);
      if (holder){
        let formGroup = holder.find('.'+formGroupName+'-wrap');
        if (typeof formGroup !== undefined && formGroup.length > 0){
          let label = formGroup.find('label').first();
            if (!label.hasClass('label-hint-already-defined')) {
            label.append(' ');
            label.append($('<i/>')
              .addClass('fa fa-question-circle')
              .attr("title",message)
              .tooltip()
            );
            label.addClass('label-hint-already-defined');
          }
        }
      }
    },
  };