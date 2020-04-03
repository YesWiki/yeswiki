var $formBuilderTextInput = $("#form-builder-text");
var $formBuilderContainer = $("#form-builder-container");
var formBuilder;

// Custom fields to add to form builder
var fields = [
  {
    label: "Texte, Nombre, Couleur, Url",
    name: "text",
    attrs: { type: "text" }
  },
  {
    label: "Carte Geolocalisation",
    name: "carte_google",
    attrs: { type: "carte_google" },
    icon: "🌎"
  },
  { label: "Image", name: "image", attrs: { type: "image" }, icon: "🖼" },
  {
    label: "Email",
    name: "champs_mail",
    attrs: { type: "champs_mail" },
    icon: "✉️"
  },
  { label: "Tags", name: "tags", attrs: { type: "tags" }, icon: "🏷" },
  {
    label: "Inscription Liste Diffusion",
    name: "inscriptionliste",
    attrs: { type: "inscriptionliste" },
    icon: "📫"
  },
  {
    label: "Custom HTML",
    name: "labelhtml",
    attrs: { type: "labelhtml" },
    icon: "📜"
  },
  {
    label: "Config Droits d'accès",
    name: "acls",
    attrs: { type: "acls" },
    icon: "🔒"
  },
  {
    label: "Config Thème de la fiche",
    name: "metadatas",
    attrs: { type: "metadatas" },
    icon: "🎨"
  },
  {
    label: "Bookmarklet",
    name: "bookmarklet",
    attrs: { type: "bookmarklet" },
    icon: "🗃"
  },
  {
    label: "Liste des fiches liées",
    name: "listefichesliees",
    attrs: { type: "listefichesliees" },
    icon: "🖇"
  },
  {
    label: "Créer un utilisateur lorsque la fiche est validée",
    name: "utilisateur_wikini",
    attrs: { type: "utilisateur_wikini" },
    icon: "👤"
  },
  {
    name: 'collaborative_doc',
    attrs: { type: "collaborative_doc" },
  },
  {
    label: "Titre Automatique",
    name: 'titre',
    attrs: { type: "titre" },
    icon: "👤"
  }
];

// Some attributes configuration used in multiple fields
var visibilityOptions = {
  "": "Tout le monde",
  "+": "Utilisateurs identifiés",
  "%": "Propriétaire de la fiche et admins",
  "@admins": "Membre du groupe admin"
};
var aclsOptions = {
  ...visibilityOptions,
  ...{
    user:
      "Utilisateur (lorsqu'on créé un utilisateur en même temps que la fiche)"
  }
};
var readConf = { label: "Peut être lu par", options: visibilityOptions };
var writeconf = { label: "Peut être saisi par", options: visibilityOptions };
var searchableConf = {
  label: "Présence dans le moteur de recherche",
  options: { "": "Non", "1": "Oui" }
};
var semanticConf = {
  label: "Type sémantique du champ",
  placeholder: "Exple: https://schema.org/name"
};
var selectConf = {
  subtype2: {
    label: "Origine des données",
    options: {
      list: "Une liste",
      form: "Un Formulaire Bazar"
    }
  },
  listeOrFormId: {
    label: "Choix de la liste/du formulaire",
    options: { ...{ "": "" }, ...formAndListIds.lists, ...formAndListIds.forms }
  },
  listId: { label: "", options: formAndListIds.lists },
  formId: { label: "", options: formAndListIds.forms },
  hint: { label: "Texte d'aide" },
  read: readConf,
  write: writeconf,
  semantic: semanticConf
  // searchable: searchableConf -> 10/19 Florian say that this conf is not working for now
};

