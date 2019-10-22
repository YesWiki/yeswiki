var $formBuilderTextInput = $('#form-builder-text')
var $formBuilderContainer = $('#form-builder-container')
var formBuilder;

function initializeFormbuilder(formAndListIds)
{
  // Custom fields to add to form builder
  var fields = [
    { label: 'Titre', name: "titre", attrs: { type: 'titre' }, icon: '*' },
    { label: 'Carte Geolocalisation', name: "carte_google", attrs: { type: 'carte_google' }, icon: '*' },
    { label: 'Image', name: "image", attrs: { type: 'image' }, icon: '*' },
    { label: 'Email', name: "champs_mail", attrs: { type: 'champs_mail' }, icon: '@' },
  ];

  // Some attributes configuration used in multiple fields
  var visibilityOptions = {
    '': 'Tout le monde',
    '+': 'Utilisateurs identifiés',
    '%': 'Propriétaire de la fiche et admins',
    '@admins': 'Membre du groupe admin'
  }
  var readConf = { label: 'Peut être lu par', options: visibilityOptions }
  var writeconf = { label: 'Peut être saisi par', options: visibilityOptions }
  var searchableConf = { label: 'Présence dans le moteur de recherche', options: { '': 'Non', '1': 'Oui' } }

  var selectConf = {
    subtype2: { label: 'Origine des données', options: {
      'list': 'Une liste',
      'form': 'Un Formulaire Bazar'
      },
    },
    listeOrFormId: { label: 'Choix de la liste/du formulaire', options: {...{ '': ''}, ...formAndListIds.lists, ...formAndListIds.forms} },
    listId: { label: '', options: formAndListIds.lists },
    formId: { label: '', options: formAndListIds.forms },
    hint: { label: "Texte d'aide" },
    read: readConf,
    write: writeconf,
    // searchable: searchableConf -> 10/19 Florian say that this conf is not working for now
  }

  // Attributes to be configured for each field
  var typeUserAttrs = {
    text: {
      maxlength: { label: "Longueur Max."},
      hint: { label: "Texte d'aide" },
      separator: { label: '' }, // separate important attrs from others
      subtype: { label: 'Type', options: {
          'text': 'Texte',
          'tel': 'Téléphone',
          'url': 'Url',
          'password': "Mot de passe"
        },
      },
      read: readConf,
      write: writeconf,
      size: { label: "Nbre caractères visibles"},
    },
    champs_mail: {
      hint: { label: "Texte d'aide" },
      separator: { label: '' }, // separate important attrs from others
      replace_email_by_button: { label: "Remplacer l'email par un bouton contact", options: { '': 'Non', 'form': 'Oui' } },
      send_form_content_to_this_email: { label: "Envoyer le contenu du formulaire à cet email", options: { '': 'Non', '1': 'Oui' } },
      // searchable: searchableConf, -> 10/19 Florian say that this conf is not working for now
      read: readConf,
      write: writeconf,
    },
    carte_google: {
      name_latitude: { label: "Nom champ latitude" },
      name_longitude: { label: "Nom champ longitude" },
    },
    date: {
      today_button: { label: "Btn Aujourd'hui", options: { '': 'Non', '1': 'Oui' } },
      read: readConf,
      write: writeconf,
    },
    image: {
      hint: { label: "Texte d'aide" },
      thumb_height: { label: "Hauteur Vignette", value: "140" },
      thumb_width: { label: "Largeur Vignette", value: "140" },
      resize_height: { label: "Hauteur redimension", value: "600" },
      resize_width: { label: "Largeur redimension", value: "600" },
      align: { label: "Alignement", value: 'right', options: { 'left': "Gauche", 'center': "Centre", 'right': 'Droite'} }
    },
    select: selectConf,
    'checkbox-group': selectConf,
    'radio-group': selectConf
  }

  // How a field is represented in the formBuilder view
  var templates = {
    titre: function(fieldData) { return { field: '' }; },
    champs_mail: function(fieldData) { return { field: '<input id="' + fieldData.name + '"' + ' type="email"/>' }; },
    carte_google: function(fieldDate) { return { field: 'Geoloc'} },
    image: function(fieldDate) { return { field: '<input type="file"/>' }},
    text: function(fieldData) {
      var string = '<input type="' + fieldData.subtype + '"';
      if (fieldData.subtype == "url")
        string += 'placeholder="' + (fieldData.value || '') + '"/>';
      else
        string += 'value="' + fieldData.value + '"/>';
      return { field:  string }
    },
  };

  // FormBuilder conf
  formBuilder = $formBuilderContainer.formBuilder({
    showActionButtons: false,
    fields: fields,
    // i18n: { locale: 'fr-FR' },
    templates: templates,
    disableFields: ['carte_google', 'titre', 'hidden', 'button', 'autocomplete', 'checkbox', 'paragraph', 'header'],
    controlOrder: ['text', 'number', 'date', 'image', 'champs_mail'],
    disabledAttrs: ['access', 'placeholder', 'className', 'inline', 'toggle', 'description', 'other', 'multiple'],
    typeUserAttrs: typeUserAttrs,
  });

  // Each 300ms update the text field converting form bulder content into wiki syntax
  var formBuilderInitialized = false;
  setInterval(function() {
    if (!formBuilder || !formBuilder.actions || !formBuilder.actions.setData) return;
    if (!formBuilderInitialized) { initializeBuilderFromTextInput(); formBuilderInitialized = true; }
    if ($formBuilderTextInput.is(':focus')) return;
    ensureFieldsNamesAreUnique();

    var formData = formBuilder.actions.getData()
    var wikiText = formatJsonDataIntoWikiText(formData);
    if (wikiText) $formBuilderTextInput.val(wikiText);

    // when selecting betwwen data source lists or forms, we need to populate again the listOfFormId select with the
    // proper set of options
    $('.radio-group-field, .checkbox-group-field, .select-field').find('select[name=subtype2]:not(.initialized)').change(function() {
      $(this).addClass('initialized');
      var visibleSelect = $(this).closest('.form-field').find('select[name=listeOrFormId]')
      selectedValue = visibleSelect.val();
      visibleSelect.empty();
      var optionToAddToSelect = $(this).closest('.form-field').find('select[name=' + $(this).val() +'Id] option')
      visibleSelect.append(new Option('', '', false));
      optionToAddToSelect.each(function() {
        var optionKey = $(this).attr('value');
        var optionLabel = $(this).text();
        var isSelected = optionKey == selectedValue;
        var newOption = new Option(optionLabel, optionKey, false, isSelected);
        visibleSelect.append(newOption);
      })
    }).trigger('change');

   }, 300);

   $formBuilderTextInput.change(initializeBuilderFromTextInput)
}

