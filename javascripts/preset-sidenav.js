// preset-sidenav.js — live theme-preset editor sidebar (ticket 16: vanilla JS;
// the native <input type="color"> pickers replace the jQuery spectrum widget —
// disclosed: no alpha channel/palette popup — and the font inputs are plain text
// inputs instead of the jQuery fontselect dropdown)
function deactivatePresets() {
  document.querySelectorAll('.css-preset').forEach((preset) => {
    preset.classList.remove('active')
  })
}

function closeNav() {
  document.getElementById('preset-sidenav').style.width = '0'
  document.getElementById('yw-container').style.paddingRight = '0'
  return false
}

// eslint-disable-next-line no-unused-vars
function openNav() {
  // si c'est déja ouvert, on ferme
  if (document.getElementById('preset-sidenav').style.width === '250px') {
    closeNav()
  } else {
    document.getElementById('preset-sidenav').style.width = '250px'
    document.getElementById('yw-container').style.paddingRight = '250px'
    const previousActive = Array.from(
      document.querySelectorAll('.css-preset.active'),
    )
    document
      .querySelectorAll('#preset-sidenav .colorpicker')
      .forEach((pickerParam) => {
        // define values from current set for color picker
        const picker = pickerParam
        const value = document.documentElement.style.getPropertyValue(
          `--${picker.getAttribute('name')}`,
        )
        if (value) {
          picker.value = value
          picker.dispatchEvent(new Event('change', { bubbles: true }))
        }
      })
    document
      .querySelectorAll('#preset-sidenav .fontpicker')
      .forEach((pickerParam) => {
        // define values from current set for font picker
        const picker = pickerParam
        let value = document.documentElement.style.getPropertyValue(
          `--${picker.getAttribute('name')}`,
        )
        if (value) {
          // extract name
          const values = value.split(',')
          ;[value] = values
          value = value.replace(/'/g, '')
          picker.value = value
          picker.dispatchEvent(new Event('change', { bubbles: true }))
        }
      })
    document
      .querySelectorAll('#preset-sidenav .form-input[name=main-text-fontsize]')
      .forEach((inputParam) => {
        const input = inputParam
        let value = document.documentElement.style.getPropertyValue(
          '--main-text-fontsize',
        )

        if (value) {
          const values = value.split('px')
          ;[value] = values
          input.value = value
          input.dispatchEvent(new Event('change', { bubbles: true }))
        }
      })
    previousActive.forEach((preset) => preset.classList.add('active'))
  }
  return false
}

// ticket 14: one initialiser convention -- see ywInit in yeswiki-base-no-defer.js
// ticket 16: keyed on each picker rather than on <body>, which survives a boosted navigation
// and would leave every page after the first uninitialised
ywInitEach('.colorpicker', (picker) => {
  {
    const applyColor = () => {
      document.documentElement.style.setProperty(
        `--${picker.getAttribute('name')}`,
        picker.value,
      )
    }
    picker.addEventListener('input', () => {
      applyColor()
      deactivatePresets()
    })
    picker.addEventListener('change', applyColor)
  }

  document.querySelectorAll('.fontpicker').forEach((fontPicker) => {
    fontPicker.addEventListener('change', () => {
      // Replace + signs with spaces for css
      let font = fontPicker.value.replace(/\+/g, ' ')

      // Split font into family and weight (weight kept for compat, unused in css var)
      font = font.split(':')

      const fontFamily = font[0]

      document.documentElement.style.setProperty(
        `--${fontPicker.getAttribute('name')}`,
        `'${fontFamily}'`,
      )
      deactivatePresets()
    })
  })

  document.querySelectorAll('#preset-sidenav .range').forEach((range) => {
    range.addEventListener('change', () => {
      document.documentElement.style.setProperty(
        `--${range.getAttribute('name')}`,
        `${range.value}px`,
      )
      deactivatePresets()
    })
  })

  document.querySelectorAll('.css-preset').forEach((preset) => {
    preset.addEventListener('click', (e) => {
      e.preventDefault()
      closeNav()
      // get data
      const data = preset.dataset
      const { primaryColor } = data
      const { secondaryColor1 } = data
      const { secondaryColor2 } = data
      const { neutralColor } = data
      const { neutralSoftColor } = data
      const { neutralLightColor } = data
      const { mainTextFontsize } = data
      const { mainTextFontfamily } = data
      const { mainTitleFontfamily } = data
      // check all data
      if (
        !primaryColor ||
        !secondaryColor1 ||
        !secondaryColor2 ||
        !neutralColor ||
        !neutralSoftColor ||
        !neutralLightColor ||
        !mainTextFontsize ||
        !mainTextFontfamily ||
        !mainTitleFontfamily
      ) {
        // error
        const message = themeSelectorTranslation.TEMPLATE_PRESET_ERROR
        if (typeof toastMessage === 'function') {
          toastMessage(message, 3000, 'alert alert-warning')
        } else {
          alert(message)
        }
        return
      }
      // set values
      document.documentElement.style.setProperty(
        '--primary-color',
        primaryColor,
      )
      document.documentElement.style.setProperty(
        '--secondary-color-1',
        secondaryColor1,
      )
      document.documentElement.style.setProperty(
        '--secondary-color-2',
        secondaryColor2,
      )
      document.documentElement.style.setProperty(
        '--neutral-color',
        neutralColor,
      )
      document.documentElement.style.setProperty(
        '--neutral-soft-color',
        neutralSoftColor,
      )
      document.documentElement.style.setProperty(
        '--neutral-light-color',
        neutralLightColor,
      )
      document.documentElement.style.setProperty(
        '--main-text-fontsize',
        mainTextFontsize,
      )
      document.documentElement.style.setProperty(
        '--main-text-fontfamily',
        mainTextFontfamily,
      )
      document.documentElement.style.setProperty(
        '--main-title-fontfamily',
        mainTitleFontfamily,
      )
      // set filename
      let filename = data.key || ''
      filename = filename.replace('.css', '')
      if (filename) {
        document
          .querySelectorAll('#preset-sidenav input.form-input[name=filename]')
          .forEach((inputParam) => {
            const input = inputParam
            input.value = filename
          })
      }
      // set class active or toggle it
      const isAlreadyActive = preset.classList.contains('active')
      deactivatePresets()
      if (!isAlreadyActive) {
        preset.classList.add('active')
      }
    })
  })
})

// eslint-disable-next-line no-unused-vars
function deleteCSSPreset(elem, text, url) {
  // called from template onclick attributes, using the implicit window.event
  // eslint-disable-next-line no-restricted-globals
  event.preventDefault()
  const { key } = elem.dataset
  // eslint-disable-next-line no-alert
  const confirmResult = confirm(text)
  if (confirmResult) {
    fetch(url, { method: 'DELETE' })
      .then((response) => (response.ok ? response : Promise.reject(response)))
      .then(() => {
        console.log(`${key} deleted !`)
        elem.parentElement.remove()
      })
      .catch(async (response) => {
        const message = key + themeSelectorTranslation.TEMPLATE_FILE_NOT_DELETED
        const responseText = response.text
          ? await response.text().catch(() => '')
          : ''
        console.log(`${message} Message :${responseText}`)
        if (typeof toastMessage === 'function') {
          toastMessage(message, 3000, 'alert alert-warning')
        } else {
          alert(message)
        }
      })
  }
  // to prevent opening url
  return false
}

function componentToHex(c) {
  const hex = parseInt(c, 10).toString(16)
  return hex.length === 1 ? `0${hex}` : hex
}

function extractFromStringWithRGB(valueParam) {
  let value = valueParam
  const res = value.match(
    /\s*rgb\(\s*([0-9]*)\s*,\s*([0-9]*)\s*,\s*([0-9]*)\s*\)/,
  )
  if (res && res.length > 3) {
    value = `#${componentToHex(res[1])}${componentToHex(res[2])}${componentToHex(res[3])}`
  }
  return value
}

function getStyleValueEvenIfNotInitialized(prop) {
  let value = document.documentElement.style.getPropertyValue(prop)
  if (!value) {
    value = wiki.cssVar(prop)
  }
  return value
}

function quoteFontFamily(fontFamilyParam) {
  let fontFamily = fontFamilyParam
  if (fontFamily.search(/^[A-Za-z0-9 ]*$/) !== -1) {
    fontFamily = `'${fontFamily}', sans-serif`
  } else if (fontFamily.search(/^'[A-Za-z0-9 ]*'$/) !== -1) {
    fontFamily = `${fontFamily}, sans-serif`
  }
  return fontFamily
}

function selectValue(selector) {
  const el = document.querySelector(selector)
  return el ? el.value : ''
}

// eslint-disable-next-line no-unused-vars
function saveCSSPreset(elem, urlParam, rewriteMode) {
  // called from template onclick attributes, using the implicit window.event
  // eslint-disable-next-line no-restricted-globals
  event.preventDefault()
  let url = urlParam
  const previous = elem.previousElementSibling
  const filenameInput = previous
    ? previous.querySelector('input[name=filename]')
    : null
  let fileName = filenameInput ? filenameInput.value : ''
  fileName = fileName.replace('.css', '')
  const fullFileName = `${fileName}.css`
  url += fullFileName
  // get values
  const colorProp = (prop) =>
    extractFromStringWithRGB(getStyleValueEvenIfNotInitialized(prop))
  const fontProp = (prop) =>
    quoteFontFamily(getStyleValueEvenIfNotInitialized(prop))
  const body = new URLSearchParams({
    'primary-color': colorProp('--primary-color'),
    'secondary-color-1': colorProp('--secondary-color-1'),
    'secondary-color-2': colorProp('--secondary-color-2'),
    'neutral-color': colorProp('--neutral-color'),
    'neutral-soft-color': colorProp('--neutral-soft-color'),
    'neutral-light-color': colorProp('--neutral-light-color'),
    'main-text-fontsize': getStyleValueEvenIfNotInitialized(
      '--main-text-fontsize',
    ),
    'main-text-fontfamily': fontProp('--main-text-fontfamily'),
    'main-title-fontfamily': fontProp('--main-title-fontfamily'),
  })
  fetch(url, { method: 'POST', body })
    .then((response) => (response.ok ? response : Promise.reject(response)))
    .then(() => {
      console.log(`${fullFileName} added !`)
      const urlwindow = window.location.toString()
      const urlAux = urlwindow.split(`${rewriteMode ? '?' : '&'}theme=`)
      const squelette = selectValue('[name=squelette_select]')
      const style = selectValue('[name=style_select]')
      window.location = `${
        urlAux[0] + (rewriteMode ? '?' : '&')
      }theme=${selectValue('[name=theme_select]')}&squelette=${squelette}${
        squelette.slice(-'.tpl.html'.length) === '.tpl.html' ? '' : '.tpl.html'
      }&style=${style}${
        style.slice(-'.css'.length) === '.css' ? '' : '.css'
      }&preset=${customCSSPresetsPrefix}${fullFileName}`
    })
    .catch(async (response) => {
      let data = null
      let dataMessage = ''
      const responseText = response.text
        ? await response.text().catch(() => '')
        : ''
      try {
        data = JSON.parse(responseText)
        dataMessage = data.message
      } catch {
        data = null
        dataMessage = JSON.stringify(responseText)
      }
      let message =
        fullFileName + themeSelectorTranslation.TEMPLATE_FILE_NOT_ADDED
      let duration = 3000
      if (data && data.errorCode === 2) {
        message = `${message}\n${themeSelectorTranslation.TEMPLATE_FILE_ALREADY_EXISTING}`
        duration = 6000
      }
      console.log(`${message}. Message :${dataMessage}`)
      if (typeof toastMessage === 'function') {
        toastMessage(message, duration, 'alert alert-danger')
      } else {
        alert(message)
      }
    })
}

function getActivePreset() {
  let presetKey = ''
  const selectedCssPreset = document.querySelector('.css-preset.active')
  if (selectedCssPreset) {
    const { key } = selectedCssPreset.dataset
    if (key) {
      if (selectedCssPreset.classList.contains('custom')) {
        presetKey = customCSSPresetsPrefix + key
      } else {
        presetKey = key
      }
    }
  }
  return presetKey
}

// eslint-disable-next-line no-unused-vars
function saveTheme(event, url) {
  const { target } = event
  const form = target.closest('form')
  const theme = selectValue('[name=theme_select]')
  const squelette = selectValue('[name=squelette_select]')
  const style = form
    ? (form.querySelector('[name=style_select]') || {}).value
    : ''
  const preset = getActivePreset()
  const errorMessage = themeSelectorTranslation.TEMPLATE_THEME_NOT_SAVE
  if (theme && squelette && style) {
    const hiddenForm = document.createElement('form')
    hiddenForm.id = 'templateFormSubmit'
    hiddenForm.method = 'post'
    hiddenForm.action = url
    hiddenForm.enctype = 'multipart/form-data'
    const fields = {
      action: 'setTemplate',
      theme_select: theme,
      squelette_select: squelette,
      style_select: style,
      preset_select: preset,
    }
    Object.keys(fields).forEach((name) => {
      const input = document.createElement('input')
      input.type = 'hidden'
      input.name = name
      input.value = fields[name]
      hiddenForm.appendChild(input)
    })
    document.body.appendChild(hiddenForm)
    hiddenForm.submit()
  } else if (typeof toastMessage === 'function') {
    toastMessage(errorMessage, 3000, 'alert alert-warning')
  } else {
    alert(errorMessage)
  }
  return false
}
