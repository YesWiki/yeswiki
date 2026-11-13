// Inside a wiki page shown in an iframe: tells the embedding page how tall the content is, each time it changes (ticket 30).
;(() => {
  if (window.parent === window) return

  let sent = 0

  // The height of the content itself, not of the iframe around it, so the frame can shrink as well as grow.
  const measure = () => {
    const body = document.body
    const style = window.getComputedStyle(body)

    return Math.ceil(
      body.getBoundingClientRect().height +
        parseFloat(style.marginTop) +
        parseFloat(style.marginBottom),
    )
  }

  const report = () => {
    const height = measure()
    if (height === sent) return
    sent = height
    window.parent.postMessage({ type: 'yeswiki:iframe-height', height }, '*')
  }

  new ResizeObserver(report).observe(document.body)
  window.addEventListener('load', report)
  report()
})()
