// template-edit.js — per-page look options in the edit handler (ticket 16:
// vanilla JS, fetch instead of $.post)
document.addEventListener('DOMContentLoaded', () => {
  const value = (selector) => {
    const el = document.querySelector(selector)
    return el ? el.value : undefined
  }
  const setValue = (selector, val) => {
    const el = document.querySelector(selector)
    if (el) el.value = val
  }
  const applyBodyBackground = (styles) => {
    const base = {
      width: '100%',
      height: '100%',
      backgroundClip: 'border-box',
      backgroundOrigin: 'padding-box'
    }
    Object.assign(document.body.style, base, styles)
  }
  const coverBackground = (image) => applyBodyBackground({
    backgroundImage: image,
    backgroundRepeat: 'no-repeat',
    backgroundSize: 'cover',
    backgroundAttachment: 'fixed',
    backgroundPosition: 'center center'
  })
  const mozaicBackground = (image) => applyBodyBackground({
    backgroundImage: image,
    backgroundRepeat: 'repeat',
    backgroundSize: 'auto',
    backgroundAttachment: 'scroll',
    backgroundPosition: 'top left'
  })
  const unchoose = () => {
    document.querySelectorAll('#bgCarousel .choosen').forEach((el) => {
      el.classList.remove('choosen')
    })
  }

  // on annule les changements de look
  document.querySelectorAll('#graphical_options a.button_cancel').forEach((cancel) => {
    cancel.addEventListener('click', () => {
      const form = document.querySelector('#graphical_options form')
      if (form) form.reset()
      if (
        value('[name=theme_select]') !== value('#hiddentheme')
        || value('#hiddensquelette') !== value('[name=squelette_select]')
        || value('#hiddenstyle') !== value('[name=style_select]')
      ) {
        // on charge le theme et on remet les valeurs
        const mainstyle = document.getElementById('mainstyle')
        const newstyle = mainstyle ? mainstyle.getAttribute('href') : null
        if (newstyle) {
          mainstyle.setAttribute(
            'href',
            `${newstyle.substring(0, newstyle.lastIndexOf('/'))}/${value('#hiddenstyle')}`
          )
        }
      }

      // l'image de fond
      unchoose()
      const hiddenimg = value('#hiddenbgimg') || ''
      if (hiddenimg !== '') {
        if (hiddenimg.substr(hiddenimg.length - 4) === '.jpg') {
          // pour le jpg
          const img = document.querySelector(`#bgCarousel .bgimg[src$="${hiddenimg}"]`)
          if (img) img.classList.add('choosen')
          coverBackground(`url(files/backgrounds/${hiddenimg})`)
        } else if (hiddenimg.substr(hiddenimg.length - 4) === '.png') {
          // pour le png
          const img = document.querySelector(`#bgCarousel .mozaicimg[style*="${hiddenimg}"]`)
          if (img) img.classList.add('choosen')
          mozaicBackground(`url(files/backgrounds/${hiddenimg})`)
        }
      } else {
        // on enleve les images de fond
        mozaicBackground('none')
      }

      // on remet les valeurs par défaut aux listes déroulantes
      setValue('[name=theme_select]', value('#hiddentheme'))
      setValue('[name=squelette_select]', value('#hiddensquelette'))
      setValue('[name=style_select]', value('#hiddenstyle'))
    })
  })

  // on sauve les metas et on transmet les valeurs changées du theme au formulaire
  document.querySelectorAll('#graphical_options a.button_save').forEach((save) => {
    save.addEventListener('click', () => {
      const theme = value('[name=theme_select]')
      setValue('#hiddentheme', theme)
      const squelette = value('[name=squelette_select]')
      setValue('#hiddensquelette', squelette)
      const style = value('[name=style_select]')
      const preset = value('[name=preset_select]')
      setValue('#hiddenstyle', style)
      const choosen = document.querySelector('.choosen')
      let bgimg = choosen ? choosen.style.backgroundImage : undefined
      const imgsrc = choosen ? choosen.getAttribute('src') : null

      if (bgimg && bgimg !== 'none') {
        bgimg = bgimg.substr(bgimg.lastIndexOf('/') + 1)
        bgimg = bgimg.replace('"', '').replace(')', '')
      } else if (typeof imgsrc === 'string') {
        bgimg = imgsrc.substr(imgsrc.lastIndexOf('/') + 1)
      } else {
        bgimg = ''
      }

      setValue('#hiddenbgimg', bgimg)

      // the non-*_select fields of the graphical options form
      const o = {}
      const optionsForm = document.getElementById('form_graphical_options')
      if (optionsForm) {
        new FormData(optionsForm).forEach((fieldValue, name) => {
          if (name.slice(-'_select'.length) === '_select') return
          if (o[name] !== undefined) {
            if (!Array.isArray(o[name])) {
              o[name] = [o[name]]
            }
            o[name].push(fieldValue || '')
          } else {
            o[name] = fieldValue || ''
          }
        })
      }
      const url = `${wiki.baseUrl}api/pages/${wiki.pageTag}/metadatas`

      const metadatas = {
        ...o,
        theme,
        squelette: squelette
          + (squelette.slice(-'.tpl.html'.length) === '.tpl.html' ? '' : '.tpl.html'),
        style: style + (style.slice(-'.css'.length) === '.css' ? '' : '.css'),
        bgimg
      }
      if (preset !== undefined) {
        metadatas.favorite_preset = preset
          + (preset.length === 0 || preset.slice(-'.css'.length) === '.css' ? '' : '.css')
      }

      const body = new URLSearchParams()
      Object.keys(metadatas).forEach((key) => {
        const metaValue = metadatas[key]
        if (Array.isArray(metaValue)) {
          metaValue.forEach((item) => body.append(`metadatas[${key}][]`, item))
        } else {
          body.append(`metadatas[${key}]`, metaValue)
        }
      })
      fetch(url, { method: 'POST', body }).catch(() => {
        /* keep silent, matching the old empty $.post callback */
      })
    })
  })

  // changement de fond d ecran
  document.querySelectorAll('#bgCarousel img.bgimg').forEach((img) => {
    img.addEventListener('click', () => {
      // Au cas ou le template ne le prend pas en compte, on met html à 100%
      document.documentElement.style.width = '100%'
      document.documentElement.style.height = '100%'

      if (img.classList.contains('choosen')) {
        // desactivation de la meme image de fond
        mozaicBackground('none')
        img.classList.remove('choosen')
      } else {
        const imgsrc = img.getAttribute('src').replace('thumbs/', '')
        unchoose()
        img.classList.add('choosen')
        coverBackground(`url(${imgsrc})`)
      }
    })
  })

  // changement de fond d ecran en mosaique
  document.querySelectorAll('#bgCarousel div.mozaicimg').forEach((tile) => {
    tile.addEventListener('click', () => {
      if (tile.classList.contains('choosen')) {
        // desactivation de la meme image de fond
        mozaicBackground('none')
        tile.classList.remove('choosen')
      } else {
        mozaicBackground(tile.style.backgroundImage)
        unchoose()
        tile.classList.add('choosen')
      }
    })
  })

  // on deplace hashcash au bon endroit
  const hashcash = document.getElementById('hashcash-text')
  const formActions = document.querySelector('#ACEditor .form-actions')
  if (hashcash && formActions) {
    formActions.appendChild(hashcash)
  }
})
