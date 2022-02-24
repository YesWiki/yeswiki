var $formBuilderTextInput = $("#form-builder-text");
var $formBuilderContainer = $("#form-builder-container");
var formBuilder;

// When user add manuall via wikiCode a list or a formId that does not exist, keep the value
// so it can be added the select option list
var listAndFormUserValues = {};
// Fill the listAndFormUserValues
var text = $formBuilderTextInput.val().trim();
var textFields = text.split("\n");
for (var i = 0; i < textFields.length; i++) {
  var textField = textFields[i];
  var fieldValues = textField.split("***");
  if (fieldValues.length > 1) {
    var wikiType = fieldValues[0];
    if (
      [
        "checkboxfiche",
        "checkbox",
        "liste",
        "radio",
        "listefiche",
        "radiofiche",
      ].indexOf(wikiType) > -1 &&
      fieldValues[1] &&
      !(fieldValues[1] in formAndListIds)
    ) {
      listAndFormUserValues[fieldValues[1]] = fieldValues[1];
    }
  }
}
// Custom fields to add to form builder
var fields = [
  // {
  //   label: "Sélecteur de date",
  //   name: "jour",
  //   attrs: { type: "date" },
  //   icon: '<i class="far fa-calendar-alt"></i>',
  // },
  {
    label: _t('BAZ_FORM_EDIT_TEXT_LABEL'),
    name: "text",
    attrs: { type: "text" },
    icon:
      '<svg height="512pt" viewBox="0 -90 512 512" width="512pt" xmlns="http://www.w3.org/2000/svg"><path d="m452 0h-392c-33.085938 0-60 26.914062-60 60v212c0 33.085938 26.914062 60 60 60h392c33.085938 0 60-26.914062 60-60v-212c0-33.085938-26.914062-60-60-60zm20 272c0 11.027344-8.972656 20-20 20h-392c-11.027344 0-20-8.972656-20-20v-212c0-11.027344 8.972656-20 20-20h392c11.027344 0 20 8.972656 20 20zm-295-151v131h-40v-131h-57v-40h152v40zm40 91h40v40h-40zm80 0h40v40h-40zm80 0h40v40h-40zm0 0"/></svg>',
  },
  {
    label: _t('BAZ_FORM_EDIT_URL_LABEL'),
    name: "url",
    attrs: { type: "url" },
    icon: '<i class="fas fa-link"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_GEO_LABEL'),
    name: "map",
    attrs: { type: "map" },
    icon: '<i class="fas fa-map-marked-alt"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_IMAGE_LABEL'),
    name: "image",
    attrs: { type: "image" },
    icon: '<i class="fas fa-image"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_EMAIL_LABEL'),
    name: "champs_mail",
    attrs: { type: "champs_mail" },
    icon: '<i class="fas fa-envelope"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_TAGS_LABEL'),
    name: "tags",
    attrs: { type: "tags" },
    icon: '<i class="fas fa-tags"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_SUBSCRIBE_LIST_LABEL'),
    name: "inscriptionliste",
    attrs: { type: "inscriptionliste" },
    icon: '<i class="fas fa-mail-bulk"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_CUSTOM_HTML_LABEL'),
    name: "labelhtml",
    attrs: { type: "labelhtml" },
    icon: '<i class="fas fa-code"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_ACL_LABEL'),
    name: "acls",
    attrs: { type: "acls" },
    icon: '<i class="fas fa-user-lock"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_METADATA_LABEL'),
    name: "metadatas",
    attrs: { type: "metadatas" },
    icon: '<i class="fas fa-palette"></i>',
  },
  {
    label: "Bookmarklet",
    name: "bookmarklet",
    attrs: { type: "bookmarklet" },
    icon: '<i class="fas fa-bookmark"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_LINKEDENTRIES_LABEL'),
    name: "listefichesliees",
    attrs: { type: "listefichesliees" },
    icon: '<i class="fas fa-th-list"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_USERS_WIKINI_LABEL'),
    name: "utilisateur_wikini",
    attrs: { type: "utilisateur_wikini" },
    icon: '<i class="fas fa-user"></i>',
  },
  {
    name: "collaborative_doc",
    attrs: { type: "collaborative_doc" },
  },
  {
    label: _t('BAZ_FORM_EDIT_TITLE_LABEL'),
    name: "titre",
    attrs: { type: "titre" },
    icon: '<i class="fas fa-heading"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_CUSTOM_LABEL'),
    name: "custom",
    attrs: { type: "custom" },
    icon: '<i class="fas fa-question-circle"></i>',
  },
  {
      label: _t('BAZ_FORM_EDIT_TABS'),
      name: "tabs",
      attrs: { type: "tabs" },
      icon: '<i class="fas fa-layer-group"></i>',
  },
  {
    label: _t('BAZ_FORM_EDIT_TABCHANGE'),
    name: "tabchange",
    attrs: { type: "tabchange" },
    icon: '<i class="fas fa-stop"></i>',
  }
];

// Some attributes configuration used in multiple fields
var visibilityOptions = {
  " * ": _t('EVERYONE'),
  " + ": _t('IDENTIFIED_USERS'),
  " % ": _t('BAZ_FORM_EDIT_OWNER_AND_ADMINS'),
  "@admins": _t('MEMBER_OF_GROUP',{groupName:'admin'}),
};
// create list of groups
var formattedGroupList = [];
if (groupsList && groupsList.length > 0){
  let groupsListLen = groupsList.length;
  for(i=0;i<groupsListLen;++i){
    if (groupsList[i] !== "admins"){
      formattedGroupList["@"+groupsList[i]] = _t('MEMBER_OF_GROUP',{groupName:groupsList[i]});
    }
  }
}

