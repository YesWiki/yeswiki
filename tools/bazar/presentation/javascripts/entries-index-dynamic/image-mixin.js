const requestedRemoteImages = new Set()

export default {
  data() {
    return {
      tokenForImages: null,
      imagesToProcess: [],
      processingImage: false,
      remoteImages: {},
    }
  },
  methods: {
    isAbsoluteImageUrl(value) {
      return /^(https?:)?\/\//i.test(value)
    },
    urlImageResizedOnError(entry, fieldName, width, height, mode, token) {
      const node = window.event.target
      $(node).removeAttr('onerror')
      if (entry[fieldName] && !this.isAbsoluteImageUrl(entry[fieldName])) {
        const fileName = entry[fieldName]
        if (!this.isExternalUrl(entry)) {
          if (this.tokenForImages === null) {
            this.tokenForImages = token
          }
          this.imagesToProcess.push({
            apiPath: `${fileName}/cache/${width}/${height}/${mode}`,
            onSuccess: (cachefilename) =>
              this.replaceImageSource(node, cachefilename),
          })
          this.processNextImage()
        } else {
          const baseUrl = entry.url
            .slice(0, -entry.id_fiche.length)
            .replace(/\?$/, '')
            .replace(/\/$/, '')
          const previousUrl = $(node).prop('src')
          const newUrl = `${baseUrl}/files/${fileName}`
          if (newUrl !== previousUrl) {
            $(`img[src="${previousUrl}"]`).each(function () {
              $(this).prop('src', newUrl)
            })
          }
        }
      }
    },
    remoteImageUrl(entry, fieldName, width, height, mode, token) {
      const url = entry[fieldName]
      if (!token || this.isExternalUrl(entry)) {
        return url
      }
      const key = [entry.id_fiche, fieldName, width, height, mode, url].join(
        '|',
      )
      if (key in this.remoteImages) {
        return this.remoteImages[key]
      }
      if (!requestedRemoteImages.has(key)) {
        requestedRemoteImages.add(key)
        if (this.tokenForImages === null) {
          this.tokenForImages = token
        }
        const propertyName = this.fieldInfo(fieldName).propertyname || fieldName
        this.imagesToProcess.push({
          apiPath: `entry/${encodeURIComponent(entry.id_fiche)}/${encodeURIComponent(propertyName)}/cache/${width}/${height}/${mode}`,
          onSuccess: (cachefilename) => {
            this.remoteImages[key] = this.cachedImageUrl(cachefilename)
          },
          onFailure: () => {
            this.remoteImages[key] = url
          },
        })
        setTimeout(() => this.processNextImage())
      }
      return null
    },
    urlImage(entry, fieldName, width, height, mode, token = null) {
      if (!entry[fieldName]) {
        return null
      }
      if (this.isAbsoluteImageUrl(entry[fieldName])) {
        return this.remoteImageUrl(entry, fieldName, width, height, mode, token)
      }
      let baseUrl = this.isExternalUrl(entry)
        ? entry.url.slice(0, -entry.id_fiche.length)
        : wiki.baseUrl
      baseUrl = baseUrl.replace(/\?$/, '').replace(/\/$/, '')
      const fileName = entry[fieldName]
      const field = this.fieldInfo(fieldName)

      if (fileName.toLowerCase().endsWith('.svg'))
        return `${baseUrl}/files/${fileName}`

      let regExp = new RegExp(
        `^(${entry.id_fiche}_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`,
      )

      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode === 'fit' ? 'vignette' : 'cropped'}_${width}_${height}_$2_$3.$4`)}`
      }
      regExp = new RegExp(
        `^(${entry.id_fiche}_${field.propertyname}_.*)\\.([^.]+)$`,
      )
      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode === 'fit' ? 'vignette' : 'cropped'}_${width}_${height}.$2`)}`
      }
      // maybe from other entry
      regExp = new RegExp(
        `^([A-Za-z0-9-_]+_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`,
      )
      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode === 'fit' ? 'vignette' : 'cropped'}_${width}_${height}_$2_$3.$4`)}`
      }
      // last possible format
      regExp = new RegExp('^(.*)\\.([^.]+)$')
      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode === 'fit' ? 'vignette' : 'cropped'}_${width}_${height}.$2`)}`
      }
      return `${baseUrl}/files/${fileName}`
    },
    cachedImageUrl(cachefilename) {
      return `${wiki.baseUrl.replace(/(\?)?$/, '')}${cachefilename}`
    },
    replaceImageSource(node, cachefilename) {
      const previousUrl = $(node).prop('src')
      const srcFileName = this.cachedImageUrl(cachefilename)

      $(`img[src="${previousUrl}"]`).each(function () {
        const $img = $(this)
        $img.prop('src', srcFileName)

        const $next = $img.next('div.area.visual-area[style]')
        if ($next.length) {
          const backgroundImage = $next.css('background-image')
          if (backgroundImage && backgroundImage.length) {
            $next.css({ 'background-image': '' })
            $next.css({ 'background-image': `url("${srcFileName}")` })
          }
        }
      })
    },
    processNextImage() {
      if (!this.processingImage && this.imagesToProcess.length > 0) {
        this.processingImage = true
        const image = this.imagesToProcess[0]
        this.imagesToProcess = this.imagesToProcess.slice(1)
        $.ajax({
          url: wiki.url(`?api/images/${image.apiPath}`),
          method: 'post',
          data: { csrftoken: this.tokenForImages },
          cache: false,
          success: (data) => {
            if (data?.cachefilename) {
              image.onSuccess(data.cachefilename)
            } else {
              image.onFailure?.()
            }
          },
          error: () => {
            image.onFailure?.()
          },
          complete: (e) => {
            if (e.responseJSON != null && e.responseJSON.newToken != null) {
              this.tokenForImages = e.responseJSON.newToken
            }
            this.processingImage = false
            this.processNextImage()
          },
        })
      }
    },
  },
}
