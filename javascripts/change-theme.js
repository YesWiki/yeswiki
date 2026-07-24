(function($) {
  const findSibling = (baseElement, type) => $(baseElement).siblings().filter(function() {
    const firstSelect = $(this).find('select').first()
    return firstSelect && firstSelect.attr('name') === type
  }).first()
    .find(`[name=${type}`)
    .first()

  const updateOptions = (currentBase, type, val, data) => {
    if (val in data.templates) {
      const theme = data.templates[val]
      const element = findSibling(currentBase, `${type}_select`)
      if (element[0]) {
        const curVal = $(element).val()
        // empty list
        let emptyOption = ''
        for (let index = 0; index < element[0].options.length; index++) {
          if (element[0].options[index].value.length === 0) {
            emptyOption = element[0].options[index].text
            break
          }
        }
        for (let index = element[0].options.length - 1; index >= 0; index--) {
          element[0].options.remove(index)
        }
        if (emptyOption.length > 0) {
          element[0].options.add(new Option(emptyOption, '', false, false))
        }
        const favorite = type in data.favorites ? data.favorites[type] : null
        if (type === 'preset') {
          if (!('presets' in theme)) {
            $(element).closest('.form-group').hide()
          } else {
            const currentList = theme.presets
            const anchor = currentList.map((val) => val.replace(/(\.css)$/, '')).includes(curVal)
              ? curVal
              : favorite
            currentList.forEach((value) => {
              const shortValue = value.replace(/\.css$/, '')
              element[0].options.add(new Option(shortValue, shortValue, false, anchor === value))
            })
            Object.keys(data.presets).forEach((k) => {
              if (k.slice(0, 'custom/'.length) === 'custom/') {
                const value = data.presets[k]
                const shortValue = value.replace(/\.css$/, '')
                element[0].options.add(new Option(shortValue, shortValue, false, anchor === value))
              }
            })
            $(element).closest('.form-group').show()
          }
        } else if (type in theme) {
          const currentList = theme[type]
          const anchor = Object.values(currentList).map((val) => val.replace(/(\.css|\.tpl.html)$/, '')).includes(curVal)
            ? curVal
            : favorite
          Object.keys(currentList).forEach((k) => {
            const value = currentList[k]
            const shortValue = value.replace(/(\.css|\.tpl.html)$/, '')
            element[0].options.add(new Option(shortValue, k, false, anchor === k))
          })
        }
      }
    }
  }

  const extractData = (currentBase) => {
    let templates = $(currentBase).data('templates')
    templates = (typeof templates === 'object' && templates !== null) ? templates : {}
    let favorites = $(currentBase).data('favorites')
    favorites = (typeof favorites === 'object' && favorites !== null) ? favorites : {}
    let presets = $(currentBase).data('presets')
    presets = (typeof presets === 'object' && presets !== null) ? presets : {}
    let updateUrl = $(currentBase).data('updateUrl')
    updateUrl = ['string', 'boolean'].includes(typeof updateUrl) && [true, 'true'].includes(updateUrl)
    return { templates, favorites, presets, updateUrl }
  }

  const newUrlFromType = (url, type, currentBase) => {
    const element = type === 'theme'
      ? $(currentBase).find('select')
      : findSibling(currentBase, `${type}_select`)
    if (element.length > 0) {
      let val = $(element).val()
      if (val && typeof val !== 'undefined') {
        const ext = type === 'squelette' ? '.tpl.html' : '.css'
        if (type !== 'theme' && val.slice(-ext.length) !== ext) {
          val += ext
        }
        if (url.match(new RegExp(`(\\?|&)${type}=`))) {
          url = url.replace(new RegExp(`(\\?|&)${type}=[^#&=]+`), `$1${type}=${val}`)
        } else {
          url += `${url.includes('?') ? '&' : '?'}${type}=${val}`
        }
      }
    }
    return url
  }

  const updateUrl = (data, currentBase) => {
    if (data.updateUrl) {
      let url = window.location.toString()

      url = newUrlFromType(url, 'theme', currentBase)
      url = newUrlFromType(url, 'style', currentBase)
      url = newUrlFromType(url, 'squelette', currentBase)
      url = newUrlFromType(url, 'preset', currentBase)

      window.location = url
    }
  }

  $('[name=theme_select]').on('change', function() {
    const currentBase = $(this).closest('.control-group.form-group')
    const data = extractData(currentBase)

    // On change le theme dynamiquement
    const val = $(this).val()
    updateOptions(currentBase, 'squelette', val, data)
    updateOptions(currentBase, 'style', val, data)
    updateOptions(currentBase, 'preset', val, data)
    if (data.updateUrl) {
      updateUrl(data, currentBase)
    }
  })

  $('[name=style_select],[name=squelette_select]').on('change', function() {
    const currentBase = $(this).closest('.control-group.form-group')
    const element = findSibling(currentBase, 'theme_select')
    const realBase = $(element).closest('.control-group.form-group')
    const data = extractData(realBase)

    if (data.updateUrl) {
      updateUrl(data, realBase)
    }
  })
}(jQuery))
