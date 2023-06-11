import text from './fields/text.js'
import textarea from './fields/textarea.js'
import date from './fields/date.js'
import image from './fields/image.js'
import url from './fields/url.js'
import file from './fields/file.js'
import champs_mail from './fields/champs_mail.js'
import select from './fields/select.js'
import checkbox_group from './fields/checkbox-group.js'
import radio_group from './fields/radio-group.js'
import map from './fields/map.js'
import tags from './fields/tags.js'
import labelhtml from './fields/labelhtml.js'
import titre from './fields/titre.js'
import bookmarklet from './fields/bookmarklet.js'
import conditionschecking from './fields/conditionschecking.js'
import calc from './fields/calc.js'
import reactions from './fields/reactions.js'
import inscriptionliste from './fields/inscriptionliste.js'
import utilisateur_wikini from './fields/utilisateur_wikini.js'
import acls from './fields/acls.js'
import metadatas from './fields/metadatas.js'
import listefichesliees from './fields/listefichesliees.js'
import custom from './fields/custom.js'
import tabs from './fields/tabs.js'
import tabchange from './fields/tabchange.js'

import { defaultMapping } from './fields/commons/attributes.js'

const $formBuilderTextInput = $('#form-builder-text')
let formBuilder

// Use window to make it available outside of module, so extension can adds their own fields
window.formBuilderFields = {
  text, textarea, date, image, url, file, champs_mail, select,
  'checkbox-group': checkbox_group, 'radio-group': radio_group,
  map, tags, labelhtml, titre, bookmarklet, conditionschecking, calc,
  reactions, inscriptionliste, utilisateur_wikini, acls, metadatas,
  listefichesliees, custom, tabs, tabchange
}

function mapFieldsConf(callback) {
  return Object.fromEntries(
    Object.entries(formBuilderFields).map(([name, conf]) => [name, callback(conf)])
      .filter(([name, conf]) => !!conf)
  )
}

// Define an entire group of fields to be added to the stage at a time.
// Use window to make it available outside of module, so extension can adds their own fields
window.inputSets = Object.values(formBuilderFields).map((conf) => conf.set).filter((f) => !!f)

// Use window to make it available outside of module, so extension can adds their own fields
window.yesWikiMapping = mapFieldsConf((conf) => conf.attributesMapping || defaultMapping)

// Mapping betwwen yeswiki field type and standard field implemented by form builder
// Use window to make it available outside of module, so extension can adds their own fields
window.yesWikiTypes = {
  lien_internet: { type: 'url' },
  lien_internet_bis: { type: 'text', subtype: 'url' },
  mot_de_passe: { type: 'text', subtype: 'password' },
  // "nombre": { type: "text", subtype: "tel" },
  texte: { type: 'text' }, // all other type text subtype (range, text, tel)
  textelong: { type: 'textarea', subtype: 'textarea' },
  listedatedeb: { type: 'date' },
  listedatefin: { type: 'date' },
  jour: { type: 'date' },
  map: { type: 'map' },
  carte_google: { type: 'map' },
  checkbox: { type: 'checkbox-group', subtype2: 'list' },
  liste: { type: 'select', subtype2: 'list' },
  radio: { type: 'radio-group', subtype2: 'list' },
  checkboxfiche: { type: 'checkbox-group', subtype2: 'form' },
  listefiche: { type: 'select', subtype2: 'form' },
  radiofiche: { type: 'radio-group', subtype2: 'form' },
  fichier: { type: 'file', subtype: 'file' },
  champs_cache: { type: 'hidden' },
  listefiches: { type: 'listefichesliees' }
}

const defaultFieldsName = {
  textarea: 'bf_description',
  image: 'bf_image',
  champs_mail: 'bf_mail',
  date: 'bf_date_debut_evenement'
}

const I18nOption = {
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
  zh: 'zh-CN'
}

function copyMultipleSelectValues(currentField) {
  const currentId = $(currentField).prop('id')
  // based on formBuilder/Helpers.js 'incrementId' function
  const split = currentId.lastIndexOf('-')
  const clonedFieldNumber = parseInt(currentId.substring(split + 1)) - 1
  const baseString = currentId.substring(0, split)
  const clonedId = `${baseString}-${clonedFieldNumber}`

  // find cloned field
  const clonedField = $(`#${clonedId}`)
  if (clonedField.length > 0) {
    // copy multiple select
    const clonedFieldSelects = $(clonedField).find('select[multiple=true]')
    clonedFieldSelects.each(function() {
      const currentSelect = $(currentField).find(`select[multiple=true][name=${$(this).prop('name')}]`)
      currentSelect.val($(this).val())
    })
  }
}

