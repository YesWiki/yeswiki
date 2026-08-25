import './unsaved-changes.js'
// afterwards and reassigns the same global from the cache-busted URL, so this cannot pin a
import './yw-autocomplete.js'

/** The rows of one list, in document order. */
function rowsOf(list) {
  return [...list.querySelectorAll('[data-yw-layout-row]')]
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
    .querySelectorAll('.yw-layout__link:not([data-yw-layout-suggested])')
    .forEach((field) => {
      field.dataset.ywLayoutSuggested = '1'
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
  const flag = row.querySelector('[data-yw-layout-child]')
  if (!flag) return
  flag.value = child ? '1' : '0'
  row.classList.toggle('yw-layout__row--child', child)

  const button = row.querySelector('[data-yw-layout-indent]')
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
    ? button.dataset.ywLayoutTitleOutdent
    : button.dataset.ywLayoutTitleIndent
  if (title) button.title = title
}

function isChild(row) {
  return row.querySelector('[data-yw-layout-child]')?.value === '1'
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

function refresh(list) {
  normalizeIndents(list)
  renumber(list)
}

document.querySelectorAll('[data-yw-layout-rows]').forEach((list) => {
  const name = list.dataset.ywLayoutRows
  const template = document.querySelector(`[data-yw-layout-template="${name}"]`)

  document
    .querySelector(`[data-yw-layout-add="${name}"]`)
    ?.addEventListener('click', () => {
      if (!template) return
      list.appendChild(template.content.cloneNode(true))
      refresh(list)
      suggestPagesIn(list)
      rowsOf(list).at(-1)?.querySelector('input[type="text"]')?.focus()
    })

  list.addEventListener('click', (event) => {
    const row = event.target.closest('[data-yw-layout-row]')
    if (!row) return

    if (event.target.closest('[data-yw-layout-remove]')) {
      row.remove()
      refresh(list)
      return
    }

    const move = event.target.closest('[data-yw-layout-move]')
    if (move) {
      moveRow(list, row, Number(move.dataset.ywLayoutMove))
      refresh(list)
      return
    }

    if (event.target.closest('[data-yw-layout-indent]')) {
      setChild(row, !isChild(row))
      refresh(list)
    }
  })

  refresh(list)
  suggestPagesIn(list)
})

ywInitEach('[data-yw-layout-logo]', (logo) => {
  const preview = document.querySelector('[data-yw-layout-logo-preview]')
  const removeLogo = document.querySelector('[data-yw-layout-logo-remove]')

  const show = () => {
    const chosen = logo.value.trim() !== ''
    if (preview) {
      preview.src = logo.value
      preview.hidden = !chosen
    }
    if (removeLogo) removeLogo.hidden = !chosen
  }

  logo.addEventListener('input', show)
  logo.addEventListener('change', show)

  removeLogo?.addEventListener('click', () => {
    logo.value = ''
    logo.dispatchEvent(new Event('change', { bubbles: true }))
  })
})

ywInitEach('[data-yw-layout-height]', (height) => {
  const heightValue = document.querySelector('[data-yw-layout-height-value]')

  const apply = () => {
    const px = `${height.value}px`
    document.documentElement.style.setProperty('--yw-navbar-height', px)
    if (heightValue) heightValue.textContent = px
  }
  apply()
  height.addEventListener('input', apply)
})
