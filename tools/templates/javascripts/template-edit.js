$(document).ready(() => {
  // on annule les changements de look
  $('#graphical_options a.button_cancel').on('click', () => {
    $('#graphical_options form')[0].reset()
    if (($('[name=theme_select]').first().val() !== $('#hiddentheme').val()) || ($('#hiddensquelette').val() !== $('[name=squelette_select]').first().val()) || ($('#hiddenstyle').val() !== $('[name=style_select]').first().val())) {
      // on charge le theme et on remet les valeurs
      let newstyle = $('#mainstyle').attr('href')
      if (newstyle) {
        newstyle = `${newstyle.substring(0, newstyle.lastIndexOf('/'))}/${$('#hiddenstyle').val()}`
        $('#mainstyle').attr('href', newstyle)
      }
    }

    // l'image de fond
    $('#bgCarousel .choosen').removeClass('choosen')
    const hiddenimg = $('#hiddenbgimg').val()
    if (hiddenimg !== '') {
      // pour le jpg
      if (hiddenimg.substr(hiddenimg.length - 4) === '.jpg') {
        $(`#bgCarousel .bgimg[src$="${hiddenimg}"]`).addClass('choosen')
        $('body').css({
          'background-image': `url(files/backgrounds/${hiddenimg})`,
          'background-repeat': 'no-repeat',
          width: '100%',
          height: '100%',
          '-webkit-background-size': 'cover',
          '-moz-background-size': 'cover',
          '-o-background-size': 'cover',
          'background-size': 'cover',
          'background-attachment': 'fixed',
          'background-clip': 'border-box',
          'background-origin': 'padding-box',
          'background-position': 'center center'
        })
      }
      // pour le png
      else if (hiddenimg.substr(hiddenimg.length - 4) === '.png') {
        $(`#bgCarousel .mozaicimg[style*="${hiddenimg}"]`).addClass('choosen')
        $('body').css({
          'background-image': `url(files/backgrounds/${hiddenimg})`,
          'background-repeat': 'repeat',
          width: '100%',
          height: '100%',
          '-webkit-background-size': 'auto',
          '-moz-background-size': 'auto',
          '-o-background-size': 'auto',
          'background-size': 'auto',
          'background-attachment': 'scroll',
          'background-clip': 'border-box',
          'background-origin': 'padding-box',
          'background-position': 'top left'
        })
      }
    } else {
      // on enleve les images de fond
      $('body').css({
        'background-image': 'none',
        'background-repeat': 'repeat',
        width: '100%',
        height: '100%',
        '-webkit-background-size': 'auto',
        '-moz-background-size': 'auto',
        '-o-background-size': 'auto',
        'background-size': 'auto',
        'background-attachment': 'scroll',
        'background-clip': 'border-box',
        'background-origin': 'padding-box',
        'background-position': 'top left'
      })
    }

    // on remet les valeurs par défaut aux listes déroulantes
    $('[name=theme_select]').first().val($('#hiddentheme').val())
    $('[name=squelette_select]').first().val($('#hiddensquelette').val())
    $('[name=style_select]').first().val($('#hiddenstyle').val())
  })

  // on sauve les metas et on transmet les valeurs changées du theme au formulaire
  $('#graphical_options a.button_save').on('click', () => {
    const theme = $('[name=theme_select]').first().val()
    $('#hiddentheme').val(theme)
    const squelette = $('[name=squelette_select]').first().val()
    $('#hiddensquelette').val(squelette)
    const style = $('[name=style_select]').first().val()
    const preset = $('[name=preset_select]').first().val()
    $('#hiddenstyle').val(style)
    let bgimg = $('.choosen').css('background-image')
    const imgsrc = $('.choosen').attr('src')

    if (!(typeof bgimg === 'undefined') && bgimg != 'none') {
      bgimg = bgimg.substr(bgimg.lastIndexOf('/') + 1, bgimg.length - bgimg.lastIndexOf('/'))
      bgimg = bgimg.replace('"', '').replace(')', '')
    } else if (typeof imgsrc === 'string') {
      bgimg = imgsrc.substr(imgsrc.lastIndexOf('/') + 1, imgsrc.length - imgsrc.lastIndexOf('/'))
    } else {
      bgimg = ''
    }

    $('#hiddenbgimg').val(bgimg)

    const o = {}
    const a = $('#form_graphical_options').serializeArray()

    $.each(a, function() {
      if (this.name.slice(-'_select'.length) != '_select') {
        if (o[this.name] !== undefined) {
          if (!o[this.name].push) {
            o[this.name] = [o[this.name]]
          }
          o[this.name].push(this.value || '')
        } else {
          o[this.name] = this.value || ''
        }
      }
    })
    const url = `${document.URL.split('/edit')[0]}/savemetadatas`

    const data = {
      metadatas: $.extend({}, o, {
        theme,
        squelette: squelette + (squelette.slice(-'.tpl.html'.length) === '.tpl.html' ? '' : '.tpl.html'),
        style: style + (style.slice(-'.css'.length) === '.css' ? '' : '.css'),
        bgimg
      })
    }
    if (preset != undefined) {
      data.metadatas.favorite_preset = preset + (preset.length == 0 || preset.slice(-'.css'.length) === '.css' ? '' : '.css')
    }

    $.post(url, data, (data) => {

    })
  })

  // changement de fond d ecran
  $('#bgCarousel img.bgimg').on('click', function() {
    // Au cas ou le template ne le prend pas en compte, on met html à 100%
    $('html').css({
      width: '100%',
      height: '100%'
    })

    // desactivation de la meme image de fond
    if ($(this).hasClass('choosen')) {
      $('body').css({
        'background-image': 'none',
        'background-repeat': 'repeat',
        width: '100%',
        height: '100%',
        '-webkit-background-size': 'auto',
        '-moz-background-size': 'auto',
        '-o-background-size': 'auto',
        'background-size': 'auto',
        'background-attachment': 'scroll',
        'background-clip': 'border-box',
        'background-origin': 'padding-box',
        'background-position': 'top left'
      })
      $(this).removeClass('choosen')
    } else {
      let imgsrc = $(this).attr('src')
      imgsrc = imgsrc.replace('thumbs/', '')
      $('#bgCarousel .choosen').removeClass('choosen')
      $(this).addClass('choosen')
      $('body').css({
        'background-image': `url(${imgsrc})`,
        'background-repeat': 'no-repeat',
        width: '100%',
        height: '100%',
        '-webkit-background-size': 'cover',
        '-moz-background-size': 'cover',
        '-o-background-size': 'cover',
        'background-size': 'cover',
        'background-attachment': 'fixed',
        'background-clip': 'border-box',
        'background-origin': 'padding-box',
        'background-position': 'center center'
      })
    }
  })

  // changement de fond d ecran en mosaique
  $('#bgCarousel div.mozaicimg').on('click', function() {
    // desactivation de la meme image de fond
    if ($(this).hasClass('choosen')) {
      $('body').css({
        'background-image': 'none',
        'background-repeat': 'repeat',
        width: '100%',
        height: '100%',
        '-webkit-background-size': 'auto',
        '-moz-background-size': 'auto',
        '-o-background-size': 'auto',
        'background-size': 'auto',
        'background-attachment': 'scroll',
        'background-clip': 'border-box',
        'background-origin': 'padding-box',
        'background-position': 'top left'
      })
      $(this).removeClass('choosen')
    } else {
      $('body').css({
        'background-image': $(this).css('background-image'),
        'background-repeat': 'repeat',
        width: '100%',
        height: '100%',
        '-webkit-background-size': 'auto',
        '-moz-background-size': 'auto',
        '-o-background-size': 'auto',
        'background-size': 'auto',
        'background-attachment': 'scroll',
        'background-clip': 'border-box',
        'background-origin': 'padding-box',
        'background-position': 'top left'
      })
      $('#bgCarousel .choosen').removeClass('choosen')
      $(this).addClass('choosen')
    }
  })

  // on deplace hashcash au bon endroit
  $('#hashcash-text').appendTo('#ACEditor .form-actions')
})
