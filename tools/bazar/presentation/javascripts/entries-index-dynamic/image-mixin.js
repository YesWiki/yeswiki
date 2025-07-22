export default {
  data: {
    tokenForImages: null,
    imagesToProcess: [],
    processingImage: false
  },
  methods: {
    urlImageResizedOnError(entry, fieldName, width, height, mode, token) {
      const node = event.target
      $(node).removeAttr('onerror')
      if (entry[fieldName]) {
        const fileName = entry[fieldName]
        if (!this.isExternalUrl(entry)) {
          // currently not supporting api for external images (anti-csrf token not generated)
          if (this.tokenForImages === null) {
            this.tokenForImages = token
          }
          this.imagesToProcess.push({
            fileName,
            width,
            height,
            mode,
            node
          })
          this.processNextImage()
        } else {
          const baseUrl = entry.url
            .slice(0, -entry.id_fiche.length)
            .replace(/\?$/, '')
            .replace(/\/$/, '')
          const previousUrl = $(node).prop('src')
          const newUrl = `${baseUrl}/files/${fileName}`
          if (newUrl != previousUrl) {
            $(`img[src="${previousUrl}"]`).each(function() {
              $(this).prop('src', newUrl)
            })
          }
        }
      }
    },
    urlImage(entry, fieldName, width, height, mode) {
      if (!entry[fieldName]) {
        return null
      }
      let baseUrl = this.isExternalUrl(entry)
        ? entry.url.slice(0, -entry.id_fiche.length)
        : wiki.baseUrl
      baseUrl = baseUrl.replace(/\?$/, '').replace(/\/$/, '')
      const fileName = entry[fieldName]
      const field = this.fieldInfo(fieldName)

      if (fileName.toLowerCase().endsWith('.svg')) return `${baseUrl}/files/${fileName}`

      let regExp = new RegExp(
        `^(${entry.id_fiche}_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`
      )

      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode == 'fit' ? 'vignette' : 'cropped'}_${width}_${height}_$2_$3.$4`)}`
      }
      regExp = new RegExp(
        `^(${entry.id_fiche}_${field.propertyname}_.*)\\.([^.]+)$`
      )
      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode == 'fit' ? 'vignette' : 'cropped'}_${width}_${height}.$2`)}`
      }
      // maybe from other entry
      regExp = new RegExp(
        `^([A-Za-z0-9-_]+_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`
      )
      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode == 'fit' ? 'vignette' : 'cropped'}_${width}_${height}_$2_$3.$4`)}`
      }
      // last possible format
      regExp = new RegExp('^(.*)\\.([^.]+)$')
      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode == 'fit' ? 'vignette' : 'cropped'}_${width}_${height}.$2`)}`
      }
      return `${baseUrl}/files/${fileName}`
    },
    processNextImage() {
      if (!this.processingImage && this.imagesToProcess.length > 0) {
        this.processingImage = true
        const newImageParams = this.imagesToProcess[0]
        this.imagesToProcess = this.imagesToProcess.slice(1)
        $.ajax({
          url: wiki.url(
            `?api/images/${newImageParams.fileName}/cache/${newImageParams.width}/${newImageParams.height}/${newImageParams.mode}`
          ),
          method: 'post',
          data: { csrftoken: this.tokenForImages },
          cache: false,
          success: (data) => {
            const previousUrl = $(newImageParams.node).prop('src')
            const srcFileName = `${wiki.baseUrl.replace(/(\?)?$/, '')}${data.cachefilename}`

            $(`img[src="${previousUrl}"]`).each(function() {
              const $img = $(this)
              $img.prop('src', srcFileName)

              const $next = $img.next('div.area.visual-area[style]')
              if ($next.length) {
                const backgroundImage = $next.css('background-image')
                if (backgroundImage && backgroundImage.length) {
                  $next.css({ 'background-image': '' }) // reset to force update
                  $next.css({ 'background-image': `url("${srcFileName}")` })
                }
              }
            })
          },
          complete: (e) => {
            if (e.responseJSON != undefined && e.responseJSON.newToken != undefined) {
              this.tokenForImages = e.responseJSON.newToken
            }
            this.processingImage = false
            this.processNextImage()
          }
        })
      }
    }
  }
}
