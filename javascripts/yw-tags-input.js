;(function () {
  function widgetOf(el) {
    return el.closest('[data-yw-tag-input]')
  }

  function currentTags(widget) {
    const chips = widget.querySelectorAll('[data-yw-tag-input-chip]')
    return Array.from(chips).map((chip) => chip.dataset.tag)
  }

  function syncValue(widget) {
    const hidden = widget.querySelector('[data-yw-tag-input-value]')
    if (hidden) hidden.value = currentTags(widget).join(',')
    widget.dispatchEvent(new CustomEvent('yw:tags-changed', { bubbles: true }))
  }

  function hideSuggestions(widget) {
    const list = widget.querySelector('[data-yw-tag-input-suggestions]')
    if (list) {
      list.hidden = true
      list.innerHTML = ''
    }
  }

  function suggestionsOf(widget) {
    const list = widget.querySelector('[data-yw-tag-input-suggestions]')
    if (!list || list.hidden) return []
    return Array.from(list.querySelectorAll('[data-yw-tag-input-suggestion]'))
  }

  /** The suggestion the arrows are on. */
  function activeSuggestion(widget) {
    return widget.querySelector('[data-yw-tag-input-suggestion][data-active]')
  }

  function moveActive(widget, step) {
    const suggestions = suggestionsOf(widget)
    if (!suggestions.length) return
    const current = activeSuggestion(widget)
    const index = current ? suggestions.indexOf(current) : -1
    const next =
      index === -1
        ? step > 0
          ? 0
          : suggestions.length - 1
        : (index + step + suggestions.length) % suggestions.length
    setActive(widget, suggestions[next])
  }

  function setActive(widget, suggestion) {
    const previous = activeSuggestion(widget)
    if (previous) previous.removeAttribute('data-active')
    const search = widget.querySelector('[data-yw-tag-input-search]')
    if (!suggestion) {
      if (search) search.removeAttribute('aria-activedescendant')
      return
    }
    suggestion.setAttribute('data-active', '')
    if (!suggestion.id) {
      suggestion.id = `yw-tag-suggestion-${Math.random().toString(36).slice(2)}`
    }
    if (search) search.setAttribute('aria-activedescendant', suggestion.id)
    suggestion.scrollIntoView({ block: 'nearest' })
  }

  function staticOptions(widget) {
    const raw = widget.getAttribute('data-yw-tag-input-options')
    if (!raw) return null
    try {
      return JSON.parse(raw)
    } catch {
      return null
    }
  }

  function maxTagsReached(widget) {
    const max = parseInt(widget.getAttribute('data-yw-tag-input-max'), 10)
    return Number.isInteger(max) && max > 0 && currentTags(widget).length >= max
  }

  function addTag(widget, id, label) {
    const tag = (id || '').trim()
    if (!tag || currentTags(widget).includes(tag) || maxTagsReached(widget))
      return

    const options = staticOptions(widget)
    if (
      widget.hasAttribute('data-yw-tag-input-closed') &&
      options &&
      !(tag in options)
    )
      return

    const chip = document.createElement('span')
    chip.className = 'yw-tag-input__chip'
    chip.setAttribute('data-yw-tag-input-chip', '')
    chip.dataset.tag = tag
    chip.textContent = label || tag

    const remove = document.createElement('button')
    remove.type = 'button'
    remove.className = 'yw-tag-input__chip-remove'
    remove.setAttribute('data-yw-tag-input-remove', '')
    remove.setAttribute('aria-label', 'remove')
    remove.textContent = '×'
    chip.appendChild(remove)

    widget.insertBefore(
      chip,
      widget.querySelector('[data-yw-tag-input-search]'),
    )
    syncValue(widget)
  }

  function renderStaticSuggestions(widget, input) {
    const list = widget.querySelector('[data-yw-tag-input-suggestions]')
    const options = staticOptions(widget)
    if (!list || !options) return

    const needle = input.value.trim().toLowerCase()
    const existing = currentTags(widget)
    const matches = Object.entries(options).filter(([id, label]) => {
      if (existing.includes(id)) return false
      return !needle || String(label).toLowerCase().includes(needle)
    })

    if (needle) {
      const rank = (label) => {
        const value = String(label).toLowerCase()
        if (value === needle) return 0
        return value.startsWith(needle) ? 1 : 2
      }
      matches.sort(
        ([, a], [, b]) =>
          rank(a) - rank(b) || String(a).localeCompare(String(b)),
      )
    }

    const limit = Number(widget.dataset.ywTagInputLimit || 0)
    const shown = limit > 0 ? matches.slice(0, limit) : matches

    list.innerHTML = ''
    if (!shown.length) {
      list.hidden = true
      return
    }
    shown.forEach(([id, label]) => {
      const item = document.createElement('li')
      const button = document.createElement('button')
      button.type = 'button'
      button.setAttribute('data-yw-tag-input-suggestion', '')
      button.dataset.id = id
      button.textContent = label
      item.appendChild(button)
      list.appendChild(item)
    })
    list.hidden = false
    widget.dispatchEvent(
      new CustomEvent('yw:tags-suggested', {
        bubbles: true,
        detail: { values: shown.map(([id]) => id) },
      }),
    )
  }

  document.addEventListener('htmx:afterRequest', (e) => {
    const input = e.target.closest('[data-yw-tag-input-search]')
    if (!input || !e.detail.successful) return

    const widget = widgetOf(input)
    if (staticOptions(widget)) return

    const list = widget.querySelector('[data-yw-tag-input-suggestions]')
    if (!list) return

    let data
    try {
      data = JSON.parse(e.detail.xhr.responseText)
    } catch {
      return
    }

    const existing = currentTags(widget)
    const tags = (data.tags || []).filter((tag) => !existing.includes(tag))
    list.innerHTML = ''
    if (!tags.length) {
      list.hidden = true
      return
    }
    tags.forEach((tag) => {
      const item = document.createElement('li')
      const button = document.createElement('button')
      button.type = 'button'
      button.setAttribute('data-yw-tag-input-suggestion', '')
      button.textContent = tag
      item.appendChild(button)
      list.appendChild(item)
    })
    list.hidden = false
  })

  document.addEventListener('input', (e) => {
    const input = e.target.closest('[data-yw-tag-input-search]')
    if (!input) return
    const widget = widgetOf(input)
    if (staticOptions(widget)) renderStaticSuggestions(widget, input)
  })

  document.addEventListener(
    'focus',
    (e) => {
      const input =
        e.target.closest && e.target.closest('[data-yw-tag-input-search]')
      if (!input) return
      const widget = widgetOf(input)
      if (staticOptions(widget)) renderStaticSuggestions(widget, input)
    },
    true,
  )

  document.addEventListener('click', (e) => {
    const suggestion = e.target.closest('[data-yw-tag-input-suggestion]')
    if (suggestion) {
      const widget = widgetOf(suggestion)
      addTag(
        widget,
        suggestion.dataset.id || suggestion.textContent,
        suggestion.textContent,
      )
      const search = widget.querySelector('[data-yw-tag-input-search]')
      search.value = ''
      search.focus()
      hideSuggestions(widget)
      return
    }

    const remove = e.target.closest('[data-yw-tag-input-remove]')
    if (remove) {
      const widget = widgetOf(remove)
      remove.closest('[data-yw-tag-input-chip]').remove()
      syncValue(widget)
      return
    }

    document.querySelectorAll('[data-yw-tag-input]').forEach((widget) => {
      if (!widget.contains(e.target)) hideSuggestions(widget)
    })
  })

  document.addEventListener('keydown', (e) => {
    const input = e.target.closest('[data-yw-tag-input-search]')
    if (!input) return
    const widget = widgetOf(input)
    const options = staticOptions(widget)
    const closed = widget.hasAttribute('data-yw-tag-input-closed')

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (!suggestionsOf(widget).length && options) {
        renderStaticSuggestions(widget, input)
      }
      if (suggestionsOf(widget).length) {
        e.preventDefault()
        moveActive(widget, e.key === 'ArrowDown' ? 1 : -1)
      }
      return
    }

    if (e.key === 'Enter' || (!closed && (e.key === ',' || e.key === ';'))) {
      e.preventDefault()
      if (options && closed) {
        const chosen = activeSuggestion(widget) || suggestionsOf(widget)[0]
        if (chosen) {
          addTag(widget, chosen.dataset.id, chosen.textContent)
        }
      } else {
        addTag(widget, input.value.replace(/[,;]$/, ''))
      }
      input.value = ''
      hideSuggestions(widget)
    } else if (e.key === 'Escape') {
      hideSuggestions(widget)
    }
  })
})()
