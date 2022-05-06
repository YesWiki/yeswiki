typeUserAttrs = {
    ...typeUserAttrs,
    ...{
        conditionschecking: {
            condition: {
                label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_LABEL'),
                value: "",
            },
            clean: {
                label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_CLEAN_LABEL'),
                options: {
                    ' ' : _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_CLEAN_OPTION'),
                    noclean: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_NOCLEAN_OPTION'),
                  }
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
                templateHelper.defineLabelHintForGroup(field,'noclean',_t('BAZ_FORM_CONDITIONSCHEKING_NOCLEAN_HINT'));
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
                2: "clean",
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
                label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_LABEL'),
            },
            {
                type: "labelhtml",
                label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_END'),
                content_saisie : "</div><!-- "+_t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_END')+"-->"
            },
        ],
    }
);

fields.push({
        label: _t('BAZ_FORM_EDIT_CONDITIONS_CHECKING_LABEL'),
        name: "conditionschecking",
        attrs: { type: "conditionschecking" },
        icon: '<i class="fas fa-project-diagram"></i>',
    });