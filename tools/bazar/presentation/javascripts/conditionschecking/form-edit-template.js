typeUserAttrs = {
    ...typeUserAttrs,
    ...{
        conditionschecking: {
            condition: {
                label: _t('BAZ_FORM_EDIT_CONDITION_LABEL'),
                value: "",
            }
          },
    }
};

templates = {
    ...templates,
    ...{
        conditionschecking: function (field) {
            return { 
              field: "" ,
              onRender: function(){
                templateHelper.prependHint(field,_t('BAZ_FORM_CONDITIONSCHEKING_HINT',{
                  '\\n':'<BR>'
                }));
              },
            };
        },
    }
};

yesWikiMapping = {
    ...yesWikiMapping,
    ...{
        conditionschecking: {
            ...defaultMapping,
            ...{
                1: "condition",
                2: "",
                5: "",
                8: "",
                9: "",
            }
        }
    }
};

typeUserDisabledAttrs = {
    ...typeUserDisabledAttrs,
    ...{
        conditionschecking:['required','value','name','label']
    }
}

inputSets.push(
    {
        label: _t('BAZ_FORM_EDIT_CONDITIONCHECKING_LABEL'),
        name: "conditionschecking",
        icon: '<i class="fas fa-project-diagram"></i>',
        fields: [
            {
                type: "conditionschecking",
                label: _t('BAZ_FORM_EDIT_CONDITIONCHECKING_LABEL'),
            },
            {
                type: "labelhtml",
                label: _t('BAZ_FORM_EDIT_CONDITIONCHECKING_END'),
                content_saisie : "</div><!-- "+_t('BAZ_FORM_EDIT_CONDITIONCHECKING_END')+"-->"
            },
        ],
    }
);

fields.push({
        label: _t('BAZ_FORM_EDIT_CONDITIONCHECKING_LABEL'),
        name: "conditionschecking",
        attrs: { type: "conditionschecking" },
        icon: '<i class="fas fa-project-diagram"></i>',
    });