/** Markup syntax, said once: both editor toolbars are built from these lists. */
import { legacyIconToSprite } from './yw-icon-map.js'

const HIGHLIGHT = [
  '{{section bgcolor="var(--yw-primary)" class="" pattern="border-solid"}}',
  '{{end elem="section"}}',
]

const TABLE =
  '| col1 | col2 | col3 |\n| --- | --- | --- |\n|  |  |  |\n|  |  |  |\n'

const heading = (level) => ({
  name: `title${level}`,
  icon: `h-${level}`,
  hotkey: `⌥⌘${level}`,
  preview: [`<h${level}>`, `</h${level}>`],
  wiki: [`${'#'.repeat(level)} `, ''],
  wysiwyg: { heading: level },
})

/** The Format menu, in the columns it is drawn in: `wiki` is what the source editor writes, `wysiwyg` how the other one writes the same thing. */
export const FORMAT_MENU = [
  {
    label: () => _t('ACEDITOR_FORMAT_TITLES'),
    entries: [
      { ...heading(1), label: () => _t('ACEDITOR_TITLE1') },
      { ...heading(2), label: () => _t('ACEDITOR_TITLE2') },
      { ...heading(3), label: () => _t('ACEDITOR_TITLE3') },
      { ...heading(4), label: () => _t('ACEDITOR_TITLE4') },
      { ...heading(5), label: () => _t('ACEDITOR_TITLE5') },
    ],
  },
  {
    label: () => _t('ACEDITOR_FORMAT_BLOCKS'),
    entries: [
      {
        name: 'center',
        label: () => _t('ACEDITOR_CENTER'),
        icon: 'align-center',
        wiki: ['<center>\n\n', '\n\n</center>'],
        wysiwyg: { wrap: ['<center>', '</center>'] },
      },
      {
        name: 'highlight',
        label: () => _t('ACEDITOR_HIGHLIGHT'),
        icon: 'highlight',
        preview: ['<span class="well">', '</span>'],
        wiki: [`${HIGHLIGHT[0]}\n`, `\n${HIGHLIGHT[1]}`],
        wysiwyg: { wrap: HIGHLIGHT },
      },
      {
        name: 'quote',
        hotkey: '⌘;',
        label: () => _t('ACEDITOR_QUOTE'),
        icon: 'quote',
        preview: ['<span class="yw-format-menu__quote">', '</span>'],
        wiki: ['> ', ''],
        wysiwyg: { builtin: 'quote' },
      },
      {
        name: 'checklist',
        hotkey: '⌘J',
        label: () => _t('ACEDITOR_CHECKLIST'),
        icon: 'square-check',
        wiki: ['- [ ] ', ''],
        wysiwyg: { builtin: 'check' },
      },
      {
        name: 'line',
        hotkey: '⇧⌘H',
        label: () => _t('ACEDITOR_LINE'),
        icon: 'separator-horizontal',
        wiki: ['\n------\n', ''],
        wysiwyg: { builtin: 'line' },
      },
      {
        name: 'table',
        hotkey: '⌘M',
        label: () => _t('ACEDITOR_TABLE'),
        icon: 'table',
        wiki: [TABLE, ''],
        wysiwyg: { builtin: 'table' },
      },
      {
        name: 'code',
        hotkey: '',
        label: () => _t('ACEDITOR_CODE'),
        icon: 'codeblock',
        wiki: ['```\n', '\n```'],
        wysiwyg: { builtin: 'code' },
      },
      {
        name: 'inline_code',
        hotkey: '⌘G',
        label: () => _t('ACEDITOR_INLINE_CODE'),
        icon: 'code',
        preview: ['<code>', '</code>'],
        wiki: ['`', '`'],
        wysiwyg: { builtin: 'inline-code' },
      },
      {
        name: 'comment',
        label: () => _t('ACEDITOR_COMMENT'),
        icon: 'eye-off',
        wiki: ['{# ', ' #}'],
        wysiwyg: { surround: ['{# ', ' #}'] },
      },
    ],
  },
  {
    label: () => _t('ACEDITOR_FORMAT_ALERTS'),
    entries: [
      {
        name: 'info',
        label: () => _t('ALERT_INFO'),
        icon: 'info-circle',
        preview: ['<span class="yw-alert yw-alert--info">', '</span>'],
        callout: 'info',
        wysiwyg: { callout: 'info' },
      },
      {
        name: 'success',
        label: () => _t('ALERT_SUCCESS'),
        icon: 'circle-check',
        preview: ['<span class="yw-alert yw-alert--success">', '</span>'],
        callout: 'success',
        wysiwyg: { callout: 'success' },
      },
      {
        name: 'warning',
        label: () => _t('ALERT_WARNING'),
        icon: 'alert-triangle',
        preview: ['<span class="yw-alert yw-alert--warning">', '</span>'],
        callout: 'warning',
        wysiwyg: { callout: 'warning' },
      },
      {
        name: 'danger',
        label: () => _t('ALERT_DANGER'),
        icon: 'ban',
        preview: ['<span class="yw-alert yw-alert--danger">', '</span>'],
        callout: 'danger',
        wysiwyg: { callout: 'danger' },
      },
    ],
  },
]

