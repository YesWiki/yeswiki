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
    urlImageResizedOnError(
      entry,
      fieldName,
      width,
      height,
      mode,
      token,
      event,
    ) {
      const node = event.target
      node.removeAttribute('onerror')
      if (entry[fieldName] && !this.isAbsoluteImageUrl(entry[fieldName])) {
        const fileName = entry[fieldName]
        if (!this.isExternalUrl(entry)) {
          if (this.tokenForImages === null) {
            this.tokenForImages = token
          }
          this.imagesToProcess.push({
            apiPath: `${fileName}/cache/${width}/${height}/${mode}`,
            onSuccess: (data) =>
              this.replaceImageSource(
                node,
                `${wiki.baseUrl.replace(/(\?)?$/, '')}${data.cachefilename}`,
              ),
          })
          this.processNextImage()
        } else {
          const baseUrl = entry.url
            .slice(0, -entry.tag.length)
            .replace(/\?$/, '')
            .replace(/\/$/, '')
          const previousUrl = node.src
          const newUrl = `${baseUrl}/files/${fileName}`
          if (newUrl !== previousUrl) {
            document
              .querySelectorAll(`img[src="${previousUrl}"]`)
              .forEach((imgParam) => {
                const img = imgParam
                img.src = newUrl
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
      const key = [entry.tag, fieldName, width, height, mode, url].join('|')
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
          apiPath: `entry/${encodeURIComponent(entry.tag)}/${encodeURIComponent(propertyName)}/cache/${width}/${height}/${mode}`,
          onSuccess: (data) => {
            this.remoteImages[key] = data.url || url
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
        ? entry.url.slice(0, -entry.tag.length)
        : wiki.baseUrl
      baseUrl = baseUrl.replace(/\?$/, '').replace(/\/$/, '')
      const fileName = entry[fieldName]
      const field = this.fieldInfo(fieldName)

      if (fileName.toLowerCase().endsWith('.svg'))
        return `${baseUrl}/files/${fileName}`

      let regExp = new RegExp(
        `^(${entry.tag}_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`,
      )

      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode === 'fit' ? 'vignette' : 'cropped'}_${width}_${height}_$2_$3.$4`)}`
      }
      regExp = new RegExp(`^(${entry.tag}_${field.propertyname}_.*)\\.([^.]+)$`)
      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode === 'fit' ? 'vignette' : 'cropped'}_${width}_${height}.$2`)}`
      }
      regExp = new RegExp(
        `^([A-Za-z0-9-_]+_${field.propertyname}_.*)_(\\d{14})_(\\d{14})\\.([^.]+)$`,
      )
      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode === 'fit' ? 'vignette' : 'cropped'}_${width}_${height}_$2_$3.$4`)}`
      }
      regExp = new RegExp('^(.*)\\.([^.]+)$')
      if (regExp.test(fileName)) {
        return `${baseUrl}/cache/${fileName.replace(regExp, `$1_${mode === 'fit' ? 'vignette' : 'cropped'}_${width}_${height}.$2`)}`
      }
      return `${baseUrl}/files/${fileName}`
    },
    replaceImageSource(node, srcFileName) {
      const previousUrl = node.src
      document
        .querySelectorAll(`img[src="${previousUrl}"]`)
        .forEach((imgParam) => {
          const img = imgParam
          img.src = srcFileName

          const next = img.nextElementSibling
          if (next && next.matches('div.area.visual-area[style]')) {
            const { backgroundImage } = next.style
            if (backgroundImage && backgroundImage.length) {
              next.style.backgroundImage = ''
              next.style.backgroundImage = `url("${srcFileName}")`
            }
          }
        })
    },
    processNextImage() {
      if (!this.processingImage && this.imagesToProcess.length > 0) {
        this.processingImage = true
        const image = this.imagesToProcess[0]
        this.imagesToProcess = this.imagesToProcess.slice(1)
        fetch(wiki.url(`?api/images/${image.apiPath}`), {
          method: 'POST',
          body: new URLSearchParams({ csrftoken: this.tokenForImages }),
        })
          .then((response) =>
            response.json().then((data) => ({ ok: response.ok, data })),
          )
          .then(({ ok, data }) => {
            if (data != null && data.newToken != null) {
              this.tokenForImages = data.newToken
            }
            if (ok) {
              image.onSuccess(data)
            } else {
              image.onFailure?.()
            }
          })
          .catch(() => image.onFailure?.())
          .finally(() => {
            this.processingImage = false
            this.processNextImage()
          })
      }
    },
  },
}