var aclsOptions = {
  ...visibilityOptions,
  ...{
    user:
    _t('BAZ_FORM_EDIT_USER'),
  },
  ...formattedGroupList,
};
var readConf = { label: _t('BAZ_FORM_EDIT_CAN_BE_READ_BY'), options: {...visibilityOptions,...formattedGroupList}, multiple: true };
var writeconf = { label: _t('BAZ_FORM_EDIT_CAN_BE_WRITTEN_BY'), options: {...visibilityOptions,...formattedGroupList}, multiple: true };
var searchableConf = {
  label: _t('BAZ_FORM_EDIT_SEARCH_LABEL'),
  options: { "": _t('NO'), 1: _t('YES') },
};
var semanticConf = {
  label: _t('BAZ_FORM_EDIT_SEMANTIC_LABEL'),
  value: "",
  placeholder: "Ex: https://schema.org/name",
};
var selectConf = {
  subtype2: {
    label: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_LABEL'),
    options: {
      list: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_LIST'),
      form: _t('BAZ_FORM_EDIT_SELECT_SUBTYPE2_FORM'),
    },
  },
  listeOrFormId: {
    label: _t('BAZ_FORM_EDIT_SELECT_LIST_FORM_ID'),
    options: {
      ...{ "": "" },
      ...formAndListIds.lists,
      ...formAndListIds.forms,
      ...listAndFormUserValues,
    },
  },
  listId: {
    label: "",
    options: { ...formAndListIds.lists, ...listAndFormUserValues },
  },
  formId: {
    label: "",
    options: { ...formAndListIds.forms, ...listAndFormUserValues },
  },
  defaultValue: {
    label: _t('BAZ_FORM_EDIT_SELECT_DEFAULT'),
    value: "",
  },
  hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: "" },
  read: readConf,
  write: writeconf,
  semantic: semanticConf,
  // searchable: searchableConf -> 10/19 Florian say that this conf is not working for now
};
var TabsConf = {
  formTitles:{
      label: _t('BAZ_FORM_EDIT_TABS_FOR_FORM'),
      value:_t('BAZ_FORM_EDIT_TABS_FORMTITLES_VALUE'),
      placeholder: _t('BAZ_FORM_EDIT_TABS_FORMTITLES_DESCRIPTION'),
      description: _t('BAZ_FORM_EDIT_TABS_FORMTITLES_DESCRIPTION')
  },
  viewTitles:{
      label: _t('BAZ_FORM_EDIT_TABS_FOR_ENTRY'),
      value: "",
      placeholder: _t('BAZ_FORM_EDIT_TABS_VIEWTITLES_DESCRIPTION'),
      description: _t('BAZ_FORM_EDIT_TABS_VIEWTITLES_DESCRIPTION')
  },
  moveSubmitButtonToLastTab: {
      label: _t('BAZ_FORM_EDIT_TABS_MOVESUBMITBUTTONTOLASTTAB_LABEL'),
      options: { "": _t('NO'),"moveSubmit": _t('YES') },
      description: _t('BAZ_FORM_EDIT_TABS_MOVESUBMITBUTTONTOLASTTAB_DESCRIPTION')
    },
  btnColor: {
        label: _t('BAZ_FORM_EDIT_TABS_BTNCOLOR_LABEL'),
        options: { "btn-primary": _t('PRIMARY'),"btn-secondary-1": _t('SECONDARY') + " 1","btn-secondary-2": _t('SECONDARY') + " 2" },
      },
  btnSize: {
          label: _t('BAZ_FORM_EDIT_TABS_BTNSIZE_LABEL'),
          options: { "": _t('NORMAL_F'),"btn-xs": _t('SMALL_F') },
      },
};
var TabChangeConf = {
  formChange: {
      label: _t('BAZ_FORM_EDIT_TABS_FOR_FORM'),
      options: { "formChange": _t('YES'), "noformchange":_t('NO') },
      description: _t('BAZ_FORM_EDIT_TABCHANGE_CHANGE_LABEL') + ' ' + _t('BAZ_FORM_EDIT_TABS_FOR_FORM')
    },
  viewChange: {
      label:  _t('BAZ_FORM_EDIT_TABS_FOR_ENTRY'),
      options: { "": _t('NO'), "viewChange": _t('YES') },
      description: _t('BAZ_FORM_EDIT_TABCHANGE_CHANGE_LABEL') + ' ' + _t('BAZ_FORM_EDIT_TABS_FOR_ENTRY')
    },
};

