// change-theme.js — dependent theme/squelette/style/preset selects
// (ticket 16: vanilla JS)
(function() {
  // among the siblings of `baseElement`, the select named `type`
  const findSibling = (baseElement, type) => {
    let found = null
    Array.from(baseElement.parentElement ? baseElement.parentElement.children : []).forEach(
      (sibling) => {
        if (found || sibling === baseElement) return
        const firstSelect = sibling.querySelector('select')
        if (firstSelect && firstSelect.getAttribute('name') === type) {
          found = sibling.querySelector(`[name=${type}]`)
        }
      }
    )
    return found
  }

  const parseJsonDataset = (value) => {
    if (!value) return {}
    try {
      const parsed = JSON.parse(value)
      return (typeof parsed === 'object' && parsed !== null) ? parsed : {}
    } catch {
      return {}
    }
  }

  const extractData = (currentBase) => {
    const templates = parseJsonDataset(currentBase.dataset.templates)
    const favorites = parseJsonDataset(currentBase.dataset.favorites)
    const presets = parseJsonDataset(currentBase.dataset.presets)
    const rawUpdateUrl = currentBase.dataset.updateUrl
    const updateUrl = [true, 'true'].includes(rawUpdateUrl)
    return { templates, favorites, presets, updateUrl }
  }

  const updateOptions = (currentBase, type, val, data) => {
    if (!(val in data.templates)) return
    const theme = data.templates[val]
    const element = findSibling(currentBase, `${type}_select`)
    if (!element) return
    const curVal = element.value
    // empty list, keeping the placeholder empty option's label if there is one
    let emptyOption = ''
    Array.from(element.options).some((option) => {
      if (option.value.length === 0) {
        emptyOption = option.text
        return true
      }
      return false
    })
    for (let index = element.options.length - 1; index >= 0; index -= 1) {
      element.options.remove(index)
    }
    if (emptyOption.length > 0) {
      element.options.add(new Option(emptyOption, '', false, false))
    }
    const favorite = type in data.favorites ? data.favorites[type] : null
    const formGroup = element.closest('.yw-form-group')
    if (type === 'preset') {
      if (!('presets' in theme)) {
        if (formGroup) formGroup.style.display = 'none'
      } else {
        const currentList = theme.presets
        const anchor = currentList.map((value) => value.replace(/(\.css)$/, '')).includes(curVal)
          ? curVal
          : favorite
        currentList.forEach((value) => {
          const shortValue = value.replace(/\.css$/, '')
          element.options.add(new Option(shortValue, shortValue, false, anchor === value))
        })
        Object.keys(data.presets).forEach((k) => {
          if (k.slice(0, 'custom/'.length) === 'custom/') {
            const value = data.presets[k]
            const shortValue = value.replace(/\.css$/, '')
            element.options.add(new Option(shortValue, shortValue, false, anchor === value))
          }
        })
        if (formGroup) formGroup.style.display = ''
      }
    } else if (type in theme) {
      const currentList = theme[type]
      const anchor = Object.values(currentList)
        .map((value) => value.replace(/(\.css|\.tpl.html)$/, '')).includes(curVal)
        ? curVal
        : favorite
      Object.keys(currentList).forEach((k) => {
        const value = currentList[k]
        const shortValue = value.replace(/(\.css|\.tpl.html)$/, '')
        element.options.add(new Option(shortValue, k, false, anchor === k))
      })
    }
  }

  const newUrlFromType = (urlParam, type, currentBase) => {
    let url = urlParam
    const element = type === 'theme'
      ? currentBase.querySelector('select')
      : findSibling(currentBase, `${type}_select`)
    if (element) {
      let val = element.value
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

  document.querySelectorAll('[name=theme_select]').forEach((select) => {
    select.addEventListener('change', () => {
      const currentBase = select.closest('.yw-form-group')
      if (!currentBase) return
      const data = extractData(currentBase)

      // On change le theme dynamiquement
      const val = select.value
      updateOptions(currentBase, 'squelette', val, data)
      updateOptions(currentBase, 'style', val, data)
      updateOptions(currentBase, 'preset', val, data)
      if (data.updateUrl) {
        updateUrl(data, currentBase)
      }
    })
  })

  document.querySelectorAll('[name=style_select],[name=squelette_select]').forEach((select) => {
    select.addEventListener('change', () => {
      const currentBase = select.closest('.yw-form-group')
      if (!currentBase) return
      const element = findSibling(currentBase, 'theme_select')
      const realBase = element ? element.closest('.yw-form-group') : null
      if (!realBase) return
      const data = extractData(realBase)

      if (data.updateUrl) {
        updateUrl(data, realBase)
      }
    })
  })
}())
