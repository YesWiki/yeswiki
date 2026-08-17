// javascripts/inputs/file-upload-optimize.js -- a plain `<input type="file">` gets the same
// treatment the file-picker's upload pane does: an image is converted to WebP and capped
// before it is sent (javascripts/image-upload.js).
//
// The picker can do this inside its own `fetch`; a form field cannot, because the browser
// sends whatever the input holds at submit time. So the chosen file is REPLACED in the input
// with the optimised one, through a DataTransfer -- the only way to write a FileList.
//
// Two things make that safe to do asynchronously. The input is marked while the work runs
// and the form's submit buttons are disabled, so the original cannot be posted from under
// it; and a `submit` fired anyway (Enter in a text field, a script) is held until the
// replacement is in place. Without the second guard, a fast typist submits the 12-megapixel
// original and nothing anywhere says so.

import prepareImageForUpload from '../image-upload.js'

/** Forms with an optimisation still running, and the submit each is waiting to redo. */
const pending = new WeakMap()

function setBusy(form, busy) {
  if (!form) return
  form
    .querySelectorAll('button[type="submit"], input[type="submit"]')
    .forEach((button) => {
      button.disabled = busy
    })
}

async function optimise(input) {
  const file = input.files?.[0]
  if (!file) return

  const form = input.form
  input.dataset.ywOptimising = ''
  setBusy(form, true)
  try {
    const prepared = await prepareImageForUpload(file)
    if (prepared !== file) {
      const transfer = new DataTransfer()
      transfer.items.add(prepared)
      input.files = transfer.files
    }
  } finally {
    delete input.dataset.ywOptimising
    setBusy(form, false)
    // a submit that arrived while this was running was stopped, not lost
    const held = pending.get(form)
    if (held) {
      pending.delete(form)
      held.requestSubmit ? held.requestSubmit() : held.submit()
    }
  }
}

document.addEventListener('change', (event) => {
  const input = event.target.closest(
    'input[type="file"][data-yw-optimise-image]',
  )
  if (input) optimise(input)
})

document.addEventListener(
  'submit',
  (event) => {
    const form = event.target
    const busy = form.querySelector?.('[data-yw-optimising]')
    if (!busy) return
    event.preventDefault()
    pending.set(form, form)
  },
  true,
)