const typeUserEvents = {}
Object.keys(formBuilderFields).forEach((field) => {
  typeUserEvents[field] = { onclone: copyMultipleSelectValues }
})

function initializeFormbuilder() {
  // FormBuilder conf
  formBuilder = $('#form-builder-container').formBuilder({
    showActionButtons: false,
    fields: Object.values(formBuilderFields).map((conf) => conf.field).filter((f) => !!f),
    controlOrder: Object.keys(formBuilderFields),
    typeUserAttrs: mapFieldsConf((conf) => conf.attributes),
    typeUserDisabledAttrs: mapFieldsConf((conf) => conf.disabledAttributes),
    typeUserEvents,
    inputSets,
    templates: mapFieldsConf((conf) => conf.renderInput),
    i18n: {
      locale: I18nOption[wiki.locale] ?? 'fr-FR',
      location: `${wiki.baseUrl.replace('?', '')}javascripts/vendor/formbuilder-languages/`
    },
    // Disable some default fields of Jquery formBuilder
    disableFields: [
      'number',
      'button',
      'autocomplete',
      'checkbox',
      'paragraph',
      'header',
      'textarea',
      'checkbox-group',
      'radio-group',
      'select',
      'hidden'
    ],
    // disbale some default attributes of Jquery formBuilder for all fields
    disabledAttrs: [
      'access',
      'placeholder',
      'className',
      'inline',
      'toggle',
      'description',
      'other',
      'multiple'
    ],
    onAddField(fieldId, field) {
      if (!field.hasOwnProperty('read')) {
        field.read = [' * ']// everyone by default
      }
      if (!field.hasOwnProperty('write')) {
        field.write = (field.type === 'champs_mail')
          ? [' % '] // owner and @admins by default for e-mail
          : [' * '] // everyone by default
      }
      if (field.type === 'acls' && !field.hasOwnProperty('comment')) {
        field.comment = ['comments-closed']// comments-closed by default
      }
      if (field.type === 'champs_mail' && !('seeEmailAcls' in field)) {
        field.seeEmailAcls = [' % ']// owner and @admins by default
      }
    }
  })

  // disable bf_titre identifier
  $('.fld-name').each(function() {
    if ($(this).val() === 'bf_titre') {
      $(this).attr('disabled', true)
    }
  })

  // Each 300ms update the text field converting form bulder content into wiki syntax
  let formBuilderInitialized = false
  let existingFieldsNames = []; let
    existingFieldsIds = []

  setInterval(() => {
    if (!formBuilder || !formBuilder.actions || !formBuilder.actions.setData) return
    if (!formBuilderInitialized) {
      initializeBuilderFromTextInput()
      existingFieldsIds = getFieldsIds()
      formBuilderInitialized = true
    }
    if ($formBuilderTextInput.is(':focus')) return
    // Change names
    $('.form-group.name-wrap label').text(_t('BAZ_FORM_EDIT_UNIQUE_ID'))
    $('.form-group.label-wrap label').text(_t('BAZ_FORM_EDIT_NAME'))
    existingFieldsNames = []
    $('.fld-name').each(function() { existingFieldsNames.push($(this).val()) })

    // Transform input[textarea] in real textarea
    $('input[type="textarea"]').replaceWith(function() {
      const domTextarea = document.createElement('textarea')
      domTextarea.id = this.id
      domTextarea.name = this.name
      domTextarea.value = this.value
      domTextarea.classList = this.classList
      domTextarea.title = this.title
      domTextarea.rows = $(this).attr('rows')
      return domTextarea
    })

    // Slugiy field names
    $('.fld-name').each(function() {
      const newValue = $(this)
        .val()
        .replace(/[^a-z^A-Z^_^0-9^{^}]/g, '_')
        .toLowerCase()
      $(this).val(newValue)
    })

    if ($('#form-builder-container').is(':visible')) {
      const formData = formBuilder.actions.getData()
      const wikiText = formatJsonDataIntoWikiText(formData)
      if (wikiText) $formBuilderTextInput.val(wikiText)
    }

    // when selecting between data source lists or forms, we need to populate again the listOfFormId select with the
    // proper set of options
    $('.radio-group-field, .checkbox-group-field, .select-field')
      .find('select[name=subtype2]:not(.initialized)')
      .change(function() {
        $(this).addClass('initialized')
        const visibleSelect = $(this)
          .closest('.form-field')
          .find('select[name=listeOrFormId]')
        selectedValue = visibleSelect.val()
        visibleSelect.empty()
        const optionToAddToSelect = $(this)
          .closest('.form-field')
          .find(`select[name=${$(this).val()}Id] option`)
        visibleSelect.append(new Option('', '', false))
        optionToAddToSelect.each(function() {
          const optionKey = $(this).attr('value')
          const optionLabel = $(this).text()
          const isSelected = optionKey == selectedValue
          const newOption = new Option(optionLabel, optionKey, false, isSelected)
          visibleSelect.append(newOption)
        })
      })
      .trigger('change')

    $('.fld-name').each(function() {
      let name = $(this).val()
      const id = $(this).closest('.form-field').attr('id')

      // Detect new fields added
      if (!existingFieldsIds.includes(id)) {
        const fieldType = $(this).closest('.form-field').attr('type')

        // Make the default names easier to read
        if (['radio_group', 'checkbox_group', 'select'].includes(fieldType)) {
          name = ''
        } else if (!name.includes('bf_')) {
          name = defaultFieldsName[fieldType] || `bf_${fieldType}`
          if (existingFieldsNames.includes(name)) {
            // If name already exist, we add a number (bf_address, bf_address1, bf_address2...)
            number = 1
            while (existingFieldsNames.includes(name + number)) number += 1
            name += number
          }
        }

        // if it's a map, we automatically add a bf_addresse
        if (fieldType == 'map' && !existingFieldsNames.includes('bf_adresse')) {
          const field = {
            type: 'text',
            subtype: 'text',
            name: 'bf_adresse',
            label: _t('BAZ_FORM_EDIT_ADDRESS')
          }
          const index = $(this).closest('.form-field').index()
          formBuilder.actions.addField(field, index)
        }
      }
      $(this).val(name)
    })

    existingFieldsIds = getFieldsIds()

    $('.text-field select[name=subtype]:not(.initialized)').on('change', function() {
        $(this).addClass('initialized')
        const $parent = $(this).closest('.form-field')
        if ($(this).val() == 'range' || $(this).val() == 'number') {
          $parent.find('.maxlength-wrap label').text(_t('BAZ_FORM_EDIT_MAX_VAL'))
          $parent.find('.size-wrap label').text(_t('BAZ_FORM_EDIT_MIN_VAL'))
        } else {
          $parent.find('.maxlength-wrap label').text(_t('BAZ_FORM_EDIT_MAX_LENGTH'))
          $parent.find('.size-wrap label').text(_t('BAZ_FORM_EDIT_NB_CHARS'))
        }
        if ($(this).val() == 'color') {
          $parent.find('.maxlength-wrap, .size-wrap').hide()
        } else {
          $parent.find('.maxlength-wrap, .size-wrap').show()
        }
      })
      .trigger('change')

    // in semantic field, we want to separate value by coma
    $('.fld-semantic').each(function() {
      let newVal = $(this)
        .val()
        .replace(/\s*,\s*/g, ',')
      newVal = newVal.replace(/\s+/g, ',')
      newVal = newVal.replace(/,+/g, ',')
      $(this).val(newVal)
    })

    // Changes icons and icones helpers
    $('a[type=remove].icon-cancel')
      .removeClass('icon-cancel')
      .html('<i class="fa fa-trash"></i>')
    $('a[type=copy].icon-copy').attr('title', _t('DUPLICATE'))
    $('a[type=edit].icon-pencil').attr('title', _t('BAZ_FORM_EDIT_HIDE'))
  }, 300)

  $('#formbuilder-link').click(initializeBuilderFromTextInput)
}

