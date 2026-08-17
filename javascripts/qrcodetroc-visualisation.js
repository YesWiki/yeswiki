let nbUsers = []
let relations = []
let nbRelations = []
const minSize = 20
const maxSize = 35
let dragging = false
const balls = []
const canvas = document.getElementById('canvas-qrcodetroc')
const relationType = canvas.dataset.relation
const formUser = canvas.dataset.formuser

setInterval(getRelations, canvas.dataset.refresh)

window.addEventListener('resize', () => {
  resizeCanvas(window.innerWidth, window.innerHeight)
})

function getRelations() {
  fetch(`?api/relations/${relationType}`)
    .then((response) => response.json())
    .then((dataRelations) => {
      relations = dataRelations
      nbRelations = Object.keys(relations).length
    })
  fetch(`?api/forms/${formUser}/entries&fields=title`)
    .then((response) => response.json())
    .then((dataUsers) => {
      nbUsers = Object.keys(dataUsers).length
      Object.keys(dataUsers).forEach((key) => {
        if (!(key in balls)) {
          balls[key] = new Ball(
            random(window.innerWidth),
            random(window.innerHeight),
            random(minSize, maxSize),
            color(random(255), random(255), random(255), 70),
            dataUsers[key].title,
          )
        }
      })
    })
}

class Ball {
  constructor(x, y, r, color, title) {
    this.position = new p5.Vector(x, y)
    this.velocity = p5.Vector.random2D()
    this.velocity.mult(0.3)
    this.r = r
    this.m = r * 0.1
    this.color = color
    this.title = title
  }

  update() {
    this.position.add(this.velocity)
  }

  checkBoundaryCollision() {
    if (this.position.x > width - this.r) {
      this.position.x = width - this.r
      this.velocity.x *= -1
    } else if (this.position.x < this.r) {
      this.position.x = this.r
      this.velocity.x *= -1
    } else if (this.position.y > height - this.r) {
      this.position.y = height - this.r
      this.velocity.y *= -1
    } else if (this.position.y < this.r) {
      this.position.y = this.r
      this.velocity.y *= -1
    }
  }

  checkCollision(other) {
    const distanceVect = p5.Vector.sub(other.position, this.position)

    const distanceVectMag = distanceVect.mag()

    const minDistance = this.r + other.r

    if (distanceVectMag < minDistance) {
      const distanceCorrection = (minDistance - distanceVectMag) / 2.0
      const d = distanceVect.copy()
      const correctionVector = d.normalize().mult(distanceCorrection)
      other.position.add(correctionVector)
      this.position.sub(correctionVector)

      const theta = distanceVect.heading()
      const sine = sin(theta)
      const cosine = cos(theta)

      const bTemp = [new p5.Vector(), new p5.Vector()]

      bTemp[1].x = cosine * distanceVect.x + sine * distanceVect.y
      bTemp[1].y = cosine * distanceVect.y - sine * distanceVect.x

      const vTemp = [new p5.Vector(), new p5.Vector()]

      vTemp[0].x = cosine * this.velocity.x + sine * this.velocity.y
      vTemp[0].y = cosine * this.velocity.y - sine * this.velocity.x
      vTemp[1].x = cosine * other.velocity.x + sine * other.velocity.y
      vTemp[1].y = cosine * other.velocity.y - sine * other.velocity.x

      const vFinal = [new p5.Vector(), new p5.Vector()]

      vFinal[0].x =
        ((this.m - other.m) * vTemp[0].x + 2 * other.m * vTemp[1].x) /
        (this.m + other.m)
      vFinal[0].y = vTemp[0].y

      vFinal[1].x =
        ((other.m - this.m) * vTemp[1].x + 2 * this.m * vTemp[0].x) /
        (this.m + other.m)
      vFinal[1].y = vTemp[1].y

      bTemp[0].x += vFinal[0].x
      bTemp[1].x += vFinal[1].x

      const bFinal = [new p5.Vector(), new p5.Vector()]

      bFinal[0].x = cosine * bTemp[0].x - sine * bTemp[0].y
      bFinal[0].y = cosine * bTemp[0].y + sine * bTemp[0].x
      bFinal[1].x = cosine * bTemp[1].x - sine * bTemp[1].y
      bFinal[1].y = cosine * bTemp[1].y + sine * bTemp[1].x

      Object.assign(other.position, {
        x: this.position.x + bFinal[1].x,
        y: this.position.y + bFinal[1].y,
      })

      this.position.add(bFinal[0])

      this.velocity.x = cosine * vFinal[0].x - sine * vFinal[0].y
      this.velocity.y = cosine * vFinal[0].y + sine * vFinal[0].x
      Object.assign(other.velocity, {
        x: cosine * vFinal[1].x - sine * vFinal[1].y,
        y: cosine * vFinal[1].y + sine * vFinal[1].x,
      })
    }
  }

  display() {
    noStroke()
    fill(this.color)
    ellipse(this.position.x, this.position.y, this.r * 2, this.r * 2)

    fill(20)
    rect(this.position.x - 3, this.position.y - 3, 6, 6)

    if (dist(this.position.x, this.position.y, mouseX, mouseY) < this.r) {
      textAlign(CENTER)
      fill(255)
      text(this.title, this.position.x, this.position.y - 10)

      if (dragging) {
        this.position.x = mouseX
        this.position.y = mouseY
      }
    }
  }
}

// eslint-disable-next-line no-unused-vars
function mouseDragged() {
  dragging = true
}

// eslint-disable-next-line no-unused-vars
function mouseReleased() {
  dragging = false
}

// eslint-disable-next-line no-unused-vars
function setup() {
  createCanvas(window.innerWidth, window.innerHeight)
  getRelations()
}

// eslint-disable-next-line no-unused-vars
function draw() {
  background(20)
  Object.keys(balls).forEach((key) => {
    const b = balls[key]
    b.update()
    b.display()
    b.checkBoundaryCollision()
  })

  noStroke()
  fill(255)
  textAlign(LEFT)
  let txtuser = ''
  if (nbUsers > 1) {
    txtuser = `${nbUsers} utilisateur·ices`
  } else {
    txtuser = `${nbUsers} utilisateur·ice`
  }
  let txtlinks = ''
  if (nbRelations > 1) {
    txtlinks = `${nbRelations} liens`
  } else {
    txtlinks = `${nbRelations} lien`
  }
  text(`${txtuser}  |  ${txtlinks}`, 20, 20)

  Object.keys(relations).forEach((key) => {
    noFill()
    stroke(126)
    if (
      key in relations &&
      'bf_fiche1' in relations[key] &&
      'bf_fiche2' in relations[key] &&
      relations[key].bf_fiche1 in balls &&
      relations[key].bf_fiche2 in balls &&
      balls[relations[key].bf_fiche1].position.x &&
      balls[relations[key].bf_fiche1].position.y &&
      balls[relations[key].bf_fiche2].position.x &&
      balls[relations[key].bf_fiche2].position.y
    ) {
      line(
        balls[relations[key].bf_fiche1].position.x,
        balls[relations[key].bf_fiche1].position.y,
        balls[relations[key].bf_fiche2].position.x,
        balls[relations[key].bf_fiche2].position.y,
      )
    }
  })
}
