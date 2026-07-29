// do speech synthesis of the text inside the selector
function speak(selector) {
  // cut former speech
  window.speechSynthesis.cancel()
  const toSpeak = new SpeechSynthesisUtterance(document.querySelector(selector).textContent)
  window.speechSynthesis.speak(toSpeak)
}

// reset the scanner to allow re-scanning and clear the current media player
// NB: this file used to carry a whole relation-linking/stepper subsystem copy-pasted from
// qrcodescan.js (reset/stepHandler/firstpeople/secondpeople/step), but qrscanner.twig's markup
// has no .step1/.step2/.step3/stepper elements at all -- that code was dead and would have
// thrown (querying non-existent elements) the moment the reset button was actually clicked.
// Removed; this scanner variant is about playing linked media, not linking two scanned entries.
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

// handler of the qrcode data when successfully read
function successHandler(data) {
  const url = new URL(data)
  url.search = ''
  console.log(url.href)
  // do something when code is read and is a string
  const isReadableString = typeof data === 'string' || data instanceof String
  if (isReadableString && data !== 'undefined' && isValidHttpUrl(data)) {
    fetch(`${data}/raw`)
      .then((response) => response.text())
      .then((response) => {
        const cardData = JSON.parse(response)
        console.log(cardData)
        const song = `${url.href}files/${cardData.fichierbf_file}`
        console.log(song) // TODO test if url exists
        const player = document.getElementById('multimedia-player')
        const caption = `<figcaption><strong>En écoute : ${cardData.bf_titre}</strong></figcaption>`
        const audio = `<audio id="audio-player" controls autoplay src="${song}"></audio>`
        const download = `<a style="display:block" download href="${song}">`
          + '<svg class="yw-icon" aria-hidden="true"><use href="src/assets/icons.svg#download"/></svg> Télécharger</a>'
        player.innerHTML = `<figure>${caption}${audio}${download}</figure>`
      })
  }
}

// global vars for the scanner
let lastResult

const qrinfos = document.getElementById('qrinfos')

// This method will trigger user permissions
const qrCodeFormats = { formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE] }
const html5QrCode = new Html5Qrcode(/* element id */ 'qrreader', qrCodeFormats)
const config = { fps: 20, qrbox: 250 }
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

document.addEventListener('DOMContentLoaded', () => {
  if (qrinfos.dataset.speak === 'true') {
    function mutate() {
      speak('#qrinfos .yw-alert')
    }

    const target = document.querySelector('div#qrinfos .yw-alert')
    const observer = new MutationObserver(mutate)
    const observerConfig = { characterData: false, attributes: false, childList: true, subtree: false }
    observer.observe(target, observerConfig)

    // first load
    speak('#qrinfos .yw-alert')
  }
})
