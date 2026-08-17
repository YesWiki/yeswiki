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
