export default {
  data() {
    return {
      tokenForImages: null,
      imagesToProcess: [],
      processingImage: false
    }
  },
  methods: {
    urlImageResizedOnError(entry, fieldName, width, height, mode, token) {
      const node = event.target
      node.removeAttribute('onerror')
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
            .slice(0, -entry.tag.length)
            .replace(/\?$/, '')
            .replace(/\/$/, '')
          const previousUrl = node.src
          const newUrl = `${baseUrl}/files/${fileName}`
          if (newUrl != previousUrl) {
            document.querySelectorAll(`img[src="${previousUrl}"]`).forEach((imgParam) => {
              const img = imgParam
              img.src = newUrl
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
        ? entry.url.slice(0, -entry.tag.length)
        : wiki.baseUrl
      baseUrl = baseUrl.replace(/\?$/, '').replace(/\/$/, '')
      const fileName = entry[fieldName]
      const field = this.fieldInfo(fieldName)

      if (fileName.toLowerCase().endsWith('.svg')) return `${baseUrl}/files/${fileName}`

      let regExp = new RegExp(
        `^(${entry.tag}_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`
      )

      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode == 'fit' ? 'vignette' : 'cropped'}_${width}_${height}_$2_$3.$4`)}`
      }
      regExp = new RegExp(
        `^(${entry.tag}_${field.propertyname}_.*)\\.([^.]+)$`
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
        const { fileName, width, height } = newImageParams
        const cacheUrl = wiki.url(
          `?api/images/${fileName}/cache/${width}/${height}/${newImageParams.mode}`
        )
        fetch(cacheUrl, {
          method: 'POST',
          body: new URLSearchParams({ csrftoken: this.tokenForImages })
        })
          .then((response) => response.json()
            .then((data) => ({ ok: response.ok, data })))
          .then(({ ok, data }) => {
            if (data != undefined && data.newToken != undefined) {
              this.tokenForImages = data.newToken
            }
            if (!ok) return
            const previousUrl = newImageParams.node.src
            const srcFileName = `${wiki.baseUrl.replace(/(\?)?$/, '')}${data.cachefilename}`

            document.querySelectorAll(`img[src="${previousUrl}"]`).forEach((imgParam) => {
              const img = imgParam
              img.src = srcFileName

              const next = img.nextElementSibling
              if (next && next.matches('div.area.visual-area[style]')) {
                const { backgroundImage } = next.style
                if (backgroundImage && backgroundImage.length) {
                  next.style.backgroundImage = '' // reset to force update
                  next.style.backgroundImage = `url("${srcFileName}")`
                }
              }
            })
          })
          .catch(() => { /* image cache generation failed, keep the original src */ })
          .finally(() => {
            this.processingImage = false
            this.processNextImage()
          })
      }
    }
  }
}
