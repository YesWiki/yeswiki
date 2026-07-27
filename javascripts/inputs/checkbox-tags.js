// checkbox-tags.js — refresh button for distant-json radio/checkbox tag fields
// (ticket 16: tag picking itself is yw-tags-input.js's closed mode now; this only
// re-fetches the option list from the form's API and swaps the widget's live-read
// data-yw-tag-input-options attribute — no re-init needed)
(function() {
  document.addEventListener('click', (e) => {
    const button = e.target.closest('.tagsinput-refresh')
    if (!button) return
    const { propertyName } = button.dataset
    const formIdInput = document.querySelector('input[name=id_typeannonce]')
    const formId = formIdInput ? formIdInput.value : null
    const hidden = propertyName
      ? document.querySelector(`input[name="${propertyName}"][data-yw-tag-input-value]`)
      : null
    const widget = hidden ? hidden.closest('[data-yw-tag-input]') : null
    if (!formId || !widget) return
    fetch(wiki.url(`api/forms/${formId}`))
      .then((response) => (response.ok ? response.json() : Promise.reject(response)))
      .then((data) => {
        if (!data.prepared) return
        const fields = typeof data.prepared === 'object'
          ? Object.values(data.prepared)
          : data.prepared
        fields.forEach((field) => {
          if (field.propertyname === propertyName && field.options) {
            widget.setAttribute('data-yw-tag-input-options', JSON.stringify(field.options))
          }
        })
      })
      .catch(() => {
        /* keep the current option list on failure */
      })
  })
}())