document.addEventListener("DOMContentLoaded", function() {
  initializeFormbuilder();
})

function getFieldsIds() {
  let result = []
  $('.fld-name').each(function() { result.push($(this).closest('.form-field').attr('id')) })
  return result
}

// Remove accidental br at the end of the labels
function removeBR(text) {
  let newValue = text.replace(/(<div><br><\/div>)+$/g, '')
  // replace multiple '<div><br></div>' when at the end of the value
  newValue = newValue.replace(/(<br>)+$/g, '')
  // replace multiple '<br>' when at the end of the value
  return newValue
}

function initializeBuilderFromTextInput() {
  const jsonData = parseWikiTextIntoJsonData($formBuilderTextInput.val())
  formBuilder.actions.setData(JSON.stringify(jsonData))
}

// transform a json object like "{ type: 'texte', name: 'bf_titre', label: 'Nom' .... }"
// into wiki text like "texte***bf_titre***Nom***255***255*** *** *** ***1***0***"
function formatJsonDataIntoWikiText(formData) {
  if (formData.length == 0) return null
  let wikiText = ''

  for (let i = 0; i < formData.length; i++) {
    const wikiProps = {}
    const formElement = formData[i]
    const mapping = yesWikiMapping[formElement.type]

    for (const type in yesWikiTypes) {
      if (
        formElement.type == yesWikiTypes[type].type
        && (!formElement.subtype
          || !yesWikiTypes[type].subtype
          || formElement.subtype == yesWikiTypes[type].subtype)
        && (!formElement.subtype2
          || formElement.subtype2 == yesWikiTypes[type].subtype2)
      ) {
        wikiProps[0] = type
        break
      }
    }
    // for non mapped fields, we just keep the form type
    if (!wikiProps[0]) wikiProps[0] = formElement.type

    // fix for url field which can be build with textField or urlField
    if (wikiProps[0]) wikiProps[0] = wikiProps[0].replace('_bis', '')

    for (const key in mapping) {
      const property = mapping[key]
      if (property != 'type') {
        let value = formElement[property]
        if (['required', 'access'].indexOf(property) > -1) value = value ? '1' : '0'
        if (property == 'label') {
          wikiProps[key] = removeBR(value).replace(/\n$/gm, '')
        } else {
          wikiProps[key] = value
        }
      }
    }

    const maxProp = Math.max.apply(Math, Object.keys(wikiProps))
    for (let j = 0; j <= maxProp; j++) {
      wikiText += wikiProps[j] || ' '
      wikiText += '***'
    }
    wikiText += '\n'
  }
  return wikiText
}

