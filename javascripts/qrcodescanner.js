function speak(selector) {
  window.speechSynthesis.cancel()
  const toSpeak = new SpeechSynthesisUtterance(
    document.querySelector(selector).textContent,
  )
  window.speechSynthesis.speak(toSpeak)
}

function reset() {
  lastResult = 0
  document.getElementById('multimedia-player').innerHTML = ''
  return false
}

document.querySelector('.btn-reset').addEventListener('click', reset)

function isValidHttpUrl(string) {
  let url

  try {
    url = new URL(string)
  } catch {
    return false
  }

  return url.protocol === 'http:' || url.protocol === 'https:'
}

function successHandler(data) {
  const url = new URL(data)
  url.search = ''
  console.log(url.href)
  const isReadableString = typeof data === 'string' || data instanceof String
  if (isReadableString && data !== 'undefined' && isValidHttpUrl(data)) {
    fetch(`${data}/raw`)
      .then((response) => response.text())
      .then((response) => {
        const cardData = JSON.parse(response)
        console.log(cardData)
        const song = `${url.href}files/${cardData.fichierbf_file}`
        console.log(song)
        const player = document.getElementById('multimedia-player')
        const caption = `<figcaption><strong>En écoute : ${cardData.title}</strong></figcaption>`
        const audio = `<audio id="audio-player" controls autoplay src="${song}"></audio>`
        const download =
          `<a style="display:block" download href="${song}">` +
          '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#download"/></svg> Télécharger</a>'
        player.innerHTML = `<figure>${caption}${audio}${download}</figure>`
      })
  }
}

// global vars for the scanner
let lastResult

const qrinfos = document.getElementById('qrinfos')

const qrCodeFormats = {
  formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
}
const html5QrCode = new Html5Qrcode('qrreader', qrCodeFormats)
const config = { fps: 20, qrbox: 250 }
const qrCodeSuccessCallback = (decodedText) => {
  if (decodedText !== lastResult) {
    lastResult = decodedText
    successHandler(decodedText)
  }
}
html5QrCode
  .start({ facingMode: 'environment' }, config, qrCodeSuccessCallback, () => {})
  .catch((err) => {
    console.error(err)
  })

ywInitEach('#qrinfos', () => {
  if (qrinfos.dataset.speak === 'true') {
    function mutate() {
      speak('#qrinfos .yw-alert')
    }

    const target = document.querySelector('div#qrinfos .yw-alert')
    const observer = new MutationObserver(mutate)
    const observerConfig = {
      characterData: false,
      attributes: false,
      childList: true,
      subtree: false,
    }
    observer.observe(target, observerConfig)

    speak('#qrinfos .yw-alert')
  }
})
