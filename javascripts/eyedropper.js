// A pipette beside a colour input, where the browser offers the EyeDropper API: it reads a pixel anywhere on screen into that input.

export function canPickFromScreen() {
  return typeof window.EyeDropper === 'function'
}

/** Opens the pipette and writes the picked colour into the input, telling its listeners; nothing changes when the pick is cancelled. */
export async function pickInto(input) {
  if (!input || !canPickFromScreen()) return
  let result
  try {
    result = await new window.EyeDropper().open()
  } catch {
    return
  }
  input.value = result.sRGBHex
  input.dispatchEvent(new Event('input', { bubbles: true }))
  input.dispatchEvent(new Event('change', { bubbles: true }))
}

document.addEventListener('click', (event) => {
  const button = event.target.closest?.('[data-yw-eyedropper]')
  if (!button) return
  event.preventDefault()
  pickInto(document.querySelector(button.dataset.ywEyedropper))
})

if (canPickFromScreen()) {
  window.ywInitEach('[data-yw-eyedropper][hidden]', (button) => {
    button.hidden = false
  })
}
