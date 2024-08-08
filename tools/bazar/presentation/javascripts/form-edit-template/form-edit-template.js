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

import { parseWikiTextIntoJsonData, formatJsonDataIntoWikiText } from './yeswiki-syntax-converter.js'
import {
  copyMultipleSelectValues,
  mapFieldsConf,
  addAdvancedAttributesSection,
  adjustDefaultAcls,
  adjustJqueryBuilderUI,
  convertToBytes
} from './form-builder-helper.js'
import { initListOrFormIdAttribute } from './attributes/list-form-id-attribute.js'
import I18nOption from './i18n.js'

const $formBuilderTextInput = $('#form-builder-text')
window.formBuilder = undefined
window.defaultImage = {}

// Use window to make it available outside of module, so extension can adds their own fields
window.formBuilderFields = {
  text,
  textarea,
  date,
  image,
  url,
  file,
  champs_mail,
  select,
  'checkbox-group': checkbox_group,
  'radio-group': radio_group,
  map,
  tags,
  labelhtml,
  titre,
  bookmarklet,
  conditionschecking,
  calc,
  reactions,
  inscriptionliste,
  utilisateur_wikini,
  acls,
  metadatas,
  listefichesliees,
  custom,
  tabs,
  tabchange
}

function initializeFormbuilder() {
  // Define an entire group of fields to be added to the stage at a time.
  const inputSets = Object.values(formBuilderFields).map((conf) => conf.set).filter((f) => !!f)

  const typeUserEvents = {}
  Object.keys(formBuilderFields).forEach((field) => {
    typeUserEvents[field] = { onclone: copyMultipleSelectValues }
  })

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
      adjustDefaultAcls(field)

      // strange bug with jQuery Formbuilder, the fieldId given is not the last field, but
      // the one just before... so incrementing the id manually
      // transform frmb-XXXX-fld-6  into frmb-XXXX-fld-7
      fieldId = fieldId.replace(/(.*)-fld-(\d+)$/gim, (string, formId, fieldId) => `${formId}-fld-${parseInt(fieldId, 10) + 1}`)

      // Timeout to wait the field ot be rendered
      setTimeout(() => {
        const $field = $(`#${fieldId}`)
        addAdvancedAttributesSection($field)
        initListOrFormIdAttribute($field)
        adjustJqueryBuilderUI($field)

        // disable bf_titre identifier edition
        $field.find('.fld-name').each(function() {
          if ($(this).val() === 'bf_titre') {
            $(this).attr('disabled', true)
          }
        })
      }, 0)
    },
    onOpenFieldEdit() {
      // Default image is in base64 in buffer variable => convert it to File in input element
      $('input.default-file').each((idx, file_elem) => {
        const current_ids = file_elem.attributes.id.value.split('-')
        const image_name = `${current_ids[current_ids.length - 2]}-${current_ids[current_ids.length - 1]}`
        if (window.defaultImage[image_name]) {
          const image_content = window.defaultImage[image_name].split('|')
          if (image_content.length === 2) {
            const dataTransfer = new DataTransfer()
            dataTransfer.items.add(new File(convertToBytes(image_content[1]), image_content[0]))
            file_elem.files = dataTransfer.files
          }
        }
        if (file_elem.parentElement.childElementCount == 1) {
        	const new_button = document.createElement('i')
    		new_button.className = 'fas fa-remove btn-img-remove'
    		new_button.onclick = function() {
            file_elem.files = new DataTransfer().files
            window.defaultImage[image_name] = ''
          }
        	file_elem.parentElement.append(new_button)
        }
      })
      // When change image, save it to base64 to buffer variable
      $('input.default-file').change((event) => {
        const current_ids = event.target.attributes.id.value.split('-')
        const image_name = `${current_ids[current_ids.length - 2]}-${current_ids[current_ids.length - 1]}`
        const file = event.target.files[0]
        if (typeof imageMaxSize == 'undefined') {
          var imageMaxSize = 1024 * 1024
        }
        if (file.size > imageMaxSize) {
          alert(_t('IMAGEFIELD_TOO_LARGE_IMAGE', { imageMaxSize }))
          event.target.value = ''
          return false
        }
        const reader = new FileReader()
        reader.readAsDataURL(file)
        reader.onload = function() {
          window.defaultImage[image_name] = `${file.name}|${reader.result}`
        }
      })
    }
  })

  const defaultFieldsName = mapFieldsConf((conf) => conf.defaultIdentifier)

  let formBuilderInitialized = false
  let existingFieldsNames = []
  let existingFieldsIds = []

  setInterval(() => {
    if (!formBuilder || !formBuilder.actions || !formBuilder.actions.setData) return
    if (!formBuilderInitialized) {
      initializeBuilderFromTextInput()
      existingFieldsIds = getFieldsIds()
      formBuilderInitialized = true
    }
    if ($formBuilderTextInput.is(':focus')) return

    existingFieldsNames = []
    $('.fld-name').each(function() { existingFieldsNames.push($(this).val()) })

    // Slugiy field names
    $('.fld-name').each(function() {
      const newValue = $(this).val().replace(/[^a-z^A-Z^_^0-9^{^}]/g, '_')
      $(this).val(newValue)
    })

    // Update the text field converting form builder content into wiki syntax
    if ($('#form-builder-container').is(':visible')) {
      const formData = formBuilder.actions.getData()
      // save base64 default image from buffer variable
      Object.keys(window.defaultImage).forEach((image_name) => {
        if (window.defaultImage[image_name] && window.defaultImage[image_name] != '') {
          const image_names = image_name.split('-')
          const field_idx = Number(image_names[image_names.length - 1]) - 1
          formData[field_idx].default_image = window.defaultImage[image_name]
        }
      })
      const wikiText = formatJsonDataIntoWikiText(formData)
      if (wikiText) $formBuilderTextInput.val(wikiText)
    }

    $('.fld-name').each(function() {
      let name = $(this).val()
      const id = $(this).closest('.form-field').attr('id')

      // Detect new fields added
      if (!existingFieldsIds.includes(id)) {
        const fieldType = $(this).closest('.form-field').attr('type')

        // Make the default names easier to read
        if (!name.includes('bf_')) {
          name = defaultFieldsName[fieldType] || `bf_${fieldType}`
          if (existingFieldsNames.includes(name)) {
            // If name already exist, we add a number (bf_address, bf_address1, bf_address2...)
            let number = 1
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
    }).trigger('change')

    // in semantic field, we want to separate value by coma
    $('.fld-semantic').each(function() {
      let newVal = $(this)
        .val()
        .replace(/\s*,\s*/g, ',')
      newVal = newVal.replace(/\s+/g, ',')
      newVal = newVal.replace(/,+/g, ',')
      $(this).val(newVal)
    })
  }, 300)

  $('#formbuilder-link').click(initializeBuilderFromTextInput)
}

document.addEventListener('DOMContentLoaded', () => {
  initializeFormbuilder()
})

function getFieldsIds() {
  const result = []
  $('.fld-name').each(function() { result.push($(this).closest('.form-field').attr('id')) })
  return result
}

function initializeBuilderFromTextInput() {
  const jsonData = parseWikiTextIntoJsonData($formBuilderTextInput.val())
  try {
    window.defaultImage = {}
    // extract base64 default image to buffer variable
    jsonData.forEach((field, index) => {
      if (field.type === 'image') {
        window.defaultImage[`fld-${index + 1}`] = field.default_image.trim()
      }
    })
    formBuilder.actions.setData(JSON.stringify(jsonData))
  } catch (error) {
    console.error(error)
  }
}

$('a[href="#formbuilder"]').on('click', (event) => {
  if (!confirm(_t('BAZ_FORM_EDIT_CONFIRM_DISPLAY_FORMBUILDER'))) {
    event.preventDefault()
    return false
  }
})