// Attributes to be configured for each field
var typeUserAttrs = {
  text: {
    size: { label: _t('BAZ_FORM_EDIT_TEXT_SIZE'), value: "" },
    maxlength: { label: _t('BAZ_FORM_EDIT_TEXT_MAX_LENGTH'), value: "" },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: "" },
    separator: { label: "" }, // separate important attrs from others
    subtype: {
      label: _t('BAZ_FORM_EDIT_TEXT_TYPE_LABEL'),
      options: {
        text: _t('BAZ_FORM_EDIT_TEXT_TYPE_TEXT'),
        number: _t('BAZ_FORM_EDIT_TEXT_TYPE_NUMBER'),
        range: _t('BAZ_FORM_EDIT_TEXT_TYPE_RANGE'),
        url: _t('BAZ_FORM_EDIT_TEXT_TYPE_URL'),
        password: _t('BAZ_FORM_EDIT_TEXT_TYPE_PASSWORD'),
        color: _t('BAZ_FORM_EDIT_TEXT_TYPE_COLOR'),
      },
    },
    read: readConf,
    write: writeconf,
    semantic: semanticConf,
    pattern: {
      label: _t('BAZ_FORM_EDIT_TEXT_PATTERN'),
      value: "",
      placeholder: _t('BAZ_FORM_EDIT_ADVANCED_MODE')+" Ex: [0-9]+ ou [A-Za-z]{3}, ...",
    }
  },
  url: {
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  champs_mail: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: "" },
    separator: { label: "" }, // separate important attrs from others
    replace_email_by_button: {
      label: _t('BAZ_FORM_EDIT_EMAIL_REPLACE_BY_BUTTON_LABEL'),
      options: { "": _t('NO'), form: _t('YES') },
    },
    send_form_content_to_this_email: {
      label: _t('BAZ_FORM_EDIT_EMAIL_SEND_FORM_CONTENT_LABEL'),
      options: { 0: _t('NO'), 1: _t('YES') },
    },
    // searchable: searchableConf, -> 10/19 Florian say that this conf is not working for now
    read: readConf,
    write: writeconf,
    semantic: semanticConf,
  },
  map: {
    name_latitude: { label: _t('BAZ_FORM_EDIT_MAP_LATITUDE'), value: "bf_latitude" },
    name_longitude: { label: _t('BAZ_FORM_EDIT_MAP_LONGITUDE'), value: "bf_longitude" },
    autocomplete_postalcode: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_POSTALCODE'), value: "" , placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_POSTALCODE_PLACEHOLDER') },
    autocomplete_town: { label: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_TOWN'), value: "" , placeholder: _t('BAZ_FORM_EDIT_MAP_AUTOCOMPLETE_TOWNE_PLACEHOLDER') },
  },
  date: {
    today_button: {
      options: { " ": _t('NO'), today: _t('YES') },
    },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: "" },
    read: readConf,
    write: writeconf,
    semantic: semanticConf,
  },
  image: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: "" },
    thumb_height: { label: _t('BAZ_FORM_EDIT_IMAGE_WIDTH'), value: "140" },
    thumb_width: { label: _t('BAZ_FORM_EDIT_IMAGE_WIDTH'), value: "140" },
    resize_height: { label: _t('BAZ_FORM_EDIT_IMAGE_HEIGHT_RESIZE'), value: "400" },
    resize_width: { label: _t('BAZ_FORM_EDIT_IMAGE_WIDTH_RESIZE'), value: "400" },
    align: {
      label: _t('BAZ_FORM_EDIT_IMAGE_ALIGN_LABEL'),
      value: "right",
      options: { left: _t('LEFT'), right: _t('RIGHT') },
    },
    read: readConf,
    write: writeconf,
    semantic: semanticConf,
  },
  select: {
    ...selectConf,
    ...{
      queries: {
        label: _t('BAZ_FORM_EDIT_QUERIES_LABEL'),
        value: "",
        placeholder: "ex. : checkboxfiche6=PageTag ; cf. https://yeswiki.net/?LierFormulairesEntreEux",
      },
    },
  },
  "checkbox-group": {
    ...selectConf,
    ...{
      fillingMode: {
        label: _t('BAZ_FORM_EDIT_FILLING_MODE_LABEL'),
        options: {
          " ": _t('BAZ_FORM_EDIT_FILLING_MODE_NORMAL'),
          tags: _t('BAZ_FORM_EDIT_FILLING_MODE_TAGS'),
          dragndrop: _t('BAZ_FORM_EDIT_FILLING_MODE_DRAG_AND_DROP'),
        },
      },
      queries: {
        label: _t('BAZ_FORM_EDIT_QUERIES_LABEL'),
        value: "",
        placeholder: "ex. : checkboxfiche6=PageTag ; cf. https://yeswiki.net/?LierFormulairesEntreEux",
      },
    },
  },
  "radio-group":  {
    ...selectConf,
    ...{
      fillingMode: {
        label: _t('BAZ_FORM_EDIT_FILLING_MODE_LABEL'),
        options: {
          " ": _t('BAZ_FORM_EDIT_FILLING_MODE_NORMAL'),
          tags: _t('BAZ_FORM_EDIT_FILLING_MODE_TAGS'),
        },
      },
      queries: {
        label: _t('BAZ_FORM_EDIT_QUERIES_LABEL'),
        value: "",
        placeholder: "ex. : checkboxfiche6=PageTag ; cf. https://yeswiki.net/?LierFormulairesEntreEux",
      },
    },
  },
  textarea: {
    syntax: {
      label: _t('BAZ_FORM_EDIT_TEXTAREA_SYNTAX_LABEL'),
      options: {
        wiki: "Wiki",
        html: _t('BAZ_FORM_EDIT_TEXTAREA_SYNTAX_HTML'),
        nohtml: _t('BAZ_FORM_EDIT_TEXTAREA_SYNTAX_NOHTML'),
      },
    },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: "" },
    size: { label: _t('BAZ_FORM_EDIT_TEXTAREA_SIZE_LABEL'), value: "" },
    rows: {
      label: _t('BAZ_FORM_EDIT_TEXTAREA_ROWS_LABEL'),
      type: 'number',
      placeholder: _t('BAZ_FORM_EDIT_TEXTAREA_ROWS_PLACEHOLDER')
    },
    read: readConf,
    write: writeconf,
    semantic: semanticConf,
  },
  file: {
    readlabel: {
      label: _t('BAZ_FORM_EDIT_FILE_READLABEL_LABEL'), 
      value: "",
      placeholder: _t('BAZ_FILEFIELD_FILE')
    },
    maxsize: { label: _t('BAZ_FORM_EDIT_FILE_MAXSIZE_LABEL'), value: "" },
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: "" },
    read: readConf,
    write: writeconf,
    semantic: semanticConf,
  },
  tags: {
    hint: { label: _t('BAZ_FORM_EDIT_HELP'), value: "" },
    read: readConf,
    write: writeconf,
    semantic: semanticConf,
  },
  inscriptionliste: {
    subscription_email: { label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_LABEL'), value: "" },
    email_field_id: {
      label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_EMAIL_FIELDID'),
      value: "bf_mail",
    },
    mailing_list_tool: {
      label: _t('BAZ_FORM_EDIT_INSCRIPTIONLISTE_MAILINGLIST'),
      value: ""
    },
  },
  labelhtml: {
    label: { value: _t('BAZ_FORM_EDIT_CUSTOM_HTML_LABEL'), value: "" },
    content_saisie: { label: _t('BAZ_FORM_EDIT_EDIT_CONTENT_LABEL'), type: "textarea", rows: "4", value: "" },
    content_display: { label: _t('BAZ_FORM_EDIT_VIEW_CONTENT_LABEL'), type: "textarea", rows: "4", value: ""  },
  },
  utilisateur_wikini: {
    name_field: { label: _t('BAZ_FORM_EDIT_USERS_WIKINI_NAME_FIELD_LABEL'), value: "bf_titre" },
    email_field: {
      label: _t('BAZ_FORM_EDIT_USERS_WIKINI_EMAIL_FIELD_LABEL'),
      value: "bf_mail",
    },
    // mailing_list: {
    //   label: "Inscrite à une liste de diffusion"
    // },
    autoupdate_email: {
      label: _t('BAZ_FORM_EDIT_USERS_WIKINI_AUTOUPDATE_MAIL'),
      options: { 0: _t('NO'), 1: _t('YES') },
    },
    auto_add_to_group: {
      label: _t('BAZ_FORM_EDIT_ADD_TO_GROUP_LABEL'),
      value: "",
      placeholder: _t('BAZ_FORM_EDIT_ADD_TO_GROUP_DESCRIPTION'),
      description: _t('BAZ_FORM_EDIT_ADD_TO_GROUP_DESCRIPTION')
    },
  },
  acls: {
    read: { label: _t('BAZ_FORM_EDIT_ACL_READ_LABEL'), options: aclsOptions, multiple: true },
    write: { label: _t('BAZ_FORM_EDIT_ACL_WRITE_LABEL'), options: aclsOptions, multiple: true },
    comment: { label: _t('BAZ_FORM_EDIT_ACL_COMMENT_LABEL'), options: aclsOptions, multiple: true },
  },
  metadatas: {
    theme: {
      label: _t('BAZ_FORM_EDIT_METADATA_THEME_LABEL'),
      value: "",
      placeholder: "margot, interface, colibris",
    },
    squelette: { label: _t('BAZ_FORM_EDIT_METADATA_SQUELETON_LABEL'), value: "1col.tpl.html" },
    style: { label: _t('BAZ_FORM_EDIT_METADATA_STYLE_LABEL'), value: "", placeholder: "bootstrap.css..." },
    preset: { label: _t("BAZ_FORM_EDIT_METADATA_PRESET_LABEL"), value: "", placeholder: "blue.css (" + _t("BAZ_FORM_EDIT_METADATA_PRESET_PLACEHOLDER") + ")" },
    image: { label: _t('BAZ_FORM_EDIT_METADATA_BACKGROUND_IMAGE_LABEL'), value: "", placeholder: "foret.jpg..." },
  },
  bookmarklet: {},
  collaborative_doc: {},
  titre: {},
  listefichesliees: {
    id: { label: _t('BAZ_FORM_EDIT_LISTEFICHES_FORMID_LABEL'), value: "" },
    query: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_QUERY_LABEL'), 
      value: "",
      placeholder: _t('BAZ_FORM_EDIT_LISTEFICHES_QUERY_PLACEHOLDER',{url:'https://yeswiki.net/?DocQuery/iframe'}),
    },
    param: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_PARAMS_LABEL'),
      value: "",
      placeholder: 'Ex: champs="bf_nom" ordre="desc"',
    },
    number: { label: _t('BAZ_FORM_EDIT_LISTEFICHES_NUMBER_LABEL'), value: "", placeholder: "" },
    template: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_TEMPLATE_LABEL'),
      value: "",
      placeholder:
        _t('BAZ_FORM_EDIT_LISTEFICHES_TEMPLATE_PLACEHOLDER'),
    },
    type_link: {
      label: _t('BAZ_FORM_EDIT_LISTEFICHES_LISTTYPE_LABEL'),
      value: "",
      placeholder:
        _t('BAZ_FORM_EDIT_LISTEFICHES_LISTTYPE_PLACEHOLDER'),
    },
    read: readConf,
    write: writeconf,
    semantic: semanticConf,
  },
  custom: {
    param0: { label: "Param0", value: "" },
    param1: { label: "Param1", value: "" },
    param2: { label: "Param2", value: "" },
    param3: { label: "Param3", value: "" },
    param4: { label: "Param4", value: "" },
    param5: { label: "Param5", value: "" },
    param6: { label: "Param6", value: "" },
    param7: { label: "Param7", value: "" },
    param8: { label: "Param8", value: "" },
    param9: { label: "Param9", value: "" },
    param10: { label: "Param10", value: "" },
    param11: { label: "Param11", value: "" },
    param12: { label: "Param12", value: "" },
    param13: { label: "Param13", value: "" },
    param14: { label: "Param14", value: "" },
    param15: { label: "Param15", value: "" },
  },
  tabs: TabsConf,
  tabchange: TabChangeConf,
};

