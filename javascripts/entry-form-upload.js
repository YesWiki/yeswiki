// An entry form carrying more than a couple of megabytes of files is sent in the background, so the visitor sees them go; a lighter one submits as it always did.
import { progressBlock, upload } from './upload.js'

export const BACKGROUND_FROM_BYTES = 2 * 1024 * 1024

/** How many bytes of files a form would send. */
export function filesSize(form) {
  return [...form.querySelectorAll('input[type="file"]')]
    .flatMap((input) => [...(input.files ?? [])])
    .reduce((total, file) => total + file.size, 0)
}

/** Lands where the normal submit would: the page a redirect named, or the page the wiki answered with. */
function follow(response, action) {
  if (response.url && new URL(response.url).href !== new URL(action).href) {
    window.location.assign(response.url)
    return
  }
  document.open()
  document.write(response.text)
  document.close()
}

/** Gives the buttons back after a failure, as bazar.js labelled them while saving. */
function releaseButtons(form) {
  form
    .querySelectorAll('.form-actions button[type=submit]')
    .forEach((button) => {
      if (button.dataset.label !== undefined)
        button.innerHTML = button.dataset.label
      button.removeAttribute('disabled')
      button.classList.remove('submit-disabled')
    })
}

document.addEventListener('submit', async (event) => {
  const form = event.target
  if (
    event.defaultPrevented ||
    !(form instanceof HTMLFormElement) ||
    !form.matches('.bazar-form') ||
    filesSize(form) <= BACKGROUND_FROM_BYTES
  ) {
    return
  }
  event.preventDefault()
  const data = new FormData(form, event.submitter ?? undefined)
  const progress = progressBlock(_t('UPLOAD_SENDING_ENTRY'))
  form.querySelector('.form-actions')?.before(progress)
  try {
    follow(
      await upload(form.action, data, { progress, accept: 'text/html' }),
      form.action,
    )
  } catch (error) {
    progress.remove()
    releaseButtons(form)
    const message = _t('UPLOAD_ENTRY_FAILED', {
      error: error?.message || _t('ERROR_NO_FILE_UPLOADED'),
    })
    if (typeof toastMessage === 'function') {
      toastMessage(message, 6000, 'alert alert-danger')
    } else {
      window.alert(message)
    }
  }
})
