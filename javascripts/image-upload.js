import Compressor from './vendor/compressorjs/compressor.js'

/** What Compressor can redraw without losing something the file was for. */
const CONVERTIBLE = [
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/bmp',
  'image/tiff',
]

/** Qualities to try, in order, when the first result is still over the size limit. */
const QUALITY_STEPS = [1, 0.7, 0.5]

/** The wiki's image-upload settings, with the defaults core ships. */
function settings() {
  const configured = (typeof wiki !== 'undefined' && wiki.imageUpload) || {}
  return {
    format: 'image/webp',
    maxWidth: 1920,
    maxHeight: 1920,
    quality: 0.82,
    maxSize: 0,
    ...configured,
  }
}

/** A file's name with the extension the new type needs. */
function renameFor(name, mimeType) {
  const extension = mimeType === 'image/webp' ? 'webp' : mimeType.split('/')[1]
  return `${name.replace(/\.[^.]+$/, '')}.${extension}`
}

/** One pass of Compressor, resolved with the Blob it produced. */
function compress(file, options) {
  return new Promise((resolve, reject) => {
    new Compressor(file, {
      ...options,
      success: resolve,
      error: reject,
    })
  })
}

/** Is this image bigger than the cap allows? */
async function isOversized(file, config) {
  const bitmap = await createImageBitmap(file).catch(() => null)
  if (!bitmap) return false
  const oversized =
    bitmap.width > config.maxWidth || bitmap.height > config.maxHeight
  bitmap.close?.()
  return oversized
}

/** The file to upload for the one that was chosen: WebP, within the cap, or the original. */
export default async function prepareImageForUpload(file) {
  const config = settings()
  if (!config.format || !CONVERTIBLE.includes(file.type)) return file

  try {
    const oversized = await isOversized(file, config)
    if (
      !oversized &&
      file.type === config.format &&
      (!config.maxSize || file.size <= config.maxSize)
    ) {
      return file
    }

    let best = file
    for (const factor of QUALITY_STEPS) {
      const blob = await compress(file, {
        mimeType: config.format,
        convertTypes: [],
        maxWidth: config.maxWidth,
        maxHeight: config.maxHeight,
        quality: config.quality * factor,
        checkOrientation: true,
      })

      if (blob.type !== config.format) return file

      best = blob
      if (!config.maxSize || blob.size <= config.maxSize) break
    }

    if (!oversized && best.size >= file.size) return file

    return new File([best], renameFor(file.name, config.format), {
      type: config.format,
      lastModified: Date.now(),
    })
  } catch {
    return file
  }
}
