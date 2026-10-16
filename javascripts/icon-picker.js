// Choose an icon from the shipped sprite, an emoji, or an image of the wiki's files.
// One popover per page, shared by the list editor, the menu editor and the actions builder.
import { spriteNames, legacyIconToSprite } from './yw-icon-map.js'
import FilePickerPanel from './file-picker-panel.js'

const SOURCES = ['sprite', 'emoji', 'reaction', 'file']
const DEFAULT_SOURCES = ['sprite', 'emoji', 'file']
export const REACTION_IMAGES = [
  'top-gratitude',
  'j-aime',
  'j-ai-appris',
  'pas-compris',
  'pas-d-accord',
  'idee-noire',
]
const EMOJI_LOCALES = ['en', 'es', 'fr', 'it', 'nl', 'pt', 'ru']

const esc = (text) =>
  String(text).replace(
    /[&<>"']/g,
    (char) =>
      ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
      })[char],
  )

const sourceLabel = (source) =>
  ({
    sprite: _t('MENU_ENTRY_ICON_SPRITE'),
    emoji: _t('MENU_ENTRY_ICON_EMOJI'),
    reaction: _t('ICON_PICKER_ILLUSTRATIONS'),
    file: _t('MENU_ENTRY_ICON_FILE'),
  })[source]

const assetUrl = (path) => `${wiki.baseUrl.replace(/\?+$/, '')}${path}`

const reactionImageUrl = (name) => assetUrl(`styles/images/mikone-${name}.svg`)

/** The URL an uploaded file is shown from, at icon size. */
export const fileIconUrl = (tag) =>
  wiki.url(`api/files/${encodeURIComponent(tag)}/download`, {
    width: 64,
    height: 64,
  })

/** An icon `{source, value}` as HTML, or '' when it has no value. */
export function iconHtml({ source, value } = {}) {
  const text = String(value ?? '').trim()
  if (text === '') return ''
  if (source === 'emoji')
    return `<span class="yw-icon-emoji" aria-hidden="true">${esc(text)}</span>`
  if (source === 'reaction')
    return `<img class="yw-icon-image" src="${esc(reactionImageUrl(text))}" alt="${esc(text)}">`
  if (source === 'file') {
    const src = /^(https?:)?\/\/|api\/files\//.test(text)
      ? text
      : fileIconUrl(text)
    return `<img class="yw-icon-image" src="${esc(src)}" alt="${esc(text)}" onerror="this.replaceWith(this.alt)">`
  }
  return legacyIconToSprite(text) ?? ''
}

/** Which source a bare string written by an older single-value field comes from. */
export function iconFromString(value) {
  const text = String(value ?? '').trim()
  if (text === '') return { source: 'sprite', value: '' }
  if (/\p{Extended_Pictographic}/u.test(text))
    return { source: 'emoji', value: text }
  const illustration = text.match(/^mikone-(.+)\.svg$/)?.[1] ?? text
  if (REACTION_IMAGES.includes(illustration))
    return { source: 'reaction', value: illustration }
  if (legacyIconToSprite(text)) return { source: 'sprite', value: text }
  return { source: 'file', value: text }
}

let popover = null
let current = null
let emojiPicker = null
let filePicker = null

function close(refocus = false) {
  if (!popover || popover.hidden) return
  popover.hidden = true
  document.removeEventListener('pointerdown', onOutside, true)
  document.removeEventListener('keydown', onKey, true)
  window.removeEventListener('resize', onResize)
  if (refocus) current?.anchor?.focus()
  current = null
}

const onResize = () => close()

function onOutside(event) {
  if (popover.contains(event.target) || current?.anchor?.contains(event.target))
    return
  close()
}

function onKey(event) {
  if (event.key !== 'Escape') return
  event.stopPropagation()
  close(true)
}

function pick(icon) {
  const { onPick } = current
  close(true)
  onPick(icon)
}

