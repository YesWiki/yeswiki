// forms-import.js — import bazar forms from a remote YesWiki (ticket 16: vanilla
// JS; the result table is a plain table now — its rows carry the checkboxes the
// import form submits, so no search/pagination that would detach them)
document.addEventListener('DOMContentLoaded', () => {
  const btnImportForm = document.getElementById('btn-import-forms')
  const resultForms = document.getElementById('import-forms-result')
  const resultImportTable = document.getElementById('import-forms-table')
  const resultImportForm = document.getElementById('import-forms-form')
  const translationsHolder = document.getElementById('form-translations')
  const existingForms = document.getElementById('existing-forms-table')
  if (!btnImportForm || !resultForms || !resultImportTable || !resultImportForm) return
  const formTranslations = translationsHolder ? { ...translationsHolder.dataset } : {}

  function alertBlock(kind, text) {
    const alert = document.createElement('div')
    alert.className = `yw-alert yw-alert--${kind}`
    alert.textContent = text
    return alert
  }

  btnImportForm.addEventListener('click', (e) => {
    e.preventDefault()
    // on enleve les anciens contenus
    resultForms.replaceChildren()
    resultImportForm.classList.add('hide')
    const tbody = resultImportTable.tBodies[0]
    tbody.replaceChildren()

    // url saisie
    let url = document.getElementById('url-import-forms').value

    // expression réguliere pour trouver une url valide
    // eslint-disable-next-line max-len
    const rgHttpUrl = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-À-ſ]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=/?]|%[0-9a-fA-F]{2})*)?(#([a-zA-Z0-9$\-_.+!*'(),;:@&=/?]|%[0-9a-fA-F]{2})*)?)?$/

    if (!rgHttpUrl.test(url)) {
      resultForms.replaceChildren(
        alertBlock('danger', `${formTranslations.notvalidurl} : ${url}`)
      )
      return
    }
    // on formate l url pour acceder au service json de yeswiki
    const taburl = url.search('wakka.php') > -1 ? url.split('wakka.php') : url.split('?')
    url = `${taburl[0].replace(/\/+$/g, '')}/?wiki=BazaR/json&demand=forms`
    const loading = alertBlock('info', ` ${formTranslations.recuperation} ${url}`)
    const throbber = document.createElement('span')
    throbber.className = 'throbber'
    throbber.textContent = `${formTranslations.loading}...`
    loading.prepend(throbber)
    resultForms.replaceChildren(loading)

    const existingCells = existingForms ? Array.from(existingForms.querySelectorAll('td')) : []

    fetch(url)
      .then((response) => (response.ok ? response.json() : Promise.reject(response)))
      .then((data) => {
        resultForms.replaceChildren()
        let count = 0
        Object.keys(data).forEach((idform) => {
          count += 1
          let trclass = ''
          let existingmessage = ''
          const sameLabel = existingCells.some((cell) => {
            const strong = cell.querySelector('strong')
            return strong && strong.textContent === data[idform].bn_label_nature
          })
          if (sameLabel) {
            trclass = ' class="error danger"'
            existingmessage = '<br><span class="text-danger">'
              + `${formTranslations.existingmessagereplace}</span>`
          } else if (existingCells.some((cell) => cell.textContent.trim() === idform)) {
            trclass = ' class="warning"'
            existingmessage = '<br><span class="text-warning">'
              + `${formTranslations.existingmessage}</span>`
          }

          const escapedValue = JSON.stringify(data[idform]).replace(/"/g, '&quot;')
          let tablerow = `<tr${trclass}><td><label>`
            + `<input type="checkbox" name="imported-form[${data[idform].bn_id_nature}]"`
            + ` value="${escapedValue}"><span></span></label></td>`
            + `<td><strong>${data[idform].bn_label_nature}</strong>`
          if (data[idform].bn_description && data[idform].bn_description.length !== 0) {
            tablerow += `<br>${data[idform].bn_description}`
          }
          tablerow += `${existingmessage}</td><td>${data[idform].bn_id_nature}</td></tr>`
          tbody.insertAdjacentHTML('beforeend', tablerow)
        })

        resultImportForm.classList.remove('hide')
        resultForms.prepend(
          alertBlock('success', `${formTranslations.nbformsfound} : ${count}`)
        )
      })
      .catch(() => {
        resultForms.replaceChildren(alertBlock('danger', `${formTranslations.noanswers}.`))
      })
  })
})
