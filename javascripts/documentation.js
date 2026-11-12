/* The documentation page: copy buttons on code blocks, syntax colours, and old Docsify addresses (#/docs/fr/page?id=title) sent to their page and heading. */
;(() => {
  const root = document.querySelector('.yw-doc')
  if (!root) return

  const decode = (text) => {
    try {
      return decodeURIComponent(text)
    } catch {
      return text
    }
  }

  const docsifySlug = (text) =>
    text
      .trim()
      .normalize('NFC')
      .replace(/'/g, '39')
      .replace(/"/g, 'quot')
      .replace(/\uFE0F/g, '')
      .replace(/[\p{Emoji_Presentation}\p{Extended_Pictographic}]/gu, '')
      .replace(/[A-Z]+/g, (letters) => letters.toLowerCase())
      .replace(
        /[\u2000-\u206F\u2E00-\u2E7F\\'!"#$%&()*+,./:;<=>?@[\]^`{|}~]/g,
        '',
      )
      .replace(/\s/g, '-')
      .replace(/^(\d)/, '_$1')

  const headingText = (heading) => {
    const copy = heading.cloneNode(true)
    copy
      .querySelectorAll('.yw-doc__anchor')
      .forEach((anchor) => anchor.remove())
    return copy.textContent
  }

  const legacy =
    /^#\/docs\/[a-z]{2}\/([A-Za-z0-9_-]+?)(?:\.md)?(?:\?id=(.*))?$/.exec(
      decode(window.location.hash),
    )
  if (legacy) {
    const page =
      legacy[1] === 'README'
        ? root.dataset.home
        : `${root.dataset.home}/${legacy[1]}`
    window.location.replace(
      page + (legacy[2] ? `#${encodeURIComponent(legacy[2])}` : ''),
    )
    return
  }

  const wanted = decode(window.location.hash.slice(1))
  if (wanted && !document.getElementById(wanted)) {
    const heading = [
      ...root.querySelectorAll(
        '.yw-doc__content :is(h1, h2, h3, h4, h5, h6)[id]',
      ),
    ].find(
      (candidate) =>
        docsifySlug(headingText(candidate)).replace(/^_?[\d-]+-/, '') ===
        wanted.replace(/^_?[\d-]+-/, ''),
    )
    if (heading) {
      window.history.replaceState(null, '', `#${heading.id}`)
      heading.scrollIntoView()
    }
  }

  root.querySelectorAll('.yw-doc__content pre').forEach((pre) => {
    if (pre.querySelector('.yw-doc__copy')) return
    const button = document.createElement('button')
    button.type = 'button'
    button.className = 'yw-doc__copy'
    button.textContent = root.dataset.copy
    pre.appendChild(button)
  })

  if (window.Prism) window.Prism.highlightAllUnder(root)

  if (window.ywDocCopyListening) return
  window.ywDocCopyListening = true
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('.yw-doc__copy')
    if (!button) return
    const doc = button.closest('.yw-doc')
    const code = button.closest('pre').querySelector('code')
    await navigator.clipboard.writeText(code ? code.textContent : '')
    button.classList.add('success')
    button.textContent = doc.dataset.copied
    setTimeout(() => {
      button.classList.remove('success')
      button.textContent = doc.dataset.copy
    }, 2000)
  })
})()
