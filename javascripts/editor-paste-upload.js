// What is pasted or dropped into an editor as files is uploaded to the page, its progress shown beside the editor, and its markup inserted.
import { attachCode, attachMarkdown, uploadFile } from './upload-file.js'
import { progressBlock } from './upload.js'

/** The files a paste carries, none when it also carries text: a cell copied from a spreadsheet comes with a picture of itself. */
export function pastedFiles(clipboard) {
  if (!clipboard) return []
  if ((clipboard.getData?.('text/plain') ?? '') !== '') return []
  return [...(clipboard.files ?? [])]
}

/** The files a drop carries. */
export function droppedFiles(transfer) {
  return [...(transfer?.files ?? [])]
}

/** Whether a drag holds files, so that the drop is ours rather than the editor's. */
export function dragsFiles(transfer) {
  return [...(transfer?.types ?? [])].includes('Files')
}

/** The page a file sent from this element belongs to, and the token that lets a reader send it: a field states them, a page editor is the page itself. */
export function uploadTargetOf(element) {
  const holder = element?.closest?.('[data-yw-upload-page-tag]')
  return {
    pageTag: holder?.dataset.ywUploadPageTag || wiki.pageTag,
    uploadToken: holder?.dataset.ywUploadToken || '',
  }
}

/** Where the uploads of this editor show, made on first use just after it. */
function trayOf(host) {
  const next = host.nextElementSibling
  if (next?.classList.contains('yw-upload-tray')) return next
  const tray = document.createElement('div')
  tray.className = 'yw-upload-tray'
  host.insertAdjacentElement('afterend', tray)
  return tray
}

/** Says why a file was not sent, in a toast when the page has them. */
function reportFailure(file, error) {
  const message = _t('UPLOAD_FAILED', {
    name: file.name,
    error: error?.message || _t('ERROR_NO_FILE_UPLOADED'),
  })
  if (typeof toastMessage === 'function') {
    toastMessage(message, 6000, 'alert alert-danger')
  } else {
    window.alert(message)
  }
}

/** Uploads each file in turn, then hands insert() the wiki code or markdown that shows it. */
export async function uploadIntoEditor(
  files,
  { host, format = 'wiki', insert },
) {
  const target = uploadTargetOf(host)
  for (const file of files) {
    const block = progressBlock(_t('UPLOAD_SENDING', { name: file.name }))
    trayOf(host).append(block)
    try {
      const entry = await uploadFile(file, { ...target, progress: block })
      insert(format === 'markdown' ? attachMarkdown(entry) : attachCode(entry))
    } catch (error) {
      reportFailure(file, error)
    } finally {
      block.remove()
    }
  }
}
