window.yesWikiTypes = window.yesWikiTypes || {}
Object.assign(window.yesWikiTypes, {
  lien_internet: { type: 'url' },
  lien_internet_bis: { type: 'text', subtype: 'url' },
  mot_de_passe: { type: 'text', subtype: 'password' },
  texte: { type: 'text' },
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

export function resolveWikiType(type, data) {
  const original = data._wikiType
  const compatible = (resolution) =>
    resolution.type === type &&
    (!data.sub_type ||
      !resolution.subtype ||
      data.sub_type === resolution.subtype) &&
    (!data.subtype2 || data.subtype2 === resolution.subtype2)
  const entries = Object.entries(window.yesWikiTypes)

  const specific = entries.find(
    ([, resolution]) =>
      compatible(resolution) &&
      resolution.subtype &&
      resolution.subtype === data.sub_type,
  )
  if (specific) return specific[0].replace(/_bis$/, '')

  const originalResolution = original ? window.yesWikiTypes[original] : null
  if (originalResolution && compatible(originalResolution)) return original

  const first = entries.find(([, resolution]) => compatible(resolution))
  if (first) return first[0].replace(/_bis$/, '')

  return original || type
}

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
