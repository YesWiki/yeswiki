const fps = 20
const scannerSize = 200

const qrCodeFormats = {
  formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE],
}
const html5QrCode = new Html5Qrcode('qrreader', qrCodeFormats)
const config = { fps, qrbox: scannerSize }
let lastResult = ''

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

function speak(selector) {
  window.speechSynthesis.cancel()
  const toSpeak = new SpeechSynthesisUtterance(
    document.querySelector(selector).textContent,
  )
  window.speechSynthesis.speak(toSpeak)
}

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
  const isReadableString = typeof data === 'string' || data instanceof String
  if (isReadableString && data !== 'undefined' && isValidHttpUrl(data)) {
    fetch(url.href, { headers: { Accept: 'application/json' } })
      .then((response) => response.json())
      .then((cardData) => {
        console.log(cardData)
        if (cardData.listeListeTypeCarte === '③' && cardData.fichierbf_file) {
          const song = `${url.origin}/files/${cardData.fichierbf_file}`
          console.log(song)
          const player = document.getElementById('multimedia-player')
          const caption = `<figcaption><strong>En écoute : ${cardData.title}</strong></figcaption>`
          const audio = `<audio id="audio-player" controls autoplay autobuffer src="${song}"></audio>`
          const download =
            `<a style="display:block" download href="${song}">` +
            '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#download"/></svg> Télécharger</a>'
          player.innerHTML = `<figure>${caption}${audio}${download}</figure>`
          const activeSelector =
            '#multimedia-playlist .yw-list-group__item--active'
          document.querySelectorAll(activeSelector).forEach((el) => {
            el.classList.remove('yw-list-group__item--active')
          })
          const songButton =
            '<button type="button"' +
            ' class="song yw-list-group__item yw-list-group__item--active"' +
            ` data-url="${song}">` +
            `<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#music"/></svg> ${cardData.title}` +
            '</button>'
          document
            .getElementById('multimedia-playlist')
            .insertAdjacentHTML('beforeend', songButton)
        }
      })
  }
}

ywInitEach('#qrinfos', () => {
  const qrinfos = document.getElementById('qrinfos')
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

    document
      .getElementById('multimedia-playlist')
      .addEventListener('click', (e) => {
        if (e.target.closest('.song')) {
          alert('coucou')
        }
      })
  }
})
