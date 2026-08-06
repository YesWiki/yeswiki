const fps = 20
const scannerSize = 200

// This method will trigger user permissions
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
// we prefer back camera for scanning from mobile phone
html5QrCode
  .start({ facingMode: 'environment' }, config, qrCodeSuccessCallback, () => {
    // parse error, ignore it.
  })
  .catch((err) => {
    // Start failed, handle it.
    console.error(err)
  })

// do speech synthesis of the text inside the selector
function speak(selector) {
  // cut former speech
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

// handler of the qrcode data when successfully read
function successHandler(data) {
  const url = new URL(data)
  const isReadableString = typeof data === 'string' || data instanceof String
  if (isReadableString && data !== 'undefined' && isValidHttpUrl(data)) {
    fetch(url.href, { headers: { Accept: 'application/json' } })
      .then((response) => response.json())
      .then((cardData) => {
        console.log(cardData)
        // resource card with linked media file
        if (cardData.listeListeTypeCarte === '③' && cardData.fichierbf_file) {
          const song = `${url.origin}/files/${cardData.fichierbf_file}`
          console.log(song) // TODO test if url exists
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

// ticket 14: one initialiser convention -- see ywInit in yeswiki-base-no-defer.js
// ticket 16: keyed on the element it sets up, not on <body>. A boosted navigation swaps
// the body's *contents*, so a body-keyed initialiser runs once per session and every
// later page is left uninitialised.
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

    // first load
    speak('#qrinfos .yw-alert')

    // clic on playlist item
    // NB: pre-existing debug leftover carried over unchanged from the source extension --
    // out of scope for a Bootstrap/jQuery-removal ticket to also fix.
    document
      .getElementById('multimedia-playlist')
      .addEventListener('click', (e) => {
        if (e.target.closest('.song')) {
          alert('coucou')
        }
      })
  }
})
