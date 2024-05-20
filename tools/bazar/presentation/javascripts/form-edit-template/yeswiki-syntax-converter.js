import { mapFieldsConf } from './form-builder-helper.js'
import { defaultMapping } from './fields/commons/attributes.js'

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

function getYesWikiMapping() {
  return mapFieldsConf((conf) => conf.attributesMapping || defaultMapping)
}

// Remove accidental br at the end of the labels
function removeBR(text) {
  let newValue = text.replace(/(<div><br><\/div>)+$/g, '')
  // replace multiple '<div><br></div>' when at the end of the value
  newValue = newValue.replace(/(<br>)+$/g, '')
  // replace multiple '<br>' when at the end of the value
  return newValue
}

// transform text with wiki text like "texte***bf_titre***Nom***255***255*** *** *** ***1***0***"
// into a json object "{ type: 'texte', name: 'bf_titre', label: 'Nom' .... }"
export function parseWikiTextIntoJsonData(text) {
  const yesWikiMapping = getYesWikiMapping()
  const result = []
  text = text.trim()
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
        for (const key in window.formBuilderFields) {
          if (window.formBuilderFields[key].name == wikiType) {
            fieldObject.label = window.formBuilderFields[key].label
          }
        }
      }
      result.push(fieldObject)
    }
  }
  if (wiki.isDebugEnabled) {
    console.log('parse result', result)
  }
  return result
}

// transform a json object like "{ type: 'texte', name: 'bf_titre', label: 'Nom' .... }"
// into wiki text like "texte***bf_titre***Nom***255***255*** *** *** ***1***0***"
export function formatJsonDataIntoWikiText(formData) {
  if (formData.length == 0) return null
  let wikiText = ''
  const yesWikiMapping = getYesWikiMapping()

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
