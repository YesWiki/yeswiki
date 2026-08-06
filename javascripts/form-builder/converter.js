// converter.js — storage JSON ↔ designer field objects (ticket 26)
//
// The stored template (`template`) is a JSON array of named-attribute field
// objects: `[{"type": "texte", "name": "bf_titre", "label": "…"}]`. Attribute keys
// are the FIELD_* constant names of the PHP class handling the type (see
// FieldFactory::getAttributeIndexToKeyMap()); the designer edits those objects
// directly, so conversion here is only about resolving the wiki type keyword to a
// designer field config and back — there is no positional mapping anymore.

// Mapping between yeswiki type keywords and the designer's internal field types.
// Use window to make it available outside of module, so extensions can add
// their own types (same contract as the old builder).
window.yesWikiTypes = window.yesWikiTypes || {}
Object.assign(window.yesWikiTypes, {
  lien_internet: { type: 'url' },
  lien_internet_bis: { type: 'text', subtype: 'url' },
  mot_de_passe: { type: 'text', subtype: 'password' },
  texte: { type: 'text' }, // all other text sub_types (range, text, tel, …)
  textelong: { type: 'textarea' },
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
  fichier: { type: 'file' },
  champs_cache: { type: 'hidden' },
  listefiches: { type: 'listefichesliees' },
})

// stored JSON text -> list of { type: designerType, data: attributes } items.
// `data` keeps the storage keys verbatim, plus transient resolution helpers
// (`_wikiType`, `sub_type` for aliased keywords, `subtype2` for list-vs-form
// enum variants) that serializeFields() strips or re-resolves.
export function parseFields(text, registry) {
  let stored
  try {
    stored = JSON.parse(text)
  } catch {
    return null
  }
  if (!Array.isArray(stored)) return null

  return stored
    .filter(
      (fieldObject) =>
        fieldObject && typeof fieldObject === 'object' && fieldObject.type,
    )
    .map((fieldObject) => {
      const wikiType = fieldObject.type
      const resolution = window.yesWikiTypes[wikiType]
      let type = resolution ? resolution.type : wikiType
      if (!(type in registry)) type = 'custom'

      const data = { ...fieldObject }
      delete data.type
      data._wikiType = wikiType
      if (resolution?.subtype && !data.sub_type)
        data.sub_type = resolution.subtype
      if (resolution?.subtype2) data.subtype2 = resolution.subtype2

      return { type, data }
    })
}

// designer type (+ sub_type / subtype2 in data) -> stored wiki type keyword
function resolveWikiType(type, data) {
  const original = data._wikiType
  const compatible = (resolution) =>
    resolution.type === type &&
    (!data.sub_type ||
      !resolution.subtype ||
      data.sub_type === resolution.subtype) &&
    (!data.subtype2 || data.subtype2 === resolution.subtype2)
  const entries = Object.entries(window.yesWikiTypes)

  // a keyword dedicated to this exact sub_type wins (text+password -> mot_de_passe,
  // text+url -> lien_internet_bis -> lien_internet)
  const specific = entries.find(
    ([, resolution]) =>
      compatible(resolution) &&
      resolution.subtype &&
      resolution.subtype === data.sub_type,
  )
  if (specific) return specific[0].replace(/_bis$/, '')

  // otherwise the field's original keyword is kept as long as it still fits the
  // (possibly edited) designer type — several keywords alias one designer type
  // (jour/listedatedeb/listedatefin, map/carte_google) and round-trips must not
  // silently rewrite them
  const originalResolution = original ? window.yesWikiTypes[original] : null
  if (originalResolution && compatible(originalResolution)) return original

  const first = entries.find(([, resolution]) => compatible(resolution))
  if (first) return first[0].replace(/_bis$/, '')

  // custom/unknown fields keep their original keyword; registry-named types
  // (acls, tabs, labelhtml, …) store under their own name
  return original || type
}

// list of { type, data } items -> stored JSON text (pretty, like the PHP encoder)
export function serializeFields(fields, registry) {
  const stored = fields.map(({ type, data }) => {
    const config = registry[type] || {}
    const fieldObject = { type: resolveWikiType(type, data) }
    Object.entries(data).forEach(([key, value]) => {
      if (key.startsWith('_') || key === 'subtype2') return
      if (config.attributes?.[key]?.transient) return
      let serialized = Array.isArray(value)
        ? value.join(',')
        : String(value ?? '')
      serialized = serialized.trim()
      if (serialized === '') return
      fieldObject[key] = serialized
    })
    return fieldObject
  })

  return JSON.stringify(stored, null, 4)
}
