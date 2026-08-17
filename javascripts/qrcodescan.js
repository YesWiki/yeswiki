function parseVcard(input) {
  const Re1 = /^(version|fn|title|org|url|adr):(.+)$/i
  const Re2 = /^([^:;]+);([^:]+):(.+)$/
  const ReKey = /item\d{1,2}\./
  const fields = {}

  input.split(/\r\n|\r|\n/).forEach((line) => {
    let results
    let key

    if (Re1.test(line)) {
      results = line.match(Re1)
      const [, capturedKey, capturedValue] = results
      key = capturedKey.toLowerCase()
      fields[key] = capturedValue
    } else if (Re2.test(line)) {
      results = line.match(Re2)
      const [, capturedKey, , capturedValue] = results
      key = capturedKey.replace(ReKey, '').toLowerCase()

      const meta = {}
      capturedValue
        .split(';')
        .map((p, i) => {
          const match = p.match(/([a-z]+)=(.*)/i)
          if (match) {
            return [match[1], match[2]]
          }
          return [`TYPE${i === 0 ? '' : i}`, p]
        })
        .forEach(([metaKey, metaValue]) => {
          meta[metaKey] = metaValue
        })

      if (!fields[key]) fields[key] = []

      fields[key].push({
        meta,
        value: results[3].split(';'),
      })
    }
  })
  return fields
}

function speak(selector) {
  window.speechSynthesis.cancel()
  const toSpeak = new SpeechSynthesisUtterance(
    document.querySelector(selector).textContent,
  )
  window.speechSynthesis.speak(toSpeak)
}

function showNotif(txt, alertClass) {
  const alert = document.querySelector('#qrinfos .yw-alert')
  alert.classList.remove(
    'yw-alert--danger',
    'yw-alert--info',
    'yw-alert--success',
  )
  alert.classList.add(alertClass)
  alert.innerHTML = txt
}

function reset() {
  step = 1
  lastResult = 0
  document
    .querySelector('#qr-container .step1')
    .classList.remove('stepper__row--disabled')
  document
    .querySelector('#qr-container .step1')
    .classList.add('stepper__row--active')
  document
    .querySelector('#qr-container .step2')
    .classList.remove('stepper__row--active')
  document
    .querySelector('#qr-container .step2')
    .classList.add('stepper__row--disabled')
  document
    .querySelector('#qr-container .step3')
    .classList.remove('stepper__row--active')
  document
    .querySelector('#qr-container .step3')
    .classList.add('stepper__row--disabled')

  document.querySelectorAll('#qr-container .paragraph').forEach((el) => {
    Object.assign(el.style, { display: '' })
  })
  document.querySelector('#qr-container .text-success').innerHTML = ''
  showNotif(
    document.querySelector('#qr-container .step1 .paragraph').textContent,
    'yw-alert--info',
  )
  return false
}

document.querySelector('.btn-reset').addEventListener('click', reset)