// Attributes to be configured for each field
var typeUserAttrs = {
  text: {
    size: { label: "Nbre caractères visibles" },
    maxlength: { label: "Longueur Max." },
    hint: { label: "Texte d'aide" },
    separator: { label: "" }, // separate important attrs from others
    subtype: {
      label: "Type",
      options: {
        text: "Texte",
        range: "Slider",
        url: "Url",
        password: "Mot de passe",
        color: "Couleur"
      }
    },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  champs_mail: {
    hint: { label: "Texte d'aide" },
    separator: { label: "" }, // separate important attrs from others
    replace_email_by_button: {
      label: "Remplacer l'email par un bouton contact",
      options: { "": "Non", form: "Oui" }
    },
    send_form_content_to_this_email: {
      label: "Envoyer le contenu du formulaire à cet email",
      options: { "": "Non", "1": "Oui" }
    },
    // searchable: searchableConf, -> 10/19 Florian say that this conf is not working for now
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  carte_google: {
    name_latitude: { label: "Nom champ latitude", value: "bf_latitude" },
    name_longitude: { label: "Nom champ longitude", value: "bf_longitude" }
  },
  date: {
    today_button: {
      label: "Btn Aujourd'hui",
      options: { "": "Non", "1": "Oui" }
    },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  image: {
    hint: { label: "Texte d'aide" },
    thumb_height: { label: "Hauteur Vignette", value: "140" },
    thumb_width: { label: "Largeur Vignette", value: "140" },
    resize_height: { label: "Hauteur redimension", value: "600" },
    resize_width: { label: "Largeur redimension", value: "600" },
    align: {
      label: "Alignement",
      value: "right",
      options: { left: "Gauche", center: "Centre", right: "Droite" }
    },
    semantic: semanticConf
  },
  select: selectConf,
  "checkbox-group": selectConf,
  "radio-group": selectConf,
  textarea: {
    syntax: {
      label: "Format d'écriture",
      options: {
        wiki: "Wiki",
        html: "Editeur Wysiwyg",
        nohtml: "Html non interprété"
      }
    },
    size: { label: "Largeur champ de saisie" },
    semantic: semanticConf
  },
  file: {
    maxsize: { label: "Taille max" },
    semantic: semanticConf
  },
  tags: {
    hint: { label: "Texte d'aide" },
    read: readConf,
    write: writeconf,
    semantic: semanticConf
  },
  inscriptionliste: {
    subscription_email: { label: "Email pour s'inscrire" },
    email_field_id: {
      label: "Champ du formulaire fournissant l'email à inscire",
      value: "bf_mail"
    },
    mailing_list_tool: {
      label: "Type de service de diffusion",
      options: { "": "", ezmlm: "Ezmlm" }
    }
  },
  labelhtml: {
    label: { value: "Custom HT" },
    content_saisie: { label: "Contenu lors la saisie" },
    content_display: { label: "Contenu lors de l'affichage d'une fiche" }
  },
  utilisateur_wikini: {
    name_field: { label: "Champ pour le nom d'utilisateur", value: "bf_titre" },
    email_field: {
      label: "Champ pour l'email de l'utilisateur",
      value: "bf_mail"
    }
  },
  acls: {
    read: { label: "Peut voir la fiche", options: aclsOptions },
    write: { label: "Peut éditer la fiche", options: aclsOptions },
    comment: { label: "Peut commenter la fiche", options: aclsOptions }
  },
  metadatas: {
    theme: {
      label: "Nom du thème",
      placeholder: "margot, interface, colibris"
    },
    squelette: { label: "Squelette", value: "1col.tpl.html" },
    style: { label: "Style", placeholder: "bootstrap.min.css..." },
    image: { label: "Image de fond", placeholder: "foret.jpg..." }
  },
  bookmarklet: {},
  collaborative_doc: {},
  titre: {},
  listefichesliees: {
    id: { label: "id du formulaire lié" },
    query: {
      label: "Query",
      placeholder: "Voir doc sur https://yeswiki.net/?DocQuery/iframe"
    },
    param: {
      label: "Params de l'action",
      placeholder: 'Exple: champs="bf_nom" ordre="desc"'
    },
    number: { label: "Nombre de fiches à ficher", placeholder: "" },
    template: {
      label: "Template de restitution",
      placeholder:
        'Exple: template="liste_liens.tpl.html (par défault = accordéon)"'
    },
    type_link: {
      label: "Type de fiche liee",
      placeholder:
        "mettre checkbox ici si vos fiches liées le sont via un checkbox"
    }
  }
};

// How a field is represented in the formBuilder view
var templates = {
  champs_mail: function(fieldData) {
    return { field: `<div class="control-group form-group input text">
    <label class="control-label col-sm-3">` + (fieldData.value || "") + `<span class="symbole_obligatoire"></span></label>
    <div class="controls col-sm-9">
      <input class="form-control input-xxlarge" id="` + fieldData.name + `" type="email" value="" />
    </div>
  </div>` };
  },
  carte_google: function(fieldDate) {
    return {
      field:
        "Geolocation à partir d'un champ bf_adresse1 (ou bf_adresse2) et/ou bf_ville et/ou bf_pays"
    };
  },
  image: function(fieldDate) {
    return { field: '<input type="file"/>' };
  },
  text: function(fieldData) {
    var string = '<input type="' + fieldData.subtype + '"';
    if (fieldData.subtype == "url")
      string += 'placeholder="' + (fieldData.value || "") + '"/>';
    else if (fieldData.subtype == "range")
      string +=
        'min="' +
        (fieldData.size || "") +
        '" max="' +
        (fieldData.maxlength || "") +
        '"/>';
    else string += 'value="' + fieldData.value + '"/>';
    return { field: `<div class="control-group form-group input text">
    <label class="control-label col-sm-3">` + (fieldData.value || "") + `<span class="symbole_obligatoire"></span></label>
    <div class="controls col-sm-9">
      `+string+`
    </div>
  </div>` };
  },
  tags: function(field) {
    return { field: "<input/>" };
  },
  inscriptionliste: function(field) {
    return { field: '<input type="checkbox"/>' };
  },
  labelhtml: function(field) {
    return {
      field:
        "<xmp>" +
        (field.content_saisie || "") +
        "</xmp><xmp>" +
        (field.content_display || "") +
        "</xmp>"
    };
  },
  utilisateur_wikini: function(field) {
    return { field: "" };
  },
  acls: function(field) {
    return { field: "" };
  },
  metadatas: function(field) {
    return { field: "" };
  },
  bookmarklet: function(field) {
    return { field: "" };
  },
  listefichesliees: function(field) {
    return { field: "" };
  },
  collaborative_doc: function(field) {
    return { field: "Document collaboratif" };
  },
  titre: function(field) {
    return { field: field.value };
  },
};

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
  14: "semantic"
};
var lists = { ...defaultMapping, ...{ 1: "listeOrFormId", 6: "name" } };
var yesWikiMapping = {
  text: defaultMapping,
  number: defaultMapping,
  textarea: defaultMapping,
  champs_mail: {
    ...defaultMapping,
    ...{ 6: "replace_email_by_button", 9: "send_form_content_to_this_email" }
  },
  carte_google: {
    0: "type",
    1: "name_latitude",
    2: "name_longitude",
    3: "?",
    4: "?"
  },
  date: { ...defaultMapping, ...{ 5: "today_button" } },
  image: {
    ...defaultMapping,
    ...{
      3: "thumb_height",
      4: "thumb_width",
      5: "resize_height",
      6: "resize_width",
      7: "align"
    }
  },
  select: lists,
  "checkbox-group": lists,
  "radio-group": lists,
  textarea: { ...defaultMapping, ...{ 4: "rows", 7: "syntax" } },
  file: { ...defaultMapping, ...{ 3: "maxsize" } },
  tags: defaultMapping,
  inscriptionliste: {
    0: "type",
    1: "subscription_email",
    2: "label",
    3: "email_field_id",
    4: "mailing_list_tool"
  },
  labelhtml: { 0: "type", 1: "content_saisie", 2: "", 3: "content_display" },
  utilisateur_wikini: { 0: "type", 1: "name_field", 2: "email_field" },
  titre: { 0: "type", 1: "value" },
  acls: { 0: "type", 1: "read", 2: "write", 3: "comment" },
  metadatas: { 0: "type", 1: "theme", 2: "squelette", 3: "style", 4: "image" },
  hidden: { 0: "type", 1: "name", 2: "value" },
  bookmarklet: { 0: "type", 1: "name", 2: "label", 3: "value" },
  listefichesliees: {
    0: "type",
    1: "id",
    2: "query",
    3: "param",
    4: "number",
    5: "template",
    6: "type_link"
  },
  collaborative_doc: defaultMapping
};
// Mapping betwwen yeswiki field type and standard field implemented by form builder
var yesWikiTypes = {
  texte: { type: "text", subtype: "text" },
  lien_internet: { type: "text", subtype: "url" },
  mot_de_passe: { type: "text", subtype: "password" },
  // "nombre": { type: "text", subtype: "tel" },
  "textelong": { type: "textarea", subtype: "textarea"},
  "listedatedeb": { type: "date"},
  "listedatefin": { type: "date"},
  "jour": { type: "date"},
  "checkbox": { type: "checkbox-group", subtype2: "list"},
  "liste": { type: "select", subtype2: "list"},
  "radio": { type: "radio-group", subtype2: "list"},
  "checkboxfiche": { type: "checkbox-group", subtype2: "form"},
  "listefiche": { type: "select", subtype2: "form"},
  "radiofiche": { type: "radio-group", subtype2: "form"},
  "fichier": { type: "file", subtype: "file" },
  "champs_cache": { type: "hidden" }
}

function initializeFormbuilder(formAndListIds) {
  // FormBuilder conf
  formBuilder = $formBuilderContainer.formBuilder({
    showActionButtons: false,
    fields: fields,
    // i18n: { locale: 'fr-FR' },
    templates: templates,
    disableFields: [
      "number",
      "button",
      "autocomplete",
      "checkbox",
      "paragraph",
      "header",
      "collaborative_doc"
    ],
    controlOrder: ["text", , "date", "image", "champs_mail", "tags"],
    disabledAttrs: [
      "access",
      "placeholder",
      "className",
      "inline",
      "toggle",
      "description",
      "other",
      "multiple"
    ],
    typeUserAttrs: typeUserAttrs
  });

  // Each 300ms update the text field converting form bulder content into wiki syntax
  var formBuilderInitialized = false;
  setInterval(function() {
    if (!formBuilder || !formBuilder.actions || !formBuilder.actions.setData)
      return;
    if (!formBuilderInitialized) {
      initializeBuilderFromTextInput();
      formBuilderInitialized = true;
    }
    if ($formBuilderTextInput.is(":focus")) return;
    ensureFieldsNamesAreUnique();

    var formData = formBuilder.actions.getData();
    var wikiText = formatJsonDataIntoWikiText(formData);
    if (wikiText) $formBuilderTextInput.val(wikiText);

    // when selecting between data source lists or forms, we need to populate again the listOfFormId select with the
    // proper set of options
    $(".radio-group-field, .checkbox-group-field, .select-field")
      .find("select[name=subtype2]:not(.initialized)")
      .change(function() {
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
        optionToAddToSelect.each(function() {
          var optionKey = $(this).attr("value");
          var optionLabel = $(this).text();
          var isSelected = optionKey == selectedValue;
          var newOption = new Option(optionLabel, optionKey, false, isSelected);
          visibleSelect.append(newOption);
        });
      })
      .trigger("change");

    $(".text-field select[name=subtype]:not(.initialized)")
      .change(function() {
        $(this).addClass("initialized");
        $parent = $(this).closest(".form-field");
        if ($(this).val() == "range") {
          $parent.find(".maxlength-wrap label").text("Valeur max");
          $parent.find(".size-wrap label").text("Valeur min");
        } else {
          $parent.find(".maxlength-wrap label").text("Longueur Max.");
          $parent.find(".size-wrap label").text("Nbre Caractères Visibles");
        }
        if ($(this).val() == "color") {
          $parent.find(".maxlength-wrap, .size-wrap").hide();
        } else {
          $parent.find(".maxlength-wrap, .size-wrap").show();
        }
      })
      .trigger("change");

    // in semantic field, we want to separate value by coma
    $(".fld-semantic").each(function() {
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
      .addClass("glyphicon glyphicon-trash");
    $("a[type=copy].icon-copy").attr("title", "Dupliquer");
    $("a[type=edit].icon-pencil").attr("title", "Editer/Masquer");
  }, 300);

  $formBuilderTextInput.change(initializeBuilderFromTextInput);
}

function initializeBuilderFromTextInput() {
  var jsondData = parseWikiTextIntoJsonData($formBuilderTextInput.val());
  formBuilder.actions.setData(JSON.stringify(jsondData));
}

// prevent user to create two fields with the same name
function ensureFieldsNamesAreUnique() {
  // get all input names (used after for uniqueness)
  var allNames = [];
  $(".fld-name").each(function() {
    // Slugify
    var newValue = $(this)
      .val()
      .replace(/[^a-z^A-Z^_^0-9^{^}]/g, "_")
      .toLowerCase();
    $(this).val(newValue);
    // collect names
    allNames.push($(this).val());
  });

  $(".fld-name:visible").each(function() {
    // Check names are unique
    var count = 0,
      currValue = $(this).val();
    for (var i = 0; i < allNames.length; ++i)
      if (allNames[i] == currValue) count++;
    if (count > 1) $(this).val(currValue + "_bis");
  });
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

    var wikiType = formElement.type;
    for (var type in yesWikiTypes)
      if (
        formElement.type == yesWikiTypes[type].type &&
        (!formElement.subtype ||
          formElement.subtype == yesWikiTypes[type].subtype) &&
        (!formElement.subtype2 ||
          formElement.subtype2 == yesWikiTypes[type].subtype2)
      )
        wikiType = type;

    wikiProps[0] = wikiType;

    for (var key in mapping) {
      var property = mapping[key];
      if (property != "type") {
        var value = formElement[property];
        if (["required", "access"].indexOf(property) > -1)
          value = value ? "1" : "0";
        wikiProps[key] = value;
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
  console.log(text);
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
      var mapping = yesWikiMapping[fieldType];

      fieldObject["type"] = fieldType;
      fieldObject["subtype"] =
        wikiType in yesWikiTypes ? yesWikiTypes[wikiType].subtype : "";
      fieldObject["subtype2"] =
        wikiType in yesWikiTypes ? yesWikiTypes[wikiType].subtype2 : "";
      for (var j = 1; j < fieldValues.length; j++) {
        var value = fieldValues[j];
        var field = mapping && j in mapping ? mapping[j] : j;
        if (field == "required") value = value == "1" ? true : false;
        if (field) fieldObject[field] = value;
      }
      if (!fieldObject.label) {
        fieldObject.label = wikiType;
        for (var k = 0; k < fields.length; k++)
          if (fields[k].name == wikiType) fieldObject.label = fields[k].label;
      }
      result.push(fieldObject);
    }
  }
  console.log("parse result", result);
  return result;
}