/** What applies to a few words rather than to a block, and so stays on the bar itself. */
export const INLINE_TOOLS = [
  [
    {
      name: 'bold',
      hotkey: '⌘B',
      label: () => _t('ACEDITOR_BOLD'),
      icon: 'bold',
      wiki: ['**', '**'],
      wysiwyg: { builtin: 'bold' },
    },
    {
      name: 'italic',
      hotkey: '⌘I',
      label: () => _t('ACEDITOR_ITALIC'),
      icon: 'italic',
      wiki: ['*', '*'],
      wysiwyg: { builtin: 'italic' },
    },
    {
      name: 'underline',
      hotkey: '⌘U',
      label: () => _t('ACEDITOR_UNDERLINE'),
      icon: 'underline',
      wiki: ['<u>', '</u>'],
      wysiwyg: { surround: ['<u>', '</u>'] },
    },
    {
      name: 'strike',
      hotkey: '⌘D',
      label: () => _t('ACEDITOR_STRIKE'),
      icon: 'strikethrough',
      wiki: ['~~', '~~'],
      wysiwyg: { builtin: 'strike' },
    },
  ],
  [
    {
      name: 'list',
      hotkey: '⌘L',
      label: () => _t('ACEDITOR_LIST'),
      icon: 'list',
      wiki: ['- ', ''],
      wysiwyg: { builtin: 'list' },
    },
    {
      name: 'ordered_list',
      hotkey: '⌘O',
      label: () => _t('ACEDITOR_ORDERED_LIST'),
      icon: 'list-numbers',
      wiki: ['1. ', ''],
      wysiwyg: { builtin: 'ordered-list' },
    },
  ],
]

const onAMac = () => /Mac/.test(navigator.platform)

/** A shortcut as Vditor writes it in a menu, so the two menus read the same. */
export const hotkeyTip = (hotkey) =>
  onAMac()
    ? hotkey
    : hotkey
        .replace('⇧⌘', 'Ctrl+Shift+')
        .replace('⌥⌘', 'Alt+Ctrl+')
        .replace('⌘', 'Ctrl+')

/** Whether this keystroke is that shortcut, told by the key's place rather than the character a layout prints there. */
export function matchesHotkey(event, hotkey) {
  if (!hotkey || !(event.ctrlKey || event.metaKey)) return false
  if (event.altKey !== hotkey.includes('⌥')) return false
  if (event.shiftKey !== hotkey.includes('⇧')) return false

  const key = hotkey.replace(/[⇧⌥⌘]/g, '')

  return /^[0-9]$/.test(key)
    ? event.code === `Digit${key}`
    : event.key.toLowerCase() === key.toLowerCase()
}

const menuEntryHtml = (entry, withHotkey = true) => {
  const [open, close] = entry.preview || ['', '']
  const hint =
    withHotkey && entry.hotkey ? ` &lt;${hotkeyTip(entry.hotkey)}&gt;` : ''

  return (
    `<span class="yw-format-menu__entry" data-yw-format="${entry.name}">` +
    `${legacyIconToSprite(entry.icon) || ''}${open}${entry.label()}${close}</span>${hint}`
  )
}