// How a field is represented in the formBuilder view
var templates = {
  champs_mail: function (fieldData) {
    return {
      field: `<input id="${fieldData.name}" type="email" value="" />`
    };
  },
  map: function (fieldDate) {
    return {
      field: _t('BAZ_FORM_EDIT_MAP_FIELD'),
    };
  },
  image: function (fieldDate) {
    return { field: '<input type="file"/>' };
  },
  text: function (fieldData) {
    var string = `<input type="${fieldData.subtype}"`;
    if (fieldData.subtype == "url")
      string += ` placeholder="${fieldData.value || ""}"/>`;
    else if (fieldData.subtype == "range" || fieldData.subtype == "number")
      string += ` min="${fieldData.size || ""}" max="${fieldData.maxlength || ""}"/>`;
    else 
      string += ` value="${fieldData.value}"/>`;
    return { field: string };
  },
  url: function (fieldData) {
    return { field: `<input type="url" placeholder="${fieldData.value || ''}"/>` }
  },
  tags: function (field) {
    return { field: "<input/>" };
  },
  inscriptionliste: function (field) {
    return { field: '<input type="checkbox"/>' };
  },
  labelhtml: function (field) {
    return {
      field:
        `<div>${field.content_saisie || ""}</div>
         <div>${field.content_display || ""}</div>`,
    };
  },
  utilisateur_wikini: function (field) {
    return {
      field: "",
      onRender: function(){
        templateHelper.defineLabelHintForGroup(field,'auto_add_to_group',_t('BAZ_FORM_EDIT_ADD_TO_GROUP_HELP'));
      },
    };
  },
  acls: function (field) {
    return { field: "" };
  },
  metadatas: function (field) {
    return { field: "" };
  },
  bookmarklet: function (field) {
    return { field: "" };
  },
  listefichesliees: function (field) {
    return { field: "" };
  },
  collaborative_doc: function (field) {
    return { field: _t('BAZ_FORM_EDIT_COLLABORATIVE_DOC_FIELD') };
  },
  titre: function (field) {
    return { field: field.value };
  },
  custom: function (field) {
    return { field: "" };
  },
  tabs: function (field) {
      return { 
        field: "" ,
        onRender: function(){
          templateHelper.prependHint(field,_t('BAZ_FORM_TABS_HINT',{
            '\\n':'<BR>',
            'tabs-field-label': _t('BAZ_FORM_EDIT_TABS'),
            'tabchange-field-label': _t('BAZ_FORM_EDIT_TABCHANGE')
          }));
          templateHelper.prependHTMLBeforeGroup(field,'formTitles',$('<div/>').addClass('form-group').append($('<b/>').append(_t('BAZ_FORM_EDIT_TABS_TITLES_LABEL'))));
          templateHelper.defineLabelHintForGroup(field,'formTitles',_t('BAZ_FORM_EDIT_TABS_FORMTITLES_DESCRIPTION'));
          templateHelper.defineLabelHintForGroup(field,'viewTitles',_t('BAZ_FORM_EDIT_TABS_VIEWTITLES_DESCRIPTION'));
          templateHelper.prependHTMLBeforeGroup(field,'moveSubmitButtonToLastTab',$('<hr/>').addClass('form-group'));
          
          let holder = templateHelper.getHolder(field);
          if (holder){
            let formGroup = holder.find('.formTitles-wrap');
            if (typeof formGroup !== undefined && formGroup.length > 0){
              let input = formGroup.find('input').first();
              if (typeof input !== undefined && input.length > 0){
                $(input).val($(input).val().replace(/\|/g,","));
              }
            }
          }
        },
      };
  },
  tabchange: function (field) {
      return { 
        field: "" ,
        onRender: function(){
          templateHelper.prependHint(field,_t('BAZ_FORM_TABS_HINT',{
            '\\n':'<BR>',
            'tabs-field-label': _t('BAZ_FORM_EDIT_TABS'),
            'tabchange-field-label': _t('BAZ_FORM_EDIT_TABCHANGE')
          }));
          templateHelper.prependHTMLBeforeGroup(field,'formChange',$('<div/>').addClass('form-group').append($('<b/>').append(_t('BAZ_FORM_EDIT_TABCHANGE_CHANGE_LABEL'))));
        },
      };
  },
};

