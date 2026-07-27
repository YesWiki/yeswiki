// yw-tags-input.js -- live-search tag-picker widget (ticket 10, pilot for the
// yw-*/htmx pattern). No jQuery, no bootstrap-tagsinput. The search <input>'s
// hx-get drives the live query; this file only renders the JSON response and
// manages the chip list + hidden `pagetags` value htmx doesn't know about.
//
// Ticket 16 adds a second, "closed" mode for replacing bootstrap-tagsinput call
// sites that pick from a fixed local option list instead of a live server query:
// a widget with [data-yw-tag-input-options='{"id":"label", ...}'] filters
// suggestions from that local map (re-read on every keystroke, so a caller can
// swap the map at runtime just by updating the attribute -- no re-init needed).
// [data-yw-tag-input-closed] additionally restricts tags to that option list
// (no free-typed tags). [data-yw-tag-input-max="1"] caps the number of tags.
(function() {
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
  }

  function hideSuggestions(widget) {
    const list = widget.querySelector('[data-yw-tag-input-suggestions]')
    if (list) {
      list.hidden = true
      list.innerHTML = ''
    }
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
    if (!tag || currentTags(widget).includes(tag) || maxTagsReached(widget)) return

    const options = staticOptions(widget)
    if (widget.hasAttribute('data-yw-tag-input-closed') && options && !(tag in options)) return

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

    widget.insertBefore(chip, widget.querySelector('[data-yw-tag-input-search]'))
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

    list.innerHTML = ''
    if (!matches.length) {
      list.hidden = true
      return
    }
    matches.forEach(([id, label]) => {
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
  }

  document.addEventListener('htmx:afterRequest', (e) => {
    const input = e.target.closest('[data-yw-tag-input-search]')
    if (!input || !e.detail.successful) return

    const widget = widgetOf(input)
    if (staticOptions(widget)) return // this widget is in local/static mode, not htmx-driven

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

  document.addEventListener('focus', (e) => {
    const input = e.target.closest && e.target.closest('[data-yw-tag-input-search]')
    if (!input) return
    const widget = widgetOf(input)
    if (staticOptions(widget)) renderStaticSuggestions(widget, input)
  }, true)

  document.addEventListener('click', (e) => {
    const suggestion = e.target.closest('[data-yw-tag-input-suggestion]')
    if (suggestion) {
      const widget = widgetOf(suggestion)
      addTag(widget, suggestion.dataset.id || suggestion.textContent, suggestion.textContent)
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

    if (e.key === 'Enter' || (!closed && (e.key === ',' || e.key === ';'))) {
      e.preventDefault()
      if (options && closed) {
        // closed vocabulary: only a listed option may be added
        const list = widget.querySelector('[data-yw-tag-input-suggestions]')
        const firstSuggestion = list && list.querySelector('[data-yw-tag-input-suggestion]')
        if (firstSuggestion) {
          addTag(widget, firstSuggestion.dataset.id, firstSuggestion.textContent)
        }
      } else {
        // open vocabulary (with or without suggestions): free-typed tags are fine
        addTag(widget, input.value.replace(/[,;]$/, ''))
      }
      input.value = ''
      hideSuggestions(widget)
    } else if (e.key === 'Escape') {
      hideSuggestions(widget)
    }
  })
}())