function initializeBuilderFromTextInput()
{
  var jsondData = parseWikiTextIntoJsonData($formBuilderTextInput.val());
  formBuilder.actions.setData(JSON.stringify(jsondData));
}

// prevent user to create two fields with the same name
function ensureFieldsNamesAreUnique() {
  // get all input names (used after for uniqueness)
  var allNames = [];
  $('.fld-name').each(function() {
    // Slugify
    var newValue = $(this).val().replace(/[^a-z^A-Z^_^0-9^{^}]/g, '_').toLowerCase();
    $(this).val(newValue);
    // collect names
    allNames.push($(this).val());
  });

  $('.fld-name:visible').each(function() {
    // Check names are unique
    var count = 0, currValue = $(this).val();
    for(var i = 0; i < allNames.length; ++i) if (allNames[i] == currValue) count++;
    if (count > 1) $(this).val(currValue + "_bis");
  });
}

var defaultMapping = { 0: "type", 1: "name", 2: "label", 3: 'size', 4: 'maxlength', 5: 'value', 6: 'pattern',  8: 'required', 9: 'searchable', 10: 'hint', 11: 'read', 12: 'write' }
var lists = { ...defaultMapping, ...{ 1: "listeOrFormId", 6: 'name' } }
var yesWikiMapping = {
  "titre": { 0: "type", 1: "label"},
  "text": defaultMapping,
  "number": defaultMapping,
  "textarea": defaultMapping,
  "champs_mail": {...defaultMapping, ...{ 6: "replace_email_by_button", 9: "send_form_content_to_this_email"}},
  "carte_google": { 0: "type", 1: "name_latitude", 2: "name_longitude", 3: '?', 4: '?' },
  "date": {...defaultMapping, ...{ 5: 'today_button'}},
  "image": {...defaultMapping, ...{ 3: 'thumb_height', 4: 'thumb_width', 5: 'resize_height', 6: 'resize_width', 7: 'align'}},
  "select" : lists,
  "checkbox-group" : lists,
  "radio-group" : lists,
}
var yesWikiTypes = {
  "texte": { type: "text", subtype: "text" },
  "lien_internet": { type: "text", subtype: "url" },
  "mot_de_passe": { type: "text", subtype: "password" },
  "nombre": { type: "text", subtype: "tel" },
  "textelong": { type: "textarea"},
  "jour": { type: "date"},
  "checkbox": { type: "checkbox-group", subtype2: "list"},
  "liste": { type: "select", subtype2: "list"},
  "radio": { type: "radio-group", subtype2: "list"},
  "checkboxfiche": { type: "checkbox-group", subtype2: "form"},
  "listefiche": { type: "select", subtype2: "form"},
  "radiofiche": { type: "radio-group", subtype2: "form"},
}