var typeUserDisabledAttrs = {
  tabs:['required','value','name','label'],
  tabchange:['required','value','name','label'],
};

var inputSets = [
  {
    label: _t('BAZ_FORM_EDIT_TABS'),
    name: "tabs",
    icon: '<i class="fas fa-layer-group"></i>',
    fields: [
      {
        type: "tabs",
        label: _t('BAZ_FORM_EDIT_TABS'),
      },
      {
        type: "tabchange",
        label: _t('BAZ_FORM_EDIT_TABCHANGE'),
      },
      {
        type: "tabchange",
        label: _t('BAZ_FORM_EDIT_TABCHANGE'),
      },
      {
        type: "tabchange",
        label: _t('BAZ_FORM_EDIT_TABCHANGE'),
      },
    ],
  },
];

// Mapping betwwen yes wiki syntax and FormBuilder json syntax
var defaultMapping = {
  0: "type",
  1: "name",
  2: "label",
  3: "size",
  4: "maxlength",
  5: "value",
  6: "pattern",
  7: "subtype",
  8: "required",
  9: "searchable",
  10: "hint",
  11: "read",
  12: "write",
  14: "semantic",
  15: "queries",
};
var lists = {
  ...defaultMapping,
  ...{ 1: "listeOrFormId", 5: "defaultValue", 6: "name" },
};
var yesWikiMapping = {
  text: defaultMapping,
  url: defaultMapping,
  number: defaultMapping,
  champs_mail: {
    ...defaultMapping,
    ...{ 6: "replace_email_by_button", 9: "send_form_content_to_this_email" },
  },
  map: {
    0: "type",
    1: "name_latitude",
    2: "name_longitude",
    3: "",
    4: "autocomplete_postalcode",
    5: "autocomplete_town",
    8: "required"
  },
  date: { ...defaultMapping, ...{ 5: "today_button" } },
  image: {
    ...defaultMapping,
    ...{
      1: "name",
      3: "thumb_height",
      4: "thumb_width",
      5: "resize_height",
      6: "resize_width",
      7: "align",
    },
  },
  select: lists,
  "checkbox-group": { ...lists, ...{ 7: "fillingMode" } },
  "radio-group": { ...lists, ...{ 7: "fillingMode" } },
  textarea: { ...defaultMapping, ...{ 4: "rows", 7: "syntax" } },
  file: { ...defaultMapping, ...{ 3: "maxsize", 6: "readlabel" } },
  tags: defaultMapping,
  inscriptionliste: {
    0: "type",
    1: "subscription_email",
    2: "label",
    3: "email_field_id",
    4: "mailing_list_tool",
  },
  labelhtml: { 0: "type", 1: "content_saisie", 2: "", 3: "content_display" },
  utilisateur_wikini: { 
    ...defaultMapping,
    ...{0: "type", 1: "name_field", 2: "email_field",5:'',/*5:"mailing_list",*/6: "auto_add_to_group",8:"",9:"autoupdate_email" }
  },
  titre: { 0: "type", 1: "value", 2: "label" },
  acls: { 0: "type", 1: "read", 2: "write", 3: "comment" },
  metadatas: { 0: "type", 1: "theme", 2: "squelette", 3: "style", 4: "image", 5:"preset" },
  hidden: { 0: "type", 1: "name", 5: "value" },
  bookmarklet: { 0: "type", 1: "name", 2: "label", 3: "value" },
  listefichesliees: {
    0: "type",
    1: "id",
    2: "query",
    3: "param",
    4: "number",
    5: "template",
    6: "type_link",
  },
  collaborative_doc: defaultMapping,
  custom: {
    0: "param0",
    1: "param1",
    2: "param2",
    3: "param3",
    4: "param4",
    5: "param5",
    6: "param6",
    7: "param7",
    8: "param8",
    9: "param9",
    10: "param10",
    11: "param11",
    12: "param12",
    13: "param13",
    14: "param14",
    15: "param15",
  },
  tabs: {
      ...defaultMapping,
      ...{
          1:'formTitles',
          2: "",
          3: 'viewTitles',
          5: 'moveSubmitButtonToLastTab',
          6: '',
          7: 'btnColor',
          9: 'btnSize'
      }
  },
  tabchange: {
      ...defaultMapping,
      ...{
          1:'formChange',
          2: "",
          3:'viewChange'
      }
  },
};
// Mapping betwwen yeswiki field type and standard field implemented by form builder
var yesWikiTypes = {
  lien_internet: { type: "url" },
  lien_internet_bis: { type: "text", subtype: "url" },
  mot_de_passe: { type: "text", subtype: "password" },
  // "nombre": { type: "text", subtype: "tel" },
  texte: { type: "text" }, // all other type text subtype (range, text, tel)
  textelong: { type: "textarea", subtype: "textarea" },
  listedatedeb: { type: "date" },
  listedatefin: { type: "date" },
  jour: { type: "date" },
  map: { type: "map" },
  carte_google: { type: "map" },
  checkbox: { type: "checkbox-group", subtype2: "list" },
  liste: { type: "select", subtype2: "list" },
  radio: { type: "radio-group", subtype2: "list" },
  checkboxfiche: { type: "checkbox-group", subtype2: "form" },
  listefiche: { type: "select", subtype2: "form" },
  radiofiche: { type: "radio-group", subtype2: "form" },
  fichier: { type: "file", subtype: "file" },
  champs_cache: { type: "hidden" },
  listefiches: { type: "listefichesliees" },
};

