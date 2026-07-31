// map-autocomplete.js — postal-code/town suggestions for the map field's address
// inputs (ticket 16: vanilla, on yw-autocomplete instead of bootstrap3-typeahead)
(function() {
  // Ticket 14: keyed on the map field itself rather than on a page-global
  // `autocompleteFieldnames`, and driven by ywInitEach so a map arriving in a fragment gets
  // its autocomplete too. The global could only ever describe one map -- a second map field
  // on the same form silently overwrote the first one's field names -- while
  // `data-field-names` is already rendered per field by templates/inputs/map.twig.
  ywInitEach('.geocode-input[data-field-names]', (mapField) => {
    let fieldNames
    try {
      fieldNames = JSON.parse(mapField.getAttribute('data-field-names'))
    } catch {
      return
    }
    if (!fieldNames || !fieldNames.postalCode || !fieldNames.town) return

    const form = mapField.closest('form') || document
    const inputCp = form.querySelector(`input[name="${fieldNames.postalCode}"]`)
    const inputTown = form.querySelector(`input[name="${fieldNames.town}"]`)
    if (!inputCp || !inputTown) return
    inputCp.setAttribute('autocomplete', 'off')
    inputTown.setAttribute('autocomplete', 'off')

    function toSuggestions(data) {
      const result = []
      data.forEach((geoloc) => {
        geoloc.postalCodes.forEach((code) => {
          result.push({
            id: code,
            name: `${code} ${geoloc.town}`,
            ville: geoloc.town
          })
        })
      })
      return result
    }

    // a hint row carries no `ville`: picking it must not overwrite the inputs
    function applySelection(item) {
      if (!item.ville) return
      inputCp.value = item.id
      inputTown.value = item.ville
      const geolocate = mapField.querySelector('.btn-geolocate-address')
      if (geolocate) geolocate.click()
    }

    window.ywAutocomplete(inputCp, {
      items: 99,
      render: (item) => item.name,
      onSelect: applySelection,
      source(query) {
        if (query.length !== 5) {
          return [{ id: query, name: _t('BAZ_POSTAL_CODE_HINT') }]
        }
        return geolocationHelper.getGelocationDataFromPostalCode('France', query)
          .then(toSuggestions)
          .catch(() => [{ id: query, name: _t('BAZ_POSTAL_CODE_HINT') }])
      }
    })

    window.ywAutocomplete(inputTown, {
      items: 12,
      minLength: 3,
      render: (item) => item.name,
      onSelect: applySelection,
      source(query) {
        return geolocationHelper.getGelocationDataFromTown('France', query)
          .then((data) => (data.length > 0
            ? toSuggestions(data)
            : [{ id: query, name: _t('BAZ_TOWN_NOT_FOUND', { input: query }) }]))
          .catch(() => [{ id: query, name: _t('BAZ_TOWN_HINT') }])
      }
    })
  })
}())
