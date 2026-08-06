// registry.js — designer field configs, keyed by designer type name (ticket 26)
//
// Each config declares: `field` (palette entry: label/icon), `attributes` (settings
// panel definition, keys = the storage attribute keys of the stored JSON template),
// `advancedAttributes`, `disabledAttributes`, `defaultIdentifier`, optional `set`
// (multi-field palette entry), `editorHint`, `editorSetup(api)`. There is no client
// side preview hook: the canvas cards show the field's real entry-form markup, served
// by `api/forms/preview` (FormController::previewTemplate).
//
// Extensions can add their own configs before the engine boots via
// window.formBuilderFields (same contract as the old builder's field modules).
import calc from './fields/calc.js'
import champsMail from './fields/champs_mail.js'
import checkboxGroup from './fields/checkbox-group.js'
import conditionschecking from './fields/conditionschecking.js'
import custom from './fields/custom.js'
import date from './fields/date.js'
import file from './fields/file.js'
import hidden from './fields/hidden.js'
import image from './fields/image.js'
import inscriptionliste from './fields/inscriptionliste.js'
import labelhtml from './fields/labelhtml.js'
import listefichesliees from './fields/listefichesliees.js'
import map from './fields/map.js'
import openinghours from './fields/openinghours.js'
import radioGroup from './fields/radio-group.js'
import reactions from './fields/reactions.js'
import select from './fields/select.js'
import tabchange from './fields/tabchange.js'
import tabs from './fields/tabs.js'
import tags from './fields/tags.js'
import text from './fields/text.js'
import textarea from './fields/textarea.js'
import url from './fields/url.js'

const registry = {
  text,
  textarea,
  select,
  'checkbox-group': checkboxGroup,
  'radio-group': radioGroup,
  date,
  file,
  image,
  url,
  map,
  champs_mail: champsMail,
  labelhtml,
  tags,
  hidden,
  listefichesliees,
  reactions,
  tabs,
  tabchange,
  conditionschecking,
  inscriptionliste,
  openinghours,
  calc,
  custom,
  // extension-provided configs win over core ones with the same name
  ...(window.formBuilderFields || {}),
}

export default registry

// palette order = registry order; only configs with a `field` (or `set`) block are
// directly addable from the palette
export const paletteEntries = Object.entries(registry)
  .filter(([, config]) => config.field || config.set)
  .map(([type, config]) => ({ type, config }))
