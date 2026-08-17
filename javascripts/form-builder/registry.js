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
  ...(window.formBuilderFields || {}),
}

export default registry

export const paletteEntries = Object.entries(registry)
  .filter(([, config]) => config.field || config.set)
  .map(([type, config]) => ({ type, config }))