var defaultFieldsName = {
  textarea: "bf_description",
  image: "bf_image",
  champs_mail: "bf_mail",
}

var I18nOption = {
  ar: 'ar-SA',
  ca: 'ca-ES',
  cs: 'cs-CZ',
  da: 'da-DK',
  de: 'de-DE',
  el: 'el-GR',
  en: 'en-US',
  es: 'es-ES',
  fa: 'fa-IR',
  fi: 'fi-FI',
  fr: 'fr-FR',
  he: 'he-IL',
  hu: 'hu-HU',
  it: 'it-IT',
  ja: 'ja-JP',
  my: 'my-MM',
  nb: 'nb-NO',
  pl: 'pl-PL',
  pt: 'pt-BR',
  qz: 'qz-MM',
  ro: 'ro-RO',
  ru: 'ru-RU',
  sj: 'sl-SL',
  th: 'th-TH',
  uk: 'uk-UA',
  vi: 'vi-VN',
  zh: 'zh-CN',
};

function initializeFormbuilder(formAndListIds) {
  // FormBuilder conf
  formBuilder = $formBuilderContainer.formBuilder({
    showActionButtons: false,
    fields: fields,
    i18n: {
      locale: I18nOption[wiki.locale] ?? 'fr-FR',
      location: wiki.baseUrl.replace('?','')+"javascripts/vendor/formbuilder-languages/"
    },
    templates: templates,
    disableFields: [
      "number",
      "button",
      "autocomplete",
      "checkbox",
      "paragraph",
      "header",
      "collaborative_doc",
    ],
    controlOrder: ["text", "textarea", "jour", "image", "url", "file", "champs_mail", "tags"],
    disabledAttrs: [
      "access",
      "placeholder",
      "className",
      "inline",
      "toggle",
      "description",
      "other",
      "multiple",
    ],
    typeUserAttrs: typeUserAttrs,
    typeUserDisabledAttrs: typeUserDisabledAttrs,
    inputSets:inputSets,
    onAddField: function(fieldId, field) {
      if (!field.hasOwnProperty("read")){
        field.read = [" * "];// everyone by default
      };
      if (!field.hasOwnProperty("write")){
        field.write = [" * "];// everyone by default
      };
      if (field.type === "acls" && !field.hasOwnProperty("comment")){
        field.comment = [" * "];// everyone by default
      }
    },
  });

  // Each 300ms update the text field converting form bulder content into wiki syntax
  var formBuilderInitialized = false;
  var existingFieldsNames = [], existingFieldsIds = []
  
  setInterval(function () {
    if (!formBuilder || !formBuilder.actions || !formBuilder.actions.setData)
      return;
    if (!formBuilderInitialized) {
      initializeBuilderFromTextInput();
      existingFieldsIds = getFieldsIds()
      formBuilderInitialized = true;
    }
    if ($formBuilderTextInput.is(":focus")) return;
    // Change names
    $(".form-group.name-wrap label").text(_t('BAZ_FORM_EDIT_UNIQUE_ID'));
    $(".form-group.label-wrap label").text(_t('BAZ_FORM_EDIT_NAME'));
    existingFieldsNames = []
    $(".fld-name").each(function() { existingFieldsNames.push($(this).val()) })
    
    // Transform input[textarea] in real textarea
    $('input[type="textarea"]').replaceWith(function() {
      const textarea = document.createElement('textarea')
      textarea.id = this.id
      textarea.name = this.name
      textarea.value = this.value
      textarea.classList = this.classList
      textarea.title = this.title
      textarea.rows = $(this).attr('rows')
      return textarea
    })

    // Slugiy field names
    $(".fld-name").each(function () {
      var newValue = $(this)
        .val()
        .replace(/[^a-z^A-Z^_^0-9^{^}]/g, "_")
        .toLowerCase();
      $(this).val(newValue);
    });

    if ($("#form-builder-container").is(":visible")) {
      var formData = formBuilder.actions.getData();
      var wikiText = formatJsonDataIntoWikiText(formData);
      if (wikiText) $formBuilderTextInput.val(wikiText);
    }

    // when selecting between data source lists or forms, we need to populate again the listOfFormId select with the
    // proper set of options
    $(".radio-group-field, .checkbox-group-field, .select-field")
      .find("select[name=subtype2]:not(.initialized)")
      .change(function () {
        $(this).addClass("initialized");
        var visibleSelect = $(this)
          .closest(".form-field")
          .find("select[name=listeOrFormId]");
        selectedValue = visibleSelect.val();
        visibleSelect.empty();
        var optionToAddToSelect = $(this)
          .closest(".form-field")
          .find("select[name=" + $(this).val() + "Id] option");
        visibleSelect.append(new Option("", "", false));
        optionToAddToSelect.each(function () {
          var optionKey = $(this).attr("value");
          var optionLabel = $(this).text();
          var isSelected = optionKey == selectedValue;
          var newOption = new Option(optionLabel, optionKey, false, isSelected);
          visibleSelect.append(newOption);
        });
      })
      .trigger("change");

    
    $(".fld-name").each(function() { 
      var name = $(this).val()
      var id = $(this).closest('.form-field').attr('id')

      // Detect new fields added
      if (!existingFieldsIds.includes(id)) {
        var fieldType = $(this).closest('.form-field').attr('type');

        // Make the default names easier to read
        if (['radio_group', 'checkbox_group', 'select'].includes(fieldType)) {
          name = ''
        } else if (!name.includes('bf_')) {
          name = defaultFieldsName[fieldType] || 'bf_' + fieldType
          if (existingFieldsNames.includes(name)) {
            // If name already exist, we add a number (bf_address, bf_address1, bf_address2...)
            number = 1
            while(existingFieldsNames.includes(name + number)) number += 1
            name += number
          }
        }

        // if it's a map, we automatically add a bf_addresse
        if (fieldType == 'map' && !existingFieldsNames.includes('bf_adresse')) {
          var field = {
            type: 'text',
            subtype: 'text',
            name: 'bf_adresse',
            label: _t('BAZ_FORM_EDIT_ADDRESS')
          };
          var index = $(this).closest('.form-field').index()
          formBuilder.actions.addField(field, index);
        }
      }
      $(this).val(name)
    })

    existingFieldsIds = getFieldsIds()

    $(".text-field select[name=subtype]:not(.initialized)")
      .change(function () {
        $(this).addClass("initialized");
        $parent = $(this).closest(".form-field");
        if ($(this).val() == "range" || $(this).val() == "number") {
          $parent.find(".maxlength-wrap label").text(_t('BAZ_FORM_EDIT_MAX_VAL'));
          $parent.find(".size-wrap label").text(_t('BAZ_FORM_EDIT_MIN_VAL'));
        } else {
          $parent.find(".maxlength-wrap label").text(_t('BAZ_FORM_EDIT_MAX_LENGTH'));
          $parent.find(".size-wrap label").text(_t('BAZ_FORM_EDIT_NB_CHARS'));
        }
        if ($(this).val() == "color") {
          $parent.find(".maxlength-wrap, .size-wrap").hide();
        } else {
          $parent.find(".maxlength-wrap, .size-wrap").show();
        }
      })
      .trigger("change");

    // in semantic field, we want to separate value by coma
    $(".fld-semantic").each(function () {
      var newVal = $(this)
        .val()
        .replace(/\s*,\s*/g, ",");
      newVal = newVal.replace(/\s+/g, ",");
      newVal = newVal.replace(/,+/g, ",");
      $(this).val(newVal);
    });

    // Changes icons and icones helpers
    $("a[type=remove].icon-cancel")
      .removeClass("icon-cancel")
      .html('<i class="fa fa-trash"></i>')
    $("a[type=copy].icon-copy").attr("title", _t('DUPLICATE'));
    $("a[type=edit].icon-pencil").attr("title", _t('BAZ_FORM_EDIT_HIDE'));
  }, 300);

  $("#formbuilder-link").click(initializeBuilderFromTextInput);
}

