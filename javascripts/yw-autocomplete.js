// Suggestions under a text field as a WAI-ARIA combobox with a listbox popup: arrows move, Enter picks, a screen reader hears it all.
;(() => {
  let fields = 0

  window.ywAutocomplete = function (fieldParam, options = {}) {
    const field = fieldParam
    const maxItems = options.items || 8
    const minLength = options.minLength || 0
    const renderLabel = options.render || ((item) => String(item))
    const { source } = options
    const onSelect = options.onSelect || (() => {})

    fields += 1
    const listId = `yw-suggestions-${fields}`
    let list = null
    let currentItems = []
    let active = -1
    let skipNextQuery = false
    let queries = 0

    const status = document.createElement('span')
    status.className = 'sr-only'
    status.setAttribute('aria-live', 'polite')
    field.insertAdjacentElement('afterend', status)

    field.setAttribute('role', 'combobox')
    field.setAttribute('aria-autocomplete', 'list')
    field.setAttribute('aria-expanded', 'false')
    field.setAttribute('aria-controls', listId)
    field.setAttribute('autocomplete', 'off')

    /** Points the field and the options at the active one, or at none. */
    function setActive(index) {
      active = index
      if (!list) return
      ;[...list.children].forEach((option, position) => {
        const isActive = position === active
        option.classList.toggle('yw-suggestions__option--active', isActive)
        option.setAttribute('aria-selected', String(isActive))
        if (isActive) option.scrollIntoView({ block: 'nearest' })
      })
      if (active >= 0) {
        field.setAttribute('aria-activedescendant', `${listId}-${active}`)
      } else {
        field.removeAttribute('aria-activedescendant')
      }
    }

    function closeList() {
      if (list) {
        list.remove()
        list = null
      }
      active = -1
      field.setAttribute('aria-expanded', 'false')
      field.removeAttribute('aria-activedescendant')
    }

    function openList() {
      closeList()
      list = document.createElement('ul')
      list.className = 'yw-suggestions'
      list.id = listId
      list.setAttribute('role', 'listbox')
      list.style.position = 'absolute'
      list.style.top = `${field.offsetTop + field.offsetHeight}px`
      list.style.left = `${field.offsetLeft}px`
      list.style.minWidth = `${field.offsetWidth}px`
      if (field.parentNode && !field.parentNode.style.position) {
        field.parentNode.style.position = 'relative'
      }
      field.parentNode.insertBefore(list, field.nextSibling)
      field.setAttribute('aria-expanded', 'true')
    }

    function select(item) {
      field.value = renderLabel(item)
      closeList()
      onSelect(item)
      skipNextQuery = true
      field.dispatchEvent(new Event('input', { bubbles: true }))
    }

    function renderItems(matchedItems) {
      currentItems = matchedItems.slice(0, maxItems)
      status.textContent = _t('AUTOCOMPLETE_COUNT', {
        count: String(currentItems.length),
      })
      if (!currentItems.length) {
        closeList()
        return
      }
      openList()
      currentItems.forEach((item, position) => {
        const option = document.createElement('li')
        option.id = `${listId}-${position}`
        option.className = 'yw-suggestions__option'
        option.setAttribute('role', 'option')
        option.setAttribute('aria-selected', 'false')
        option.textContent = renderLabel(item)
        option.addEventListener('mousedown', (event) => event.preventDefault())
        option.addEventListener('click', () => select(item))
        list.appendChild(option)
      })
    }

    function handleQuery() {
      if (skipNextQuery) {
        skipNextQuery = false
        return
      }
      const query = field.value
      if (query.length < minLength) {
        closeList()
        return
      }
      queries += 1
      const asked = queries
      const result = typeof source === 'function' ? source(query) : source
      Promise.resolve(result).then((matchedItems) => {
        if (asked === queries) renderItems(matchedItems || [])
      })
    }

    /** Moves the active option by one, wrapping, after opening the list when it is closed. */
    function move(step) {
      if (!list) {
        handleQuery()
        return
      }
      const count = currentItems.length
      setActive(
        active < 0
          ? step > 0
            ? 0
            : count - 1
          : (active + step + count) % count,
      )
    }

    field.addEventListener('input', handleQuery)
    field.addEventListener('focus', handleQuery)
    field.addEventListener('blur', () => {
      setTimeout(closeList, 150)
    })
    field.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
        event.preventDefault()
        move(event.key === 'ArrowDown' ? 1 : -1)
      } else if (event.key === 'Enter' && list && active >= 0) {
        event.preventDefault()
        select(currentItems[active])
      } else if (event.key === 'Escape') {
        if (list) {
          event.preventDefault()
          closeList()
        } else if (field.value !== '') {
          event.preventDefault()
          field.value = ''
          skipNextQuery = true
          field.dispatchEvent(new Event('input', { bubbles: true }))
        }
      }
    })
  }
})()
