// Scroll tracking for `{{toc display="rail"}}` and `{{toc display="graduated-rail"}}`, and the folding of a long rail's sub-sections.

ywInitEach('[data-yw-toc-rail]', (rail) => {
  const graduated = rail.classList.contains('yw-toc--graduated')
  const foldable = rail.classList.contains('yw-toc--foldable')
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

  const sectionOf = new Map()
  let head = null
  Array.from(list.children).forEach((item) => {
    if (item.hasAttribute('data-yw-toc-section')) head = item
    sectionOf.set(item, head)
  })
  const sections = Array.from(sectionOf.values()).filter(
    (section, index, all) => section && all.indexOf(section) === index,
  )
  const chosen = new Map()

  /** Where, below the top of the window, a heading counts as reached: below the room an anchor jump leaves, at least a quarter down. */
  const readingLine = () =>
    Math.max(
      window.innerHeight * 0.25,
      (parseFloat(
        getComputedStyle(document.documentElement).scrollPaddingTop,
      ) || 0) + 48,
    )

  /** Opens the section being read and the ones opened by hand, and folds the others. */
  const fold = () => {
    if (!foldable) return
    const reading = active ? sectionOf.get(active.item) : null
    sections.forEach((section) => {
      const open = chosen.has(section)
        ? chosen.get(section)
        : section === reading
      section
        .querySelector('.yw-toc__toggle')
        ?.setAttribute('aria-expanded', String(open))
    })
    sectionOf.forEach((section, item) => {
      if (section && item !== section) {
        const button = section.querySelector('.yw-toc__toggle')
        item.classList.toggle(
          'yw-toc__item--folded',
          button?.getAttribute('aria-expanded') === 'false',
        )
      }
    })
  }

  /** Scrolls whatever holds the rail so the entry being read stays in sight. */
  const keepInSight = (item) => {
    let box = rail
    while (box && box !== document.body) {
      const overflow = getComputedStyle(box).overflowY
      if (
        (overflow === 'auto' || overflow === 'scroll') &&
        box.scrollHeight > box.clientHeight
      )
        break
      box = box.parentElement
    }
    if (!box || box === document.body) return
    const frameBox = box.getBoundingClientRect()
    const spot = item.getBoundingClientRect()
    if (spot.top < frameBox.top || spot.bottom > frameBox.bottom) {
      box.scrollTop += spot.top - frameBox.top - frameBox.height / 3
    }
  }

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
        const section = sectionOf.get(current.item)
        if (
          section &&
          section !== sectionOf.get(active?.item) &&
          chosen.get(section) === false
        ) {
          chosen.delete(section)
        }
      }
      active = current
      fold()
      if (current) keepInSight(current.item)
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

  if (foldable) {
    rail.addEventListener('click', (event) => {
      const button = event.target.closest('.yw-toc__toggle')
      if (!button) return
      chosen.set(
        button.closest('li'),
        button.getAttribute('aria-expanded') !== 'true',
      )
      fold()
      schedule()
    })
    rail.classList.add('yw-toc--ready')
    fold()
  }

  const observer = new ResizeObserver(remeasure)
  observer.observe(document.body)
  window.addEventListener('scroll', schedule, { passive: true })
  window.addEventListener('resize', remeasure)
  remeasure()
})