function getFieldsIds() {
  result = []
  $(".fld-name").each(function() { result.push($(this).closest('.form-field').attr('id')) })
  return result
}
// Remove accidental br at the end of the labels
function removeBR(text) {
  var newValue = text.replace(/(<div><br><\/div>)+$/g, '')
  // replace multiple '<div><br></div>' when at the end of the value
  newValue = newValue.replace(/(<br>)+$/g, '')
  // replace multiple '<br>' when at the end of the value
  return newValue;
}

function initializeBuilderFromTextInput() {
  var jsonData = parseWikiTextIntoJsonData($formBuilderTextInput.val());
  formBuilder.actions.setData(JSON.stringify(jsonData));
}

// transform a json object like "{ type: 'texte', name: 'bf_titre', label: 'Nom' .... }"
// into wiki text like "texte***bf_titre***Nom***255***255*** *** *** ***1***0***"
function formatJsonDataIntoWikiText(formData) {
  if (formData.length == 0) return null;
  var wikiText = "";

  for (var i = 0; i < formData.length; i++) {
    var wikiProps = {};
    var formElement = formData[i];
    var mapping = yesWikiMapping[formElement.type];

    for (var type in yesWikiTypes)
      if (
        formElement.type == yesWikiTypes[type].type &&
        (!formElement.subtype ||
          !yesWikiTypes[type].subtype ||
          formElement.subtype == yesWikiTypes[type].subtype) &&
        (!formElement.subtype2 ||
          formElement.subtype2 == yesWikiTypes[type].subtype2)
      ) {
        wikiProps[0] = type;
        break;
      }
    // for non mapped fields, we just keep the form type
    if (!wikiProps[0]) wikiProps[0] = formElement.type;
    
    // fix for url field which can be build with textField or urlField
    if (wikiProps[0]) wikiProps[0] = wikiProps[0].replace('_bis', '') 

    for (var key in mapping) {
      var property = mapping[key];
      if (property != "type") {
        var value = formElement[property];
        if (["required", "access"].indexOf(property) > -1)
          value = value ? "1" : "0";
        if (property == "label"){
          wikiProps[key] = removeBR(value).replace(/\n$/gm,"");
        } else {
          wikiProps[key] = value ;
        }
      }
    }

    maxProp = Math.max.apply(Math, Object.keys(wikiProps));
    for (var j = 0; j <= maxProp; j++) {
      wikiText += wikiProps[j] || " ";
      wikiText += "***";
    }
    wikiText += "\n";
  }
  return wikiText;
}

