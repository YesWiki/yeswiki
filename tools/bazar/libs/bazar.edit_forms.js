$(document).ready(() => {
  // import de formes à partir d'un yeswiki
  const btnimportform = $('#btn-import-forms')
  const resultforms = $('#import-forms-result')
  const resultimporttable = $('#import-forms-table')
  const resultimportform = $('#import-forms-form')
  const formtranslations = $('#form-translations').data()
  const existingforms = $('#existing-forms-table')
  btnimportform.click(() => {
    // on enleve les anciens contenus
    resultforms.html('')
    resultimportform.addClass('hide')
    resultimporttable.find('tbody').html('')

    // url saisie
    let url = $('#url-import-forms').val()

    // expression réguliere pour trouver une url valide
    const rgHttpUrl = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-\u00C0-\u017F]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?(\#([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?)?$/

    if (rgHttpUrl.test(url)) {
      // on formate l url pour acceder au service json de yeswiki
      if (url.search('wakka.php') > -1) {
        var taburl = url.split('wakka.php')
      } else {
        var taburl = url.split('?')
      }
      url = `${taburl[0].replace(/\/+$/g, '')}/wakka.php?wiki=BazaR/json&demand=forms`
      resultforms.html(`<div class="alert alert-info"><span class="throbber">${formtranslations.loading}...</span> ${formtranslations.recuperation} ${url}</div>`)
      $.ajax({
        method: 'GET',
        url
      }).done((data) => {
        resultforms.html('')
        let count = 0
        for (var idform in data) {
          if (data.hasOwnProperty(idform)) {
            count++
            let trclass = ''
            let existingmessage = ''
            if (existingforms.find('td').filter(function() {
              return $(this).find('strong').text() === data[idform].bn_label_nature
            }).length > 0) {
              trclass = ' class="error danger"'
              existingmessage = `<br><span class="text-danger">${formtranslations.existingmessagereplace}</span>`
            } else if (existingforms.find('td').filter(function() {
              return $(this).text() === idform
            }).length > 0) {
              trclass = ' class="warning"'
              existingmessage = `<br><span class="text-warning">${formtranslations.existingmessage}</span>`
            }

            let tablerow = `<tr${trclass}><td><label><input type="checkbox" name="imported-form[${data[idform].bn_id_nature}]" value="${JSON.stringify(data[idform]).replace(/"/g, '&quot;')}"><span></span></label></td><td><strong>${data[idform].bn_label_nature}</strong>`
            if (data[idform].bn_description && data[idform].bn_description.length !== 0) {
              tablerow += `<br>${data[idform].bn_description}`
            }

            tablerow += `${existingmessage}</td><td>${data[idform].bn_id_nature}</td></tr>`
            resultimporttable.find('tbody').append(tablerow)
          }
        }

        resultimportform.removeClass('hide')
        resultimporttable.DataTable(DATATABLE_OPTIONS)
        resultforms.prepend(`<div class="alert alert-success">${formtranslations.nbformsfound} : ${count}</div>`)
      }).fail((jqXHR, textStatus, errorThrown) => {
        resultforms.html(`<div class="alert alert-danger">${formtranslations.noanswers}.</div>`)
      })
    } else {
      resultforms.html(`<div class="alert alert-danger">${formtranslations.notvalidurl} : ${url}</div>`)
    }
  })
})
