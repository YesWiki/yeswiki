// Scroll tracking for `{{toc display="rail"}}` and `{{toc display="graduated-rail"}}`.

ywInitEach('[data-yw-toc-rail]', (rail) => {
  const graduated = rail.classList.contains('yw-toc--graduated')
  const marker = rail.querySelector('.yw-toc__marker')
  const list = rail.querySelector('.yw-toc__list')
  const heading = rail.querySelector('.yw-toc__heading')
  const entries = Array.from(list.querySelectorAll('li'))
    .map((item) => {
      const link = item.querySelector('a')
      const id = link ? decodeURIComponent(link.hash.slice(1)) : ''
      return { item, link, target: id ? document.getElementById(id) : null }
    })
    .filter((entry) => entry.target)

  if (!entries.length) {
    return
  }

  let tops = []
  let end = 0
  let active = null
  let frame = 0

  /** Where, below the top of the window, a heading counts as reached. */
  const readingLine = () => window.innerHeight * 0.25

  /** Records where each section starts and where the last one ends, in page coordinates. */
  const measure = () => {
    tops = entries.map(
      ({ target }) => target.getBoundingClientRect().top + window.scrollY,
    )
    end = Math.max(
      tops[tops.length - 1] + 1,
      document.documentElement.scrollHeight -
        window.innerHeight +
        readingLine(),
    )
    if (graduated) {
      const span = end - tops[0]
      entries.forEach(({ item }, index) => {
        const next = index + 1 < tops.length ? tops[index + 1] : end
        item.style.setProperty(
          '--yw-toc-share',
          String((next - tops[index]) / span),
        )
      })
    }
  }

  /** Starts the rail below the floating page buttons, wherever they sit right now. */
  const clearPageActions = () => {
    const button = document.querySelector(
      '.yw-page-actions__edit, .yw-page-actions > :first-child',
    )
    if (!button) {
      rail.style.removeProperty('--yw-toc-rail-top')
      return
    }
    const rem = parseFloat(getComputedStyle(document.documentElement).fontSize)
    const bottom = Math.max(0, button.getBoundingClientRect().bottom)
    rail.style.setProperty(
      '--yw-toc-rail-top',
      `${Math.round(bottom + rem * (graduated && !heading ? 2 : 1.25))}px`,
    )
  }

  /** Moves the marker and flags the section being read. */
  const update = () => {
    frame = 0
    if (!rail.isConnected) {
      window.removeEventListener('scroll', schedule)
      window.removeEventListener('resize', remeasure)
      observer.disconnect()
      return
    }

    clearPageActions()
    const position = window.scrollY + readingLine()
    let index = -1
    tops.forEach((top, i) => {
      if (top <= position) {
        index = i
      }
    })

    const current = index >= 0 ? entries[index] : null
    if (current !== active) {
      if (active) {
        active.item.classList.remove('yw-toc__item--active')
        active.link.removeAttribute('aria-current')
      }
      if (current) {
        current.item.classList.add('yw-toc__item--active')
        current.link.setAttribute('aria-current', 'location')
      }
      active = current
    }

    if (graduated) {
      const progress = Math.max(
        0,
        Math.min(1, (position - tops[0]) / (end - tops[0])),
      )
      marker.style.transform = `translateY(${list.offsetTop + progress * list.offsetHeight}px)`
      rail.style.setProperty('--yw-toc-progress', String(progress))
    } else if (current) {
      marker.style.transform = `translateY(${current.item.offsetTop}px)`
      marker.style.height = `${current.item.offsetHeight}px`
    }
    rail.classList.toggle('yw-toc--started', index >= 0)
  }

  /** Batches scroll events into one update per frame. */
  function schedule() {
    if (!frame) {
      frame = window.requestAnimationFrame(update)
    }
  }

  /** Measures again once the layout has moved. */
  function remeasure() {
    measure()
    schedule()
  }

  const observer = new ResizeObserver(remeasure)
  observer.observe(document.body)
  window.addEventListener('scroll', schedule, { passive: true })
  window.addEventListener('resize', remeasure)
  remeasure()
})
