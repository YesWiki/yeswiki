// One file to the wiki's api/files and the markup that shows it: what the file picker, a paste and a drop in an editor share.
import prepareImageForUpload from './image-upload.js'
import { upload } from './upload.js'

/** Uploads a file as an attachment of pageTag, compressed like any upload; resolves the file entry, rejects with the server's error. */
export async function uploadFile(
  file,
  { pageTag = '', uploadToken = '', progress = null, signal = null } = {},
) {
  const prepared = await prepareImageForUpload(file)
  const formData = new FormData()
  formData.append('upFile', prepared)
  formData.append('pageTag', pageTag || wiki.pageTag)
  if (uploadToken) formData.append('uploadToken', uploadToken)

  const response = await upload(wiki.url('api/files'), formData, {
    progress,
    signal,
  })
  if (!response.ok || !response.body?.tag) {
    throw new Error(response.body?.error || _t('ERROR_NO_FILE_UPLOADED'))
  }
  return response.body
}

/** Where a file entry is served from, the one address that survives a rename. */
export function downloadUrl(entry) {
  return wiki.url(`api/files/${encodeURIComponent(entry.tag)}/download`)
}

/** The `{{attach}}` for a file entry; the options are the file picker's, and none gives the plain attachment. */
export function attachCode(entry, options = {}) {
  const esc = (value) => String(value).replace(/"/g, '&quot;')
  const desc = options.desc || entry.original_filename || entry.tag
  let code = `{{attach file="${esc(entry.tag)}" desc="${esc(desc)}"`
  if (options.displayPdf) code += ' displaypdf="1"'
  if (options.size) code += ` size="${esc(options.size)}"`
  const classes = [options.align, ...(options.effects ?? [])].filter(Boolean)
  if (classes.length) code += ` class="${esc(classes.join(' '))}"`
  if (options.link) code += ` link="${esc(options.link)}"`
  if (options.caption) code += ` caption="${esc(options.caption)}"`
  if (options.noFullImageLink) code += ' nofullimagelink="1"'
  return `${code}}}`
}

/** The markdown for a file entry: an image for an image, a link for anything else. */
export function attachMarkdown(entry, { alt = '', linkText = '' } = {}) {
  const isImage = entry.family === 'image'
  const label =
    (isImage ? alt : linkText || alt) || entry.original_filename || entry.tag
  const esc = (value) => String(value).replace(/([[\]()])/g, '\\$1')
  return `${isImage ? '!' : ''}[${esc(label)}](${downloadUrl(entry)})`
}