function stepHandler(currentStep, entry) {
  let step = currentStep
  if (step === 1) {
    firstpeople = entry
    step = 2
    document.querySelector('.step1').classList.remove('stepper__row--active')
    document.querySelector('.step1').classList.add('stepper__row--disabled')

    document.querySelector('.step2').classList.remove('stepper__row--disabled')
    document.querySelector('.step2').classList.add('stepper__row--active')

    document.querySelector('.step1 .paragraph').style.display = 'none'
    document.querySelector('.step1 .text-success').innerHTML =
      `Premier participant : ${entry.title}`
    showNotif(
      `Vous avez été reconnu comme étant ${entry.title}.` +
        ' Merci de passer un deuxième Q.R. Code pour faire le lien.',
      'yw-alert--success',
    )
  } else if (step === 2) {
    if (firstpeople.fn === entry.title) {
      showNotif(
        'Le premier Q.R. Code et le second sont les mêmes,' +
          ' veuillez utiliser un deuxième Q.R. Code différent.',
        'yw-alert--danger',
      )
    } else {
      secondpeople = entry
      step = 3

      document.querySelector('.step2').classList.remove('stepper__row--active')
      document.querySelector('.step2').classList.add('stepper__row--disabled')

      document
        .querySelector('.step3')
        .classList.remove('stepper__row--disabled')
      document.querySelector('.step3').classList.add('stepper__row--active')

      document.querySelector('.step2 .paragraph').style.display = 'none'
      const secondParticipantMsg = `Second participant : ${entry.title}`
      document.querySelector('.step2 .text-success').innerHTML =
        secondParticipantMsg

      document.querySelector('.step3 .paragraph').style.display = 'none'
      document.querySelector('.step3 .text-success').innerHTML =
        `Bravo ${firstpeople.title}` +
        ` et ${secondpeople.title}, vous êtes maintenant reliés !`

      showNotif(
        `Bravo ${firstpeople.title} et ${secondpeople.title}!! ` +
          'Vous êtes unis par les liens sacrés du Q.R. code. Un email de contact vous a été envoyé.',
        'yw-alert--success',
      )

      setTimeout(reset, 10000)

      if (firstpeople.title.toLowerCase() > secondpeople.title.toLowerCase()) {
        const temp = secondpeople
        secondpeople = firstpeople
        firstpeople = temp
      }
      const [, url1Query] = firstpeople.url.split('?')
      const [, url2Query] = secondpeople.url.split('?')
      const params = new URLSearchParams()
      params.set(
        'bf_titre',
        'Relation "{{bf_relation}}" entre {{bf_fiche1}} et {{bf_fiche2}}',
      )
      params.set('bf_relation', qrinfos.dataset.relation)
      params.set('bf_fiche1', url1Query)
      params.set('bf_fiche2', url2Query)
      params.set('form_id', '1300')
      fetch('?api/relations', { method: 'POST', body: params })

      let message1 = 'Les informations de votre contact:\n'
      message1 = `${message1}${firstpeople.title}\n`
      message1 = `${message1}Email : ${firstpeople.bf_mail}\n`
      if (firstpeople.org) {
        message1 = `${message1}Organisation : ${firstpeople.org}\n`
      }
      if (firstpeople.url) {
        message1 = `${message1}Fiche complète : ${firstpeople.url}\n`
      }

      fetch(wiki.url('api/contact/mail'), {
        method: 'POST',
        body: new URLSearchParams({
          pageTag: 'ContacT',
          name: firstpeople.title,
          email: firstpeople.bf_mail,
          subject: 'QRcode contact',
          message: message1,
          mail: secondpeople.bf_mail,
          subjectprefix: 'Co-construire 2023',
          type: 'contact',
        }),
      })

      let message2 = 'Les informations de votre contact:\n'
      message2 = `${message2}${secondpeople.title}\n`
      message2 = `${message2}Email : ${secondpeople.bf_mail}\n`
      if (secondpeople.org) {
        message2 = `${message2}Organisation : ${secondpeople.org}\n`
      }
      if (secondpeople.url) {
        message2 = `${message2}Fiche complète : ${secondpeople.url}\n`
      }
      fetch(wiki.url('api/contact/mail'), {
        method: 'POST',
        body: new URLSearchParams({
          pageTag: 'ContacT',
          name: secondpeople.title,
          email: secondpeople.bf_mail,
          subject: 'QRcode contact',
          message: message2,
          mail: firstpeople.bf_mail,
          subjectprefix: 'Co-construire 2023',
          type: 'contact',
        }),
      })
    }
  }
  return step
}

function successHandler(data) {
  if (
    (typeof data === 'string' || data instanceof String) &&
    data !== 'undefined'
  ) {
    const vcard = parseVcard(data)
    if (vcard.fn && vcard.email && vcard.url) {
      const [firstEmail] = vcard.email
      const [firstEmailValue] = firstEmail.value
      vcard.title = vcard.fn
      vcard.bf_mail = firstEmailValue
      vcard.bf_structure = vcard.org
      step = stepHandler(step, vcard)
    } else {
      const entry = data.split('personne=')
      if (entry[1]) {
        fetch(`?${entry[1]}/raw`)
          .then((response) => response.text())
          .then((response) => {
            const json = JSON.parse(response)
            json.url = `${data.split('/?')[0]}/?${json.tag}`
            step = stepHandler(step, json)
            console.log(json)
          })
      }
    }
  }
}

// global vars for the scanner
let step = 1
let firstpeople
let secondpeople
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
  const entity = JSON.parse(qrinfos.dataset.entity)
  if (entity && Object.keys(entity).length > 0) {
    firstpeople = entity
    step = 2
    document.querySelector('.step1').classList.remove('stepper__row--active')
    document.querySelector('.step1').classList.add('stepper__row--disabled')

    document.querySelector('.step2').classList.remove('stepper__row--disabled')
    document.querySelector('.step2').classList.add('stepper__row--active')

    document.querySelector('.step1 .paragraph').style.display = 'none'
    const firstParticipantMsg = `Premier participant : ${entity.title}`
    document.querySelector('.step1 .text-success').innerHTML =
      firstParticipantMsg
    showNotif(
      `Vous avez été reconnu comme étant ${entity.title}.` +
        ' Merci de passer un deuxième Q.R. Code pour faire le lien.',
      'yw-alert--success',
    )
  }
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
