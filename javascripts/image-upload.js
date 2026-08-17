// Every image this wiki is given is converted and capped in the browser, before it is sent.
//
// A phone camera produces 12 megapixels of JPEG; a screenshot tool produces a PNG two or
// three times the size of the WebP that would look identical. Uploaded as-is, that is what
// the wiki stores, backs up, and serves -- and a page carrying four of them is a page nobody
// on a slow connection finishes loading. Doing it HERE rather than on the server means the
// bytes are never sent in the first place, which is also the slowest part of an upload on
// the connections that need this most.
//
// What is deliberately NOT converted:
//   - GIF, because a WebP made through a canvas keeps the first frame and drops the
//     animation. Silently turning somebody's animation into a still is worse than a big file.
//   - SVG, which has no resolution to cap and would be rasterised into something larger.
//   - anything already small enough at its natural size: re-encoding it costs quality and
//     saves nothing.
//
// The settings come from the wiki's configuration (CoreAssets::pageStateScript), so an
// instance can raise the cap, lower the quality, or turn the whole thing off with
// `image-upload-format: ''`.

import Compressor from './vendor/compressorjs/compressor.js'

/** What Compressor can redraw without losing something the file was for. */
const CONVERTIBLE = [
  'image/jpeg',
  'image/png',
  'image/webp',
  'image/bmp',
  'image/tiff',
]

/**
 * Qualities to try, in order, when the first result is still over the size limit.
 *
 * A ladder rather than a search: each step is a full re-encode of a possibly huge image, and
 * three of them is already most of a second on a phone. The last rung is a floor -- past it
 * the picture is worse than the problem, and the server's own limit is what says no.
 */
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

/**
 * Is this image bigger than the cap allows?
 *
 * The answer decides which file wins a tie later on: an image OVER the cap must be rewritten
 * whatever that costs in bytes, while one already inside it is only worth rewriting if the
 * rewrite is actually smaller. Re-encoding for the sake of a uniform extension is how a
 * 70-byte icon becomes a 300-byte icon.
 */
async function isOversized(file, config) {
  const bitmap = await createImageBitmap(file).catch(() => null)
  if (!bitmap) return false
  const oversized =
    bitmap.width > config.maxWidth || bitmap.height > config.maxHeight
  bitmap.close?.()
  return oversized
}

/**
 * The file to upload for the one that was chosen: WebP, within the cap, or the original.
 *
 * **Never rejects.** Every failure path -- an unsupported type, a browser without WebP
 * encoding, a corrupt file, a canvas that runs out of memory on a 100-megapixel scan --
 * returns the file that came in. Uploading the original is the outcome this had before; not
 * uploading anything because the optimiser tripped is a new way to lose somebody's work.
 */
export default async function prepareImageForUpload(file) {
  const config = settings()
  if (!config.format || !CONVERTIBLE.includes(file.type)) return file

  try {
    const oversized = await isOversized(file, config)
    // already WebP, already inside the cap, already small enough: every re-encode of a lossy
    // format loses a little, so uploading the same picture twice would make it worse twice
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
        // the PNG-to-JPEG rule this library applies above `convertSize` would quietly
        // overrule the format asked for -- and flatten transparency while doing it
        convertTypes: [],
        maxWidth: config.maxWidth,
        maxHeight: config.maxHeight,
        quality: config.quality * factor,
        // a JPEG says which way up it is in EXIF and browsers do not all agree; drawing it
        // through a canvas without this is how a portrait photo arrives on its side
        checkOrientation: true,
      })

      // the browser refuses a type it cannot encode by handing back the type it can -- so
      // the answer is only WebP if it says it is
      if (blob.type !== config.format) return file

      best = blob
      if (!config.maxSize || blob.size <= config.maxSize) break
    }

    // A rewrite that saved nothing is a rewrite not worth having -- the point is bytes on
    // the wire and on the disk, not a uniform extension. The exception is an image over the
    // cap, where the rewrite is what brings the resolution down and wins whatever it weighs.
    if (!oversized && best.size >= file.size) return file

    return new File([best], renameFor(file.name, config.format), {
      type: config.format,
      lastModified: Date.now(),
    })
  } catch {
    return file
  }
}
