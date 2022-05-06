typeUserAttrs = {
  ...typeUserAttrs,
  ...{
    calc: {
      displaytext: {
        label: _t('BAZ_FORM_EDIT_DISPLAYTEXT_LABEL'),
        value: "",
        placeholder: "{value}"
      },
      formula: {
        label: _t('BAZ_FORM_EDIT_FORMULA_LABEL'),
        value: "",
      },
      read: readConf,
      // write: writeconf
    },
  }
};

templates = {
  ...templates,
  ...{
    calc: function (field) {
      return { 
        field: "" ,
        onRender: function(){
          templateHelper.prependHint(field,_t('BAZ_FORM_CALC_HINT',{
            '\\n':'<BR>'
          }));
          templateHelper.defineLabelHintForGroup(field,'displaytext',_t('BAZ_FORM_EDIT_DISPLAYTEXT_HELP'));
        },
      };
    },
  }
};

yesWikiMapping = {
  ...yesWikiMapping,
  ...{
    calc: {
      ...defaultMapping,
      ...{
        4: "displaytext",
        5: "formula",
        8: "",
        9: "",
      }
    }
  }
};

typeUserDisabledAttrs = {
  ...typeUserDisabledAttrs,
  ...{
    calc:['required','value','default']
  }
}

typeUserEvents['calc'] = {
  onclone: copyMultipleSelectValues
};

fields.push({
  label: _t('BAZ_FORM_EDIT_CALC_LABEL'),
  name: "calc",
  attrs: { type: "calc" },
  icon: '<i class="fas fa-calculator"></i>',
});