function buildPopover() {
  popover = document.createElement('div')
  popover.className = 'yw-icon-picker'
  popover.setAttribute('role', 'dialog')
  popover.setAttribute('aria-label', _t('ICON_PICKER_TITLE'))
  popover.hidden = true
  popover.innerHTML = `
    <div class="yw-icon-picker__tabs" role="tablist">
      ${SOURCES.map((source) => `<button type="button" role="tab" class="yw-icon-picker__tab" data-yw-icon-tab="${source}">${esc(sourceLabel(source))}</button>`).join('')}
    </div>
    <div class="yw-icon-picker__pane" data-yw-icon-pane="sprite">
      <input type="search" class="yw-input yw-icon-picker__search" autocomplete="off"
             placeholder="${esc(_t('ICON_PICKER_SEARCH'))}" aria-label="${esc(_t('ICON_PICKER_SEARCH'))}">
      <div class="yw-icon-picker__grid" data-yw-icon-grid>
        ${[...spriteNames]
          .sort()
          .map(
            (name) =>
              `<button type="button" class="yw-icon-picker__glyph" data-yw-icon-sprite="${esc(name)}" title="${esc(name)}">${legacyIconToSprite(name)}</button>`,
          )
          .join('')}
      </div>
    </div>
    <div class="yw-icon-picker__pane" data-yw-icon-pane="emoji"></div>
    <div class="yw-icon-picker__pane" data-yw-icon-pane="reaction">
      <div class="yw-icon-picker__grid yw-icon-picker__grid--large">
        ${REACTION_IMAGES.map(
          (name) =>
            `<button type="button" class="yw-icon-picker__glyph" data-yw-icon-reaction="${esc(name)}" title="${esc(name)}"><img src="${esc(reactionImageUrl(name))}" alt="${esc(name)}"></button>`,
        ).join('')}
      </div>
    </div>
    <div class="yw-icon-picker__pane yw-icon-picker__pane--file" data-yw-icon-pane="file">
      <span class="yw-icon-picker__file-preview" data-yw-icon-file-preview></span>
      <button type="button" class="yw-btn yw-btn--primary" data-yw-icon-file-open>
        <svg class="yw-icon" aria-hidden="true"><use href="${esc(assetUrl('src/assets/icons.svg#photo'))}"/></svg>
        ${esc(_t('MENU_ENTRY_ICON_CHOOSE'))}
      </button>
    </div>
    <div class="yw-icon-picker__footer">
      <button type="button" class="yw-btn yw-btn--sm yw-btn--quiet" data-yw-icon-clear>${esc(_t('ICON_PICKER_NONE'))}</button>
    </div>`
  document.body.append(popover)

  popover.addEventListener('click', (event) => {
    const tab = event.target.closest('[data-yw-icon-tab]')
    if (tab) return showTab(tab.dataset.ywIconTab)
    const glyph = event.target.closest('[data-yw-icon-sprite]')
    if (glyph)
      return pick({ source: 'sprite', value: glyph.dataset.ywIconSprite })
    const illustration = event.target.closest('[data-yw-icon-reaction]')
    if (illustration)
      return pick({
        source: 'reaction',
        value: illustration.dataset.ywIconReaction,
      })
    if (event.target.closest('[data-yw-icon-file-open]')) return openFiles()
    if (event.target.closest('[data-yw-icon-clear]'))
      return pick({ source: current.value.source || 'sprite', value: '' })
  })

  const search = popover.querySelector('.yw-icon-picker__search')
  search.addEventListener('input', () => {
    const needle = search.value.trim().toLowerCase()
    popover.querySelectorAll('[data-yw-icon-sprite]').forEach((glyph) => {
      glyph.hidden =
        needle !== '' && !glyph.dataset.ywIconSprite.includes(needle)
    })
  })
}

async function mountEmojiPicker() {
  if (emojiPicker) return
  const pane = popover.querySelector('[data-yw-icon-pane="emoji"]')
  const locale = EMOJI_LOCALES.includes(wiki.locale) ? wiki.locale : 'en'
  const base = new URL('./vendor/emoji-picker-element/', import.meta.url)
  const [{ Picker }, { default: i18n }] = await Promise.all([
    import('./vendor/emoji-picker-element/index.js'),
    import(`./vendor/emoji-picker-element/i18n/${locale}.js`),
  ])
  emojiPicker = new Picker({
    locale,
    i18n,
    dataSource: new URL(`data/${locale}.json`, base).href,
  })
  emojiPicker.classList.add('yw-icon-picker__emoji')
  emojiPicker.addEventListener('emoji-click', (event) => {
    if (event.detail.unicode)
      pick({ source: 'emoji', value: event.detail.unicode })
  })
  pane.append(emojiPicker)
}

function showTab(source) {
  popover.querySelectorAll('[data-yw-icon-tab]').forEach((tab) => {
    const active = tab.dataset.ywIconTab === source
    tab.setAttribute('aria-selected', String(active))
    tab.classList.toggle('yw-icon-picker__tab--active', active)
  })
  popover.querySelectorAll('[data-yw-icon-pane]').forEach((pane) => {
    pane.hidden = pane.dataset.ywIconPane !== source
  })
  if (source === 'sprite')
    popover.querySelector('.yw-icon-picker__search').focus()
  if (source === 'emoji') mountEmojiPicker()
  place()
}

function openFiles() {
  filePicker ??= new FilePickerPanel()
  const { onPick } = current
  close()
  filePicker.open({
    only: 'image',
    onPick: ({ tag }) => onPick({ source: 'file', value: tag }),
  })
}

function place() {
  if (!current) return
  const rect = current.anchor.getBoundingClientRect()
  const width = popover.offsetWidth
  const height = popover.offsetHeight
  const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8))
  const below = rect.bottom + 4
  const top =
    below + height > window.innerHeight && rect.top - height - 4 > 0
      ? rect.top - height - 4
      : below
  popover.style.left = `${left}px`
  popover.style.top = `${top}px`
}

/** Open the picker under `anchor` on `value` ({source, value}); `onPick` gets the chosen icon. */
export function openIconPicker({
  anchor,
  value,
  sources = DEFAULT_SOURCES,
  onPick,
}) {
  if (!popover) buildPopover()
  if (current?.anchor === anchor && !popover.hidden) return close()
  close()

  const offered = SOURCES.filter(
    (source) =>
      sources.includes(source) &&
      (source !== 'file' || document.getElementById('YesWikiFilePickerPanel')),
  )
  current = {
    anchor,
    value: value ?? { source: offered[0], value: '' },
    onPick,
  }

  popover.querySelectorAll('[data-yw-icon-tab]').forEach((tab) => {
    tab.hidden = !offered.includes(tab.dataset.ywIconTab)
  })
  popover.querySelector('.yw-icon-picker__tabs').hidden = offered.length < 2
  popover.querySelector('[data-yw-icon-file-preview]').innerHTML =
    current.value.source === 'file' ? iconHtml(current.value) : ''
  const search = popover.querySelector('.yw-icon-picker__search')
  search.value = ''
  search.dispatchEvent(new Event('input'))

  popover.hidden = false
  showTab(
    offered.includes(current.value.source) ? current.value.source : offered[0],
  )
  document.addEventListener('pointerdown', onOutside, true)
  document.addEventListener('keydown', onKey, true)
  window.addEventListener('resize', onResize)
}
