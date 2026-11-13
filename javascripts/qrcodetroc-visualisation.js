// The {{qrcodetroc}} visualisation: one ball per account, a line per relation between two of them, drawn on a canvas with no library.
;(() => {
  const container = document.getElementById('canvas-qrcodetroc')
  if (!container) return

  const MIN_SIZE = 20
  const MAX_SIZE = 35
  const relationType = container.dataset.relation
  const formUser = container.dataset.formuser
  const refresh = Number(container.dataset.refresh) || 10000

  const canvas = document.createElement('canvas')
  container.appendChild(canvas)
  const context = canvas.getContext('2d')

  const balls = {}
  let relations = {}
  let userCount = 0
  let relationCount = 0
  let width = 0
  let height = 0
  const pointer = { x: -1, y: -1, pressed: false, dragging: false }

  const random = (min, max) => min + Math.random() * (max - min)

  // Sizes the canvas to the window, sharp on high-density screens.
  const resize = () => {
    const density = window.devicePixelRatio || 1
    width = window.innerWidth
    height = window.innerHeight
    canvas.width = width * density
    canvas.height = height * density
    canvas.style.width = `${width}px`
    canvas.style.height = `${height}px`
    context.setTransform(density, 0, 0, density, 0, 0)
  }

  // A ball wandering slowly, bouncing off the edges, carrying an account's title.
  const makeBall = (title) => {
    const angle = random(0, Math.PI * 2)
    const r = random(MIN_SIZE, MAX_SIZE)

    return {
      x: random(0, width),
      y: random(0, height),
      vx: Math.cos(angle) * 0.3,
      vy: Math.sin(angle) * 0.3,
      r,
      color: `rgba(${random(0, 255)}, ${random(0, 255)}, ${random(0, 255)}, ${70 / 255})`,
      title,
    }
  }

  // Moves a ball one step, then keeps it inside the canvas.
  const move = (ball) => {
    ball.x += ball.vx
    ball.y += ball.vy
    if (ball.x > width - ball.r) {
      ball.x = width - ball.r
      ball.vx *= -1
    } else if (ball.x < ball.r) {
      ball.x = ball.r
      ball.vx *= -1
    } else if (ball.y > height - ball.r) {
      ball.y = height - ball.r
      ball.vy *= -1
    } else if (ball.y < ball.r) {
      ball.y = ball.r
      ball.vy *= -1
    }
  }

  // Draws a ball, its title when the pointer is over it, and follows the pointer while it drags.
  const draw = (ball) => {
    context.fillStyle = ball.color
    context.beginPath()
    context.arc(ball.x, ball.y, ball.r, 0, Math.PI * 2)
    context.fill()

    context.fillStyle = 'rgb(20, 20, 20)'
    context.fillRect(ball.x - 3, ball.y - 3, 6, 6)

    if (Math.hypot(ball.x - pointer.x, ball.y - pointer.y) < ball.r) {
      context.textAlign = 'center'
      context.fillStyle = 'rgb(255, 255, 255)'
      context.fillText(ball.title, ball.x, ball.y - 10)
      if (pointer.dragging) {
        ball.x = pointer.x
        ball.y = pointer.y
      }
    }
  }

  // Asks the wiki for the relations and the accounts, adding a ball for each new account.
  const fetchRelations = () => {
    fetch(`?api/relations/${relationType}`)
      .then((response) => response.json())
      .then((data) => {
        relations = data && typeof data === 'object' ? data : {}
        relationCount = Object.keys(relations).length
      })
      .catch(() => {})
    fetch(`?api/forms/${formUser}/entries&fields=title`)
      .then((response) => response.json())
      .then((users) => {
        userCount = Object.keys(users).length
        Object.keys(users).forEach((key) => {
          if (!(key in balls)) balls[key] = makeBall(users[key].title)
        })
      })
      .catch(() => {})
  }

  const plural = (count, one, many) => `${count} ${count > 1 ? many : one}`

  // Draws one frame: the balls, the counts, then a line for each relation between two known accounts.
  const frame = () => {
    context.fillStyle = 'rgb(20, 20, 20)'
    context.fillRect(0, 0, width, height)
    context.font = '12px sans-serif'
    context.textBaseline = 'alphabetic'

    Object.values(balls).forEach((ball) => {
      move(ball)
      draw(ball)
    })

    context.textAlign = 'left'
    context.fillStyle = 'rgb(255, 255, 255)'
    context.fillText(
      `${plural(userCount, 'utilisateur·ice', 'utilisateur·ices')}  |  ${plural(relationCount, 'lien', 'liens')}`,
      20,
      20,
    )

    context.strokeStyle = 'rgb(126, 126, 126)'
    context.lineWidth = 1
    Object.values(relations).forEach((relation) => {
      const from = balls[relation?.bf_fiche1]
      const to = balls[relation?.bf_fiche2]
      if (!from || !to) return
      context.beginPath()
      context.moveTo(from.x, from.y)
      context.lineTo(to.x, to.y)
      context.stroke()
    })

    window.requestAnimationFrame(frame)
  }

  const follow = (event) => {
    const box = canvas.getBoundingClientRect()
    pointer.x = event.clientX - box.left
    pointer.y = event.clientY - box.top
  }

  canvas.addEventListener('pointermove', (event) => {
    follow(event)
    if (pointer.pressed) pointer.dragging = true
  })
  canvas.addEventListener('pointerdown', (event) => {
    follow(event)
    pointer.pressed = true
    canvas.setPointerCapture(event.pointerId)
  })
  const release = () => {
    pointer.pressed = false
    pointer.dragging = false
  }
  canvas.addEventListener('pointerup', release)
  canvas.addEventListener('pointercancel', release)
  window.addEventListener('resize', resize)

  resize()
  fetchRelations()
  window.setInterval(fetchRelations, refresh)
  window.requestAnimationFrame(frame)
})()
