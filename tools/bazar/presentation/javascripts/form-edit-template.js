var $formBuilderTextInput = $('#form-builder-text')
var $formBuilderContainer = $('#form-builder-container')
var formBuilder;

$(document).ready(function() {
  var visibilityOptions = {
    '': 'Tout le monde',
    '+': 'Utilisateurs identifiés',
    '%': 'Propriétaire de la fiche et admins',
    '@admins': 'Membre du groupe admin'
  }

  var fields = [
    { label: 'Titre', name: "titre", attrs: { type: 'titre' }, icon: '*' },
    { label: 'Carte Geolocalisation', name: "carte_google", attrs: { type: 'carte_google' }, icon: '*' },
  ];

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
      read: { label: 'Peut lire', options: visibilityOptions },
      write: { label: 'Peut écrire', options: visibilityOptions }
    },
    champs_mail: {
      hint: { label: "Texte d'aide" },
      separator: { label: '' }, // separate important attrs from others
      replace_email_by_button: { label: "Remplacer l'email par un bouton contact", options: { '': 'Non', 'form': 'Oui' } },
      send_form_content_to_this_email: { label: "Envoyer le contenu du formulaire à cet email", options: { '': 'Non', '1': 'Oui' } },
      read: { label: 'Peut lire', options: visibilityOptions },
      write: { label: 'Peut écrire', options: visibilityOptions }
    },
    carte_google: {
      name_latitude: { label: "Nom champ latitude" },
      name_longitude: { label: "Nom champ longitude" },
    }
  }

  var templates = {
    titre: function(fieldData) { return { field: '' }; },
    champs_mail: function(fieldData) { return { field: '<input id="' + fieldData.name + '"' + ' type="email"/>' }; },
    carte_google: function(fieldDate) { return { field: 'Geoloc'} },
  };

  formBuilder = $formBuilderContainer.formBuilder({
    showActionButtons: false,
    fields: fields,
    i18n: { locale: 'fr-FR' },
    templates: templates,
    disableFields: ['carte_google', 'titre', 'hidden', 'button', 'autocomplete'],
    controlOrder: ['text', 'number', 'date'], // 'email', 'number', 'textarea', 'checkbox', 'checkbox-group', 'radio-group', 'select', 'date'],
    disabledAttrs: ['access', 'placeholder', 'className', 'inline', 'toggle', 'description', 'other', 'multiple'],
    typeUserAttrs: typeUserAttrs,
  });

  setInterval(function() {
    if (!formBuilder || !formBuilder.actions) return;
    if ($formBuilderTextInput.is(':focus')) return;
    ensureFieldsNamesAreUnique();

    var formData = formBuilder.actions.getData()
    var wikiText = formatJsonDataIntoWikiText(formData);
    if (wikiText) $formBuilderTextInput.val(wikiText);

   }, 300);

   $formBuilderTextInput.change(initializeBuilderFromTextInput)
   setTimeout(initializeBuilderFromTextInput, 500);
});

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
var yesWikiMapping = {
  "titre": { 0: "type", 1: "label"},
  "text": defaultMapping,
  "number": defaultMapping,
  "textarea": defaultMapping,
  "champs_mail": {...defaultMapping, ...{ 6: "replace_email_by_button", 9: "send_form_content_to_this_email"}},
  "carte_google": { 0: "type", 1: "name_latitude", 2: "name_longitude", 3: '?', 4: '?' }
}
var yesWikiTypes = {
  "texte": { type: "text", subtype: "text" },
  "lien_internet": { type: "text", subtype: "url" },
  "mot_de_passe": { type: "text", subtype: "password" },
  "nombre": { type: "text", subtype: "tel" },
  "textelong": { type: "textarea"},
}

// transform a json object like "{ type: 'texte', name: 'bf_titre', label: 'Nom' .... }"
// into wiki text like "texte***bf_titre***Nom***255***255*** *** *** ***1***0***"
function formatJsonDataIntoWikiText(formData) {
  if (formData.length == 0) return null;
  console.log(formData);
  var wikiText = "";

  for(var i = 0; i < formData.length; i++)
  {
    var wikiProps = {};
    var formElement = formData[i];
    var mapping = yesWikiMapping[formElement.type]

    var wikiType = formElement.type
    for(var type in yesWikiTypes)
      if (formElement.type == yesWikiTypes[type].type && (!formElement.subtype || formElement.subtype == yesWikiTypes[type].subtype)) wikiType = type

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
      fieldObject["subtype"] = "text"; //(wikiType in yesWikiTypes) ? yesWikiTypes[wikiType].subtype : "text";
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