/** Draw the shared half of the source editor's toolbar into the placeholders its template leaves for it. */
export function fillSourceEditorToolbar(toolbar) {
  const menu = toolbar.querySelector('[data-yw-format-menu]')
  if (menu) {
    menu.replaceChildren(
      ...FORMAT_MENU.map((group) =>
        menuGroup(
          group.label(),
          group.entries.map((entry) =>
            sourceEditorButton(entry, menuEntryHtml(entry), ''),
          ),
        ),
      ),
    )
  }

  const tools = toolbar.querySelector('[data-yw-inline-tools]')
  if (tools) {
    tools.replaceChildren(
      ...INLINE_TOOLS.map((group) => {
        const buttons = document.createElement('div')
        buttons.className = 'btn-group'
        buttons.append(
          ...group.map((entry) =>
            sourceEditorButton(entry, legacyIconToSprite(entry.icon)),
          ),
        )

        return buttons
      }),
    )
  }
}

/** Vditor builds its panel as one flat run of items; this is the same columns the source editor's menu is drawn in. */
export function groupWysiwygFormatMenu(panel) {
  if (!panel || panel.querySelector('.yw-format-menu__group')) return

  const columns = [[]]
  for (const item of [...panel.children]) {
    if (item.classList.contains('vditor-toolbar__divider')) columns.push([])
    else columns[columns.length - 1].push(item)
  }

  panel.replaceChildren(
    ...columns.map((items, index) =>
      menuGroup(FORMAT_MENU[index]?.label() || '', items),
    ),
  )
}

function menuGroup(label, items) {
  const group = document.createElement('div')
  group.className = 'yw-format-menu__group'
  const heading = document.createElement('div')
  heading.className = 'yw-format-menu__label'
  heading.textContent = label
  group.append(heading, ...items)

  return group
}

function sourceEditorButton(entry, inner, btnClass = 'yw-btn') {
  const button = document.createElement('button')
  button.type = 'button'
  button.className =
    `${btnClass} aceditor-btn aceditor-btn-${entry.name}`.trim()
  button.title = entry.hotkey
    ? `${entry.label()} <${hotkeyTip(entry.hotkey)}>`
    : entry.label()
  if (entry.callout) {
    button.dataset.callout = entry.callout
  } else {
    button.dataset.lft = entry.wiki[0]
    button.dataset.rgt = entry.wiki[1]
  }
  button.innerHTML = inner

  return button
}

/**
 * The same menu and the same buttons, as Vditor toolbar items.
 *
 * @param actions what the wiki editor does for the entries Vditor has no command for.
 */
export function wysiwygMarkupItems(actions) {
  return [
    {
      name: 'yw-format',
      tip: _t('ACEDITOR_FORMAT'),
      tipPosition: 'se',
      icon: `<span>${_t('ACEDITOR_FORMAT')}</span><span class="yw-dropdown__caret"></span>`,
      click() {},
      toolbar: FORMAT_MENU.flatMap((group, index) => [
        ...(index > 0 ? ['|'] : []),
        ...group.entries.map((entry) => wysiwygItem(entry, actions)),
      ]),
    },
    ...INLINE_TOOLS.flatMap((group, index) => [
      ...(index > 0 ? ['|'] : []),
      ...group.map((entry) => ({
        ...wysiwygItem(entry, actions),
        tip: entry.label(),
        tipPosition: 'n',
        icon: legacyIconToSprite(entry.icon),
      })),
    ]),
  ]
}

function wysiwygItem(entry, actions) {
  const recipe = entry.wysiwyg
  const hotkey = entry.hotkey || ''
  if (recipe.builtin) {
    return { name: recipe.builtin, tip: menuEntryHtml(entry, false), hotkey }
  }

  return {
    name: `yw-${entry.name}`,
    tip: entry.label(),
    hotkey,
    icon: menuEntryHtml(entry),
    click() {
      if (recipe.heading) actions.heading(recipe.heading)
      else if (recipe.wrap) actions.wrap(...recipe.wrap)
      else if (recipe.surround) actions.surround(...recipe.surround)
      else if (recipe.callout) actions.callout(recipe.callout)
    },
  }
}
