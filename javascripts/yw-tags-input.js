// yw-tags-input.js -- live-search tag-picker widget (ticket 10, pilot for the
// yw-*/htmx pattern). No jQuery, no bootstrap-tagsinput. The search <input>'s
// hx-get drives the live query; this file only renders the JSON response and
// manages the chip list + hidden `pagetags` value htmx doesn't know about.
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

  function addTag(widget, rawTag) {
    const tag = rawTag.trim()
    if (!tag || currentTags(widget).includes(tag)) return

    const chip = document.createElement('span')
    chip.className = 'yw-tag-input__chip'
    chip.setAttribute('data-yw-tag-input-chip', '')
    chip.dataset.tag = tag
    chip.textContent = tag

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

  document.addEventListener('htmx:afterRequest', (e) => {
    const input = e.target.closest('[data-yw-tag-input-search]')
    if (!input || !e.detail.successful) return

    const widget = widgetOf(input)
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

  document.addEventListener('click', (e) => {
    const suggestion = e.target.closest('[data-yw-tag-input-suggestion]')
    if (suggestion) {
      const widget = widgetOf(suggestion)
      addTag(widget, suggestion.textContent)
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

    if (e.key === 'Enter' || e.key === ',' || e.key === ';') {
      e.preventDefault()
      const widget = widgetOf(input)
      addTag(widget, input.value.replace(/[,;]$/, ''))
      input.value = ''
      hideSuggestions(widget)
    } else if (e.key === 'Escape') {
      hideSuggestions(widgetOf(input))
    }
  })
}())
