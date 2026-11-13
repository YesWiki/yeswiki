// On a page that embeds a wiki page in an iframe.auto-resize, here or on another site: gives the frame the height the page inside reports (ticket 30).
;(() => {
  if (window.yeswikiIframeAutoResize) return
  window.yeswikiIframeAutoResize = true

  window.addEventListener('message', (event) => {
    if (event.data?.type !== 'yeswiki:iframe-height') return
    const height = Number(event.data.height)
    if (!Number.isFinite(height) || height <= 0) return
    const frame = [...document.querySelectorAll('iframe.auto-resize')].find(
      (candidate) => candidate.contentWindow === event.source,
    )
    if (frame) frame.style.height = `${Math.min(Math.ceil(height), 100000)}px`
  })
})()
