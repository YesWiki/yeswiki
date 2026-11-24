ywInitEach('#template-edit, .template-edit-form, body', () => {
  const applyBodyBackground = (styles) => {
    const base = {
      width: '100%',
      height: '100%',
      backgroundClip: 'border-box',
      backgroundOrigin: 'padding-box',
    }
    Object.assign(document.body.style, base, styles)
  }
  const coverBackground = (image) =>
    applyBodyBackground({
      backgroundImage: image,
      backgroundRepeat: 'no-repeat',
      backgroundSize: 'cover',
      backgroundAttachment: 'fixed',
      backgroundPosition: 'center center',
    })
  const mozaicBackground = (image) =>
    applyBodyBackground({
      backgroundImage: image,
      backgroundRepeat: 'repeat',
      backgroundSize: 'auto',
      backgroundAttachment: 'scroll',
      backgroundPosition: 'top left',
    })
  const unchoose = () => {
    document.querySelectorAll('#bgCarousel .choosen').forEach((el) => {
      el.classList.remove('choosen')
    })
  }

  document.querySelectorAll('#bgCarousel img.bgimg').forEach((img) => {
    img.addEventListener('click', () => {
      document.documentElement.style.width = '100%'
      document.documentElement.style.height = '100%'

      if (img.classList.contains('choosen')) {
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

  document.querySelectorAll('#bgCarousel div.mozaicimg').forEach((tile) => {
    tile.addEventListener('click', () => {
      if (tile.classList.contains('choosen')) {
        mozaicBackground('none')
        tile.classList.remove('choosen')
      } else {
        mozaicBackground(tile.style.backgroundImage)
        unchoose()
        tile.classList.add('choosen')
      }
    })
  })
})