// transform text with wiki text like "texte***bf_titre***Nom***255***255*** *** *** ***1***0***"
// into a json object "{ type: 'texte', name: 'bf_titre', label: 'Nom' .... }"
function parseWikiTextIntoJsonData(text) {
  var result = [];
  var text = text.trim();
  var textFields = text.split("\n");
  for (var i = 0; i < textFields.length; i++) {
    var textField = textFields[i];
    var fieldValues = textField.split("***");
    var fieldObject = {};
    if (fieldValues.length > 1) {
      var wikiType = fieldValues[0];
      var fieldType =
        wikiType in yesWikiTypes ? yesWikiTypes[wikiType].type : wikiType;
      // check that the fieldType really exists in our form builder
      if (!(fieldType in yesWikiMapping)) fieldType = "custom";

      var mapping = yesWikiMapping[fieldType];

      fieldObject["type"] = fieldType;
      fieldObject["subtype"] =
        wikiType in yesWikiTypes ? yesWikiTypes[wikiType].subtype : "";
      fieldObject["subtype2"] =
        wikiType in yesWikiTypes ? yesWikiTypes[wikiType].subtype2 : "";
      var start = fieldType == "custom" ? 0 : 1;
      for (var j = start; j < fieldValues.length; j++) {
        var value = fieldValues[j];
        var field = mapping && j in mapping ? mapping[j] : j;
        if (field == "required") value = value == "1" ? true : false;
        if (field){
          if (field == "read" || field == "write" || field == "comment"){
            fieldObject[field] = (value.trim() === "") ? [" * "] : value.split(',');
          } else {
            fieldObject[field] = value;
          }
        }
      }
      if (!fieldObject.label) {
        fieldObject.label = wikiType;
        for (var k = 0; k < fields.length; k++)
          if (fields[k].name == wikiType) fieldObject.label = fields[k].label;
      }
      result.push(fieldObject);
    }
  }
  if (wiki.isDebugEnabled){
    console.log("parse result", result);
  }
  return result;
}

$('a[href="#formbuilder"]').on('click',function (event){
  if(!confirm(_t('BAZ_FORM_EDIT_CONFIRM_DISPLAY_FORMBUILDER'))){
    event.preventDefault();
    return false;
  };
});