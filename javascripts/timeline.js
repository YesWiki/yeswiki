;(function () {
  ywInitEach('#ss-container', () => {
    const container = document.getElementById('ss-container')
    if (!container) return

    const rows = Array.from(container.querySelectorAll(':scope > div.ss-row'))
    const links = document.querySelectorAll('#ss-links > a')
    const perspective = CSS.supports('perspective', '600px')
    let rowsOutViewport = []
    let winHeight = window.innerHeight
    let scrollScheduled = false

    function rowTop(row) {
      return row.getBoundingClientRect().top + window.scrollY
    }

    function setViewportRows() {
      rowsOutViewport = rows.filter((row) => rowTop(row) >= winHeight)
    }

    function showCircle(row, shown) {
      row.querySelectorAll('a.ss-circle').forEach((circle) => {
        circle.classList.toggle('ss-circle-deco', shown)
      })
    }

    function setSide(el, transform, opacity, prop, offset) {
      if (!el) return
      const side = el
      if (perspective) {
        side.style.transform = transform
        side.style.opacity = opacity
      } else {
        side.style[prop] = offset
      }
    }

    function placeRows() {
      const winCenter = winHeight / 2 + window.scrollY

      rowsOutViewport.forEach((row) => {
        const left = row.querySelector('div.ss-left')
        const right = row.querySelector('div.ss-right')
        const top = rowTop(row)

        if (top > winHeight + window.scrollY) {
          setSide(
            left,
            'translate3d(-75%, 0, 0) rotateY(-90deg) translate3d(-75%, 0, 0)',
            0,
            'left',
            '-50%',
          )
          setSide(
            right,
            'translate3d(75%, 0, 0) rotateY(90deg) translate3d(75%, 0, 0)',
            0,
            'right',
            '-50%',
          )
        } else {
          const height = row.offsetHeight
          const factor =
            (top + height / 2 - winCenter) / (winHeight / 2 + height / 2)
          const val = Math.max(factor * 50, 0)

          showCircle(row, val <= 0)

          const t = Math.max(factor * 75, 0)
          const r = Math.max(factor * 90, 0)
          const o = Math.min(Math.abs(factor - 1), 1)
          setSide(
            left,
            `translate3d(-${t}%, 0, 0) rotateY(-${r}deg) translate3d(-${t}%, 0, 0)`,
            o,
            'left',
            `${-val}%`,
          )
          setSide(
            right,
            `translate3d(${t}%, 0, 0) rotateY(${r}deg) translate3d(${t}%, 0, 0)`,
            o,
            'right',
            `${-val}%`,
          )
        }
      })
    }

    links.forEach((link) => {
      link.addEventListener('click', (e) => {
        e.preventDefault()
        const target = document.querySelector(link.getAttribute('href'))
        if (target)
          target.scrollIntoView({ behavior: 'smooth', block: 'start' })
      })
    })

    window.addEventListener('resize', () => {
      winHeight = window.innerHeight
      setViewportRows()
      rows.forEach((row) => showCircle(row, false))
      rows
        .filter((row) => !rowsOutViewport.includes(row))
        .forEach((row) => {
          const left = row.querySelector('div.ss-left')
          const right = row.querySelector('div.ss-right')
          if (left) left.style.left = '0%'
          if (right) right.style.right = '0%'
          showCircle(row, true)
        })
    })

    window.addEventListener('scroll', () => {
      if (scrollScheduled) return
      scrollScheduled = true
      window.requestAnimationFrame(() => {
        placeRows()
        scrollScheduled = false
      })
    })

    if (perspective) {
      rows.forEach((row) => {
        const el = row
        el.style.perspective = '600px'
        el.style.perspectiveOrigin = '50% 0%'
      })
    }
    setViewportRows()
    rows
      .filter((row) => !rowsOutViewport.includes(row))
      .forEach((row) => showCircle(row, true))
    placeRows()
  })
})()