// transform a json object like "{ type: 'texte', name: 'bf_titre', label: 'Nom' .... }"
// into wiki text like "texte***bf_titre***Nom***255***255*** *** *** ***1***0***"
function formatJsonDataIntoWikiText(formData) {
  if (formData.length == 0) return null;
  var wikiText = "";

  for(var i = 0; i < formData.length; i++)
  {
    var wikiProps = {};
    var formElement = formData[i];
    var mapping = yesWikiMapping[formElement.type]

    var wikiType = formElement.type
    for(var type in yesWikiTypes)
      if (   formElement.type == yesWikiTypes[type].type
        && (!formElement.subtype || formElement.subtype == yesWikiTypes[type].subtype)
        && (!formElement.subtype2 || formElement.subtype2 == yesWikiTypes[type].subtype2)) wikiType = type

    wikiProps[0] = wikiType;

    for(var key in mapping)
    {
      var property = mapping[key]
      if (property != 'type')
      {
        var value = formElement[property]
        if (["required", "access"].indexOf(property) > -1) value = value ? "1" : "0"
        wikiProps[key] = value
      }
    }

    maxProp = Math.max.apply(Math, Object.keys(wikiProps));
    for(var j = 0; j <= maxProp; j++)
    {
      wikiText += wikiProps[j] || " "
      wikiText += "***";
    }
    wikiText += "\n"
  }
  return wikiText;
}

// transform text with wiki text like "texte***bf_titre***Nom***255***255*** *** *** ***1***0***"
// into a json object "{ type: 'texte', name: 'bf_titre', label: 'Nom' .... }"
function parseWikiTextIntoJsonData(text)
{
  var result = []
  console.log(text);
  var text = text.trim();
  var textFields = text.split('\n')
  for(var i = 0; i < textFields.length; i++)
  {
    var textField = textFields[i];
    var fieldValues = textField.split('***');
    var fieldObject = {};
    if (fieldValues.length > 1)
    {
      var wikiType = fieldValues[0]
      var fieldType = (wikiType in yesWikiTypes) ? yesWikiTypes[wikiType].type : wikiType;
      var mapping = yesWikiMapping[fieldType];

      fieldObject["type"] = fieldType;
      fieldObject["subtype"] = (wikiType in yesWikiTypes) ? yesWikiTypes[wikiType].subtype : "";
      fieldObject["subtype2"] = (wikiType in yesWikiTypes) ? yesWikiTypes[wikiType].subtype2 : "";
      for(var j = 1; j < fieldValues.length; j++)
      {
        var value = fieldValues[j];
        var field = (mapping && j in mapping) ? mapping[j] : j;
        if (field) fieldObject[field] = value;
      }
      if (!fieldObject.label) fieldObject.label = '';
      result.push(fieldObject)
    }
  }
  console.log("parse result", result)
  return result;
}
