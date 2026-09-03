import './unsaved-changes.js'
import './yw-autocomplete.js'
import './inputs/file-picker-field.js'

/** The rows of one menu, in document order. */
function rowsOf(list) {
  return [...list.querySelectorAll('[data-yw-menu-row]')]
}

/** The wiki's page names, fetched once and shared by every link field on the screen. */
let pageNames = null
function pages() {
  pageNames ??= fetch(wiki.url('?api/pages'))
    .then((response) =>
      response.ok ? response.json() : Promise.reject(response),
    )
    .then((data) =>
      Object.values(data)
        .map((page) => page.tag)
        .filter(Boolean),
    )
    .catch(() => [])

  return pageNames
}

/** Offer those names on every link field in `root`, the way the editor's own link rail does. */
function suggestPagesIn(root) {
  root
    .querySelectorAll('.yw-menu-row__link:not([data-yw-menu-suggested])')
    .forEach((field) => {
      field.dataset.ywMenuSuggested = '1'
      pages().then((names) => {
        window.ywAutocomplete(field, {
          items: 6,
          minLength: 1,
          source: (query) =>
            names.filter((name) =>
              name.toLowerCase().includes(query.toLowerCase()),
            ),
        })
      })
    })
}

/** Rewrite every field name in the list so the indices are 0..n-1 in document order. */
function renumber(list) {
  rowsOf(list).forEach((row, index) => {
    row.querySelectorAll('[name]').forEach((field) => {
      field.setAttribute('name', field.name.replace(/\[\d+\]/, `[${index}]`))
    })
  })
}

/** A row cannot be a child of nothing: the first row of the list is always top level. */
function normalizeIndents(list) {
  rowsOf(list).forEach((row, index) => {
    if (index === 0) setChild(row, false)
  })
}

function setChild(row, child) {
  const flag = row.querySelector('[data-yw-menu-child]')
  if (!flag) return
  flag.value = child ? '1' : '0'
  row.classList.toggle('yw-menu-row--child', child)

  const button = row.querySelector('[data-yw-menu-indent]')
  if (!button) return
  const use = button.querySelector('use')
  if (use) {
    const href = use.getAttribute('href') || ''
    use.setAttribute(
      'href',
      href.replace(/#.*$/, child ? '#arrow-left' : '#arrow-right'),
    )
  }
  const title = child
    ? button.dataset.ywMenuTitleOutdent
    : button.dataset.ywMenuTitleIndent
  if (title) button.title = title
}

function isChild(row) {
  return row.querySelector('[data-yw-menu-child]')?.value === '1'
}

/** The rows a move has to carry as one: a top-level row takes the submenu entries under it. */
function groupOf(row) {
  const group = [row]
  if (isChild(row)) return group
  let next = row.nextElementSibling
  while (next && isChild(next)) {
    group.push(next)
    next = next.nextElementSibling
  }
  return group
}

/** Move `row` one place, taking its submenu with it and stepping over whole groups. */
function moveRow(list, row, step) {
  const up = step < 0

  if (isChild(row)) {
    const sibling = up ? row.previousElementSibling : row.nextElementSibling
    if (!sibling || !isChild(sibling)) return
    list.insertBefore(up ? row : sibling, up ? sibling : row)

    return
  }

  const group = groupOf(row)
  if (up) {
    let target = group[0].previousElementSibling
    if (!target) return
    while (isChild(target) && target.previousElementSibling) {
      target = target.previousElementSibling
    }
    group.forEach((member) => list.insertBefore(member, target))

    return
  }

  const following = group[group.length - 1].nextElementSibling
  if (!following) return
  const nextGroup = groupOf(following)
  const anchor = nextGroup[nextGroup.length - 1].nextElementSibling
  group.forEach((member) => list.insertBefore(member, anchor))
}

/** The file picker writes into a field it is told the id of, so each row's needs one of its own. */
let pickerSeq = 0

/**
 * Which control an icon needs depends on where its glyph comes from: a sprite symbol and an emoji
 * are typed, a file is chosen from what the wiki already holds -- through the same picker
 * `/admin/layout` opens for the logo, which needs no wiring beyond naming the field it fills.
 */
function showIconControls(row) {
  const icon = row.querySelector('[data-yw-menu-icon]')
  if (!icon) return
  const value = icon.querySelector('[data-yw-menu-icon-value]')
  const pick = icon.querySelector('[data-yw-menu-icon-pick]')
  if (!value || !pick) return

  if (!value.id) value.id = `yw-menu-icon-${++pickerSeq}`
  pick.dataset.ywFilePickerField = value.id
  pick.dataset.ywFilePickerOnly = 'image'
  pick.hidden =
    icon.querySelector('[data-yw-menu-icon-source]')?.value !== 'file'
}

function refresh(list) {
  normalizeIndents(list)
  renumber(list)
  rowsOf(list).forEach(showIconControls)
}

/** Dragging says where a row goes; it never says what level it is at -- that is the indent button. */
function makeSortable(list) {
  if (!window.Sortable) return
  window.Sortable.create(list, {
    handle: '[data-yw-menu-grip]',
    animation: 150,
    onEnd: () => {
      refresh(list)
      list.dispatchEvent(new Event('change', { bubbles: true }))
    },
  })
}

document.querySelectorAll('[data-yw-menu-rows]').forEach((list) => {
  const name = list.dataset.ywMenuRows
  const template = document.querySelector(`[data-yw-menu-template="${name}"]`)

  document
    .querySelector(`[data-yw-menu-add="${name}"]`)
    ?.addEventListener('click', () => {
      if (!template) return
      list.appendChild(template.content.cloneNode(true))
      refresh(list)
      suggestPagesIn(list)
      rowsOf(list).at(-1)?.querySelector('.yw-menu-row__label')?.focus()
    })

  list.addEventListener('click', (event) => {
    const row = event.target.closest('[data-yw-menu-row]')
    if (!row) return

    if (event.target.closest('[data-yw-menu-remove]')) {
      row.remove()
      refresh(list)
      return
    }

    const move = event.target.closest('[data-yw-menu-move]')
    if (move) {
      moveRow(list, row, Number(move.dataset.ywMenuMove))
      refresh(list)
      return
    }

    if (event.target.closest('[data-yw-menu-indent]')) {
      setChild(row, !isChild(row))
      refresh(list)
    }
  })

  list.addEventListener('change', (event) => {
    if (event.target.closest('[data-yw-menu-icon-source]')) {
      showIconControls(event.target.closest('[data-yw-menu-row]'))
    }
  })

  refresh(list)
  suggestPagesIn(list)
  makeSortable(list)
})
