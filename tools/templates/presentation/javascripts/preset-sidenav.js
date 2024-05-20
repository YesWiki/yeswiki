function openNav() {
  // si c'est déja ouvert, on ferme
  if (document.getElementById('preset-sidenav').style.width == '250px') {
    closeNav()
  } else {
    document.getElementById('preset-sidenav').style.width = '250px'
    document.getElementById('yw-container').style.paddingRight = '250px'
    const previousAdtive = $('.css-preset.active')
    $('#preset-sidenav .colorpicker').each(function() {
      // define values from current set for color picker
      const value = document.documentElement.style.getPropertyValue(`--${$(this).attr('name')}`)
      if (value) {
        $(this).val(value)
        // trigger change event
        $(this).change()
      }
    })
    $('#preset-sidenav .fontpicker').each(function() {
      // define values from current set for color picker
      let value = document.documentElement.style.getPropertyValue(`--${$(this).attr('name')}`)
      if (value) {
        // extract name
        const values = value.split(',')
        value = values[0]
        value = value.replace(/'/g, '')
        $(this).val(value)
        // trigger change event
        $(this).change()
      }
    })
    $('#preset-sidenav .form-input[name=main-text-fontsize]').each(function() {
      // define values from current set for color picker
      let value = document.documentElement.style.getPropertyValue('--main-text-fontsize')

      if (value) {
        // extract name
        const values = value.split('px')
        value = values[0]
        $(this).val(value)
        // trigger change event
        $(this).change()
      }
    })
    $(previousAdtive).addClass('active')
  }
  return false
}

function closeNav() {
  document.getElementById('preset-sidenav').style.width = '0'
  document.getElementById('yw-container').style.paddingRight = '0'
  return false
}

document.addEventListener('DOMContentLoaded', () => {
  if (typeof $('.colorpicker').spectrum === 'function') {
    $('.colorpicker').spectrum({
      showPalette: true,
      showAlpha: true,
      showInput: true,
      clickoutFiresChange: true,
      showInitial: true,
      chooseText: themeSelectorTranslation.TEMPLATE_APPLY,
      cancelText: themeSelectorTranslation.TEMPLATE_CANCEL,
      change(color) {
        document.documentElement.style.setProperty(`--${$(this).attr('name')}`, color.toRgbString())
        $('.css-preset').removeClass('active')
      },
      hide(color) {
        document.documentElement.style.setProperty(`--${$(this).attr('name')}`, color.toRgbString())
      },
      move(color) {
        document.documentElement.style.setProperty(`--${$(this).attr('name')}`, color.toRgbString())
      },
      palette: [
        [
          wiki.cssVar('--primary-color'),
          wiki.cssVar('--secondary-color-1'),
          wiki.cssVar('--secondary-color-2')
        ],
        [
          wiki.cssVar('--neutral-light-color'),
          wiki.cssVar('--neutral-soft-color'),
          wiki.cssVar('--neutral-color')
        ]
      ]
    })
  }
  if (typeof $('.fontpicker').fontselect === 'function') {
    $('.fontpicker')
      .fontselect({
        placeholder: themeSelectorTranslation.TEMPLATE_CHOOSE_FONT,
        placeholderSearch: themeSelectorTranslation.TEMPLATE_SEARCH_POINTS
      })
      .on('change', function() {
        // Replace + signs with spaces for css
        let font = this.value.replace(/\+/g, ' ')

        // Split font into family and weight
        font = font.split(':')

        const fontFamily = font[0]
        const fontWeight = font[1] || 400

        document.documentElement.style.setProperty(`--${$(this).attr('name')}`, `'${fontFamily}'`)
        $('.css-preset').removeClass('active')
      })
  }

  $('#preset-sidenav .range').on('change', function() {
    document.documentElement.style.setProperty(`--${$(this).attr('name')}`, `${$(this).val()}px`)
    $('.css-preset').removeClass('active')
  })
}, false)

$('.css-preset').click(function() {
  closeNav()
  // get data
  const primaryColor = $(this).data('primary-color')
  const secondaryColor1 = $(this).data('secondary-color-1')
  const secondaryColor2 = $(this).data('secondary-color-2')
  const neutralColor = $(this).data('neutral-color')
  const neutralSoftColor = $(this).data('neutral-soft-color')
  const neutralLightColor = $(this).data('neutral-light-color')
  const mainTextFontsize = $(this).data('main-text-fontsize')
  const mainTextFontfamily = $(this).data('main-text-fontfamily')
  const mainTitleFontfamily = $(this).data('main-title-fontfamily')
  // check all data
  if (!primaryColor || !secondaryColor1 || !secondaryColor2 || !neutralColor
        || !neutralSoftColor || !neutralLightColor || !mainTextFontsize
        || !mainTextFontfamily || !mainTitleFontfamily) {
    // error
    const message = themeSelectorTranslation.TEMPLATE_PRESET_ERROR
    if (typeof toastMessage == 'function') {
      toastMessage(message, 3000, 'alert alert-warning')
    } else {
      alert(message)
    }
    return false
  }
  // set values
  document.documentElement.style.setProperty('--primary-color', primaryColor)
  document.documentElement.style.setProperty('--secondary-color-1', secondaryColor1)
  document.documentElement.style.setProperty('--secondary-color-2', secondaryColor2)
  document.documentElement.style.setProperty('--neutral-color', neutralColor)
  document.documentElement.style.setProperty('--neutral-soft-color', neutralSoftColor)
  document.documentElement.style.setProperty('--neutral-light-color', neutralLightColor)
  document.documentElement.style.setProperty('--main-text-fontsize', mainTextFontsize)
  document.documentElement.style.setProperty('--main-text-fontfamily', mainTextFontfamily)
  document.documentElement.style.setProperty('--main-title-fontfamily', mainTitleFontfamily)
  // set filename
  let filename = $(this).data('key')
  filename = filename.replace('.css', '')
  if (filename) {
    $('#preset-sidenav input.form-input[name=filename]').each(function() {
      $(this).val(filename)
    })
  }
  // set class active or toggle it
  const isAlreadyActive = $(this).hasClass('active')
  $('.css-preset').removeClass('active')
  if (!isAlreadyActive) {
    $(this).addClass('active')
  }
  return false
})
function deleteCSSPreset(elem, text, url) {
  event.preventDefault()
  const key = $(elem).data('key')
  const confirmResult = confirm(text)
  if (confirmResult) {
    $.ajax({
      url,
      success(data, textStatus, jqXHR) {
        console.log(`${key} deleted !`)
        $(elem).parent().remove()
      },
      method: 'DELETE',
      cache: false,
      error(jqXHR, textStatus, errorThrown) {
        const message = key + themeSelectorTranslation.TEMPLATE_FILE_NOT_DELETED
        console.log(`${message} Message :${jqXHR.responseText}`)
        if (typeof toastMessage == 'function') {
          toastMessage(message, 3000, 'alert alert-warning')
        } else {
          alert(message)
        }
      }
    })
  }
  // to prevent opening url
  return false
}
function componentToHex(c) {
  const hex = parseInt(c).toString(16)
  return hex.length == 1 ? `0${hex}` : hex
}
function extractFromStringWithRGB(value) {
  const res = value.match(/\s*rgb\(\s*([0-9]*)\s*,\s*([0-9]*)\s*,\s*([0-9]*)\s*\)/)
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
function saveCSSPreset(elem, url, rewriteMode) {
  event.preventDefault()
  let fileName = $(elem).prev().find('input[name=filename]').val()
  fileName = fileName.replace('.css', '')
  const fullFileName = `${fileName}.css`
  url += fullFileName
  // get values
  const primaryColor = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--primary-color'))
  const secondaryColor1 = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--secondary-color-1'))
  const secondaryColor2 = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--secondary-color-2'))
  const neutralColor = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--neutral-color'))
  const neutralSoftColor = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--neutral-soft-color'))
  const neutralLightColor = extractFromStringWithRGB(getStyleValueEvenIfNotInitialized('--neutral-light-color'))
  const mainTextFontsize = getStyleValueEvenIfNotInitialized('--main-text-fontsize')
  let mainTextFontfamily = getStyleValueEvenIfNotInitialized('--main-text-fontfamily')
  let mainTitleFontfamily = getStyleValueEvenIfNotInitialized('--main-title-fontfamily')
  if (mainTextFontfamily.search(/^[A-Za-z0-9 ]*$/) != -1) {
    mainTextFontfamily = `'${mainTextFontfamily}', sans-serif`
  } else if (mainTextFontfamily.search(/^\'[A-Za-z0-9 ]*\'$/) != -1) {
    mainTextFontfamily = `${mainTextFontfamily}, sans-serif`
  }
  if (mainTitleFontfamily.search(/^[A-Za-z0-9 ]*$/) != -1) {
    mainTitleFontfamily = `'${mainTitleFontfamily}', sans-serif`
  } else if (mainTitleFontfamily.search(/^\'[A-Za-z0-9 ]*\'$/) != -1) {
    mainTitleFontfamily = `${mainTitleFontfamily}, sans-serif`
  }
  $.ajax({
    url,
    success(data, textStatus, jqXHR) {
      console.log(`${fullFileName} added !`)
      const urlwindow = window.location.toString()
      const urlAux = urlwindow.split(`${rewriteMode ? '?' : '&'}theme=`)
      window.location = `${urlAux[0]
                + (rewriteMode ? '?' : '&')}theme=${
        $('[name=theme_select]').first().val()
      }&squelette=${
        $('[name=squelette_select]').first().val()}${$('[name=squelette_select]').first().val().slice(-'.tpl.html'.length) === '.tpl.html' ? '' : '.tpl.html'
      }&style=${
        $('[name=style_select]').first().val()}${$('[name=style_select]').first().slice(-'.css'.length) === '.css' ? '' : '.css'
      }&preset=${customCSSPresetsPrefix
      }${fullFileName}`
    },
    method: 'POST',
    data: {
      'primary-color': primaryColor,
      'secondary-color-1': secondaryColor1,
      'secondary-color-2': secondaryColor2,
      'neutral-color': neutralColor,
      'neutral-soft-color': neutralSoftColor,
      'neutral-light-color': neutralLightColor,
      'main-text-fontsize': mainTextFontsize,
      'main-text-fontfamily': mainTextFontfamily,
      'main-title-fontfamily': mainTitleFontfamily
    },
    cache: false,
    error(jqXHR, textStatus, errorThrown) {
      try {
        var data = JSON.parse(jqXHR.responseText)
        var dataMessage = data.message
      } catch (error) {
        var data = null
        var dataMessage = JSON.stringify(jqXHR.responseText)
      }
      let message = fullFileName + themeSelectorTranslation.TEMPLATE_FILE_NOT_ADDED
      let duration = 3000
      if (data && data.errorCode == 2) {
        message = `${message}\n${themeSelectorTranslation.TEMPLATE_FILE_ALREADY_EXISTING}`
        duration = 6000
      }
      console.log(`${message}. Message :${dataMessage}`)
      if (typeof toastMessage == 'function') {
        toastMessage(message, duration, 'alert alert-danger')
      } else {
        alert(message)
      }
    }
  })
}

function getActivePreset() {
  let presetKey = ''
  const selectedCssPresets = $('.css-preset.active')
  if (selectedCssPresets && selectedCssPresets.length > 0) {
    const selectedCssPreset = $(selectedCssPresets).first()
    const key = $(selectedCssPreset).data('key')
    if (key) {
      if ($(selectedCssPreset).hasClass('custom')) {
        presetKey = customCSSPresetsPrefix + key
      } else {
        presetKey = key
      }
    }
  }
  return presetKey
}

function saveTheme(event, url) {
  const { target } = event
  const form = $(target).closest('form')
  const theme = $(form).find('[name=theme_select]').first().val()
  console.log({ event, url, target, form, theme })
  const squelette = $(form).find('[name=squelette_select]').first().val()
  const style = $(form).find('[name=style_select]').val()
  const preset = getActivePreset()
  const errorMessage = themeSelectorTranslation.TEMPLATE_THEME_NOT_SAVE
  if (theme && squelette && style) {
    $('body').append(`<form id="templateFormSubmit" method="post" action="${url}" enctype="multipart/form-data">`
            + '<input type="hidden" name="action" value="setTemplate"/>'
            + `<input type="hidden" name="theme_select" value="${theme}"/>`
            + `<input type="hidden" name="squelette_select" value="${squelette}"/>`
            + `<input type="hidden" name="style_select" value="${style}"/>`
            + `<input type="hidden" name="preset_select" value="${preset}"/>`
            + '</form>')
    $('#templateFormSubmit').submit()
  } else if (typeof toastMessage == 'function') {
    toastMessage(errorMessage, 3000, 'alert alert-warning')
  } else {
    alert(errorMessage)
  }
  return false
}