// transform text with wiki text like "texte***bf_titre***Nom***255***255*** *** *** ***1***0***"
// into a json object "{ type: 'texte', name: 'bf_titre', label: 'Nom' .... }"
function parseWikiTextIntoJsonData(text) {
  const result = []
  var text = text.trim()
  const textFields = text.split('\n')
  for (let i = 0; i < textFields.length; i++) {
    const textField = textFields[i]
    const fieldValues = textField.split('***')
    const fieldObject = {}
    if (fieldValues.length > 1) {
      const wikiType = fieldValues[0]
      let fieldType = wikiType in yesWikiTypes ? yesWikiTypes[wikiType].type : wikiType
      // check that the fieldType really exists in our form builder
      if (!(fieldType in yesWikiMapping)) fieldType = 'custom'

      const mapping = yesWikiMapping[fieldType]

      fieldObject.type = fieldType
      fieldObject.subtype = wikiType in yesWikiTypes ? yesWikiTypes[wikiType].subtype : ''
      fieldObject.subtype2 = wikiType in yesWikiTypes ? yesWikiTypes[wikiType].subtype2 : ''
      const start = fieldType == 'custom' ? 0 : 1
      for (let j = start; j < fieldValues.length; j++) {
        let value = fieldValues[j]
        const field = mapping && j in mapping ? mapping[j] : j
        if (field == 'required') value = value == '1'
        if (field) {
          if (field == 'read' || field == 'write' || field == 'comment') {
            fieldObject[field] = (value.trim() === '')
              ? (
                field == 'comment'
                  ? [' + ']
                  : [' * ']
              )
              : value.split(',').map((e) => ((['+', '*', '%'].includes(e.trim())) ? ` ${e.trim()} ` : e))
          } else if (field == 'seeEmailAcls') {
            fieldObject[field] = (value.trim() === '')
              ? ' % ' // if not define in tempalte, choose owner and admins
              : value.split(',').map((e) => ((['+', '*', '%'].includes(e.trim())) ? ` ${e.trim()} ` : e))
          } else {
            fieldObject[field] = value
          }
        }
      }
      if (!fieldObject.label) {
        fieldObject.label = wikiType
        for (let k = 0; k < fields.length; k++) if (fields[k].name == wikiType) fieldObject.label = fields[k].label
      }
      result.push(fieldObject)
    }
  }
  if (wiki.isDebugEnabled) {
    console.log('parse result', result)
  }
  return result
}

$('a[href="#formbuilder"]').on('click', (event) => {
  if (!confirm(_t('BAZ_FORM_EDIT_CONFIRM_DISPLAY_FORMBUILDER'))) {
    event.preventDefault()
    return false
  }
})
