// Opens the print dialog from the printable presentations' button, and shrinks their sheets on screen without reflowing them.
ywInitEach('[data-yw-print]', (button) => {
  button.addEventListener('click', () => window.print())
})

ywInitEach('.yw-print', (print) => {
  const fit = () => {
    const sheet = print.querySelector('.yw-print__sheet')
    if (!sheet) return
    print.style.setProperty(
      '--yw-print-scale',
      Math.min(1, print.clientWidth / sheet.offsetWidth),
    )
  }
  new ResizeObserver(fit).observe(print)
  fit()
})
