$(document).ready(() => {
  if (typeof autocompleteFieldnames == 'object' && 'postalCode' in autocompleteFieldnames && 'town' in autocompleteFieldnames) {
    $(`input[name="${autocompleteFieldnames.postalCode}"],input[name="${autocompleteFieldnames.town}"]`).attr('autocomplete', 'off')
    const $inputcp = $(`input[name="${autocompleteFieldnames.postalCode}"]`)
    $inputcp.typeahead({
      items: 'all',
      source(input, callback) {
        if (input.length === 5) {
          geolocationHelper.getGelocationDataFromPostalCode('France', input)
            .then((data) => {
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
              callback(result)
            })
            .catch(() => {
              callback([{ id: input, name: _t('BAZ_POSTAL_CODE_HINT') }])
            })
        } else {
          callback([{ id: input, name: _t('BAZ_POSTAL_CODE_HINT') }])
        }
      },
      autoSelect: false,
      afterSelect(item) {
        $inputcp.val(item.id)
        $inputville.val(item.ville)
        $('.btn-geolocate-address').click()
      }
    })
    var $inputville = $(`input[name="${autocompleteFieldnames.town}"]`)
    $inputville.typeahead({
      items: 12,
      minLength: 3,
      source(input, callback) {
        if (input.length >= 3) {
          geolocationHelper.getGelocationDataFromTown('France', input)
            .then((data) => {
              const result = []
              if (data.length > 0) {
                data.forEach((geoloc) => {
                  geoloc.postalCodes.forEach((code) => {
                    result.push({
                      id: code,
                      name: `${code} ${geoloc.town}`,
                      ville: geoloc.town
                    })
                  })
                })
                callback(result)
              } else {
                callback([{ id: input, name: _t('BAZ_TOWN_NOT_FOUND', { input }) }])
              }
            })
            .catch(() => {
              callback([{ id: input, name: _t('BAZ_TOWN_HINT') }])
            })
        } else {
          callback([{ id: input, name: _t('BAZ_TOWN_HINT') }])
        }
      },
      autoSelect: false,
      afterSelect(item) {
        $inputcp.val(item.id)
        $inputville.val(item.ville)
        $('.btn-geolocate-address').click()
      }
    })
  }
})
