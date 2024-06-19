$(document).ready(() => {
  // on rend les listes deplacables
  const sortables = $('.list-sortables')
  if (sortables.length > 0) {
    sortables.sortable({
      handle: '.handle-listitems',
      update() {
        $("#bazar_form_lists .list-sortables input[name^='label']").each(function(i) {
          $(this).attr('name', `label[${i + 1}]`)
            .prev().attr('name', `id[${i + 1}]`)
            .parent('.liste_ligne')
            .attr('id', `row${i + 1}`)
            .find('input:hidden')
            .attr('name', `ancienlabel[${i + 1}]`)
        })
      }
    })
  }

  const newitem = $('#empty-new-item').html()

  // pour la gestion des listes, on peut rajouter dynamiquement des champs
  $('.ajout_label_liste').on('click', () => {
    const nb = $("#bazar_form_lists .list-sortables input[name^='label']").length + 1
    const nextnewitem = newitem.replace(/@nb@/gi, nb)
    $('#bazar_form_lists .list-sortables').append(nextnewitem)
    $(`#bazar_form_lists input[name='id[${nb}]']`).focus()
    return false
  })

  // on supprime un champs pour une liste
  $('#bazar_form_lists ul.list-sortables').on('click', '.suppression_label_liste', function() {
    const id = `#${$(this).parent('.liste_ligne').attr('id')}`
    const nb = $("#bazar_form_lists .list-sortables input[name^='label']").length
    if (nb > 1) {
      if (confirm(_t('BAZ_EDIT_LISTS_CONFIRM_DELETE'))) {
        const nom = `a_effacer_${$(id).find('input:hidden').attr('name')}`
        $(id).find('input:hidden').attr('name', nom).appendTo('#bazar_form_lists')
        $(id).remove()
        $("#bazar_form_lists .list-sortables input[name^='label']").each(function(i) {
          $(this).attr('name', `label[${i + 1}]`)
            .prev().attr('name', `id[${i + 1}]`)
            .parent('.liste_ligne')
            .attr('id', `row${i + 1}`)
            .find('input:hidden')
            .attr('name', `ancienlabel[${i + 1}]`)
        })
      }
    } else {
      alert(_t('BAZ_EDIT_LISTS_DELETE_ERROR'))
    }

    return false
  })

  // initialise le validateur
  $('.btn-save-list').click(() => {

  })

  // import de listes à partir d'un yeswiki
  const btnimportlist = $('#btn-import-lists')
  const resultimportlist = $('#import-lists-result')
  const resultimporttable = $('#import-lists-table')
  const resultimportform = $('#import-lists-form')
  const listtranslations = $('#list-translations').data()
  const existinglists = $('#existing-lists-table')
  btnimportlist.click(() => {
    // on enleve les anciens contenus
    resultimportlist.html('')
    resultimportform.addClass('hide')
    resultimporttable.find('tbody').html('')

    // url saisie
    let url = $('#url-import-lists').val()

    // expression réguliere pour trouver une url valide
    const rgHttpUrl = /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-\u00C0-\u017F]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?(\#([a-zA-Z0-9$\-_.+!*'(),;:@&=\/?]|%[0-9a-fA-F]{2})*)?)?$/

    if (rgHttpUrl.test(url)) {
      // on formate l url pour acceder au service json de yeswiki
      const taburl = url.split('wakka.php')
      url = `${taburl[0].replace(/\/+$/g, '')}/wakka.php?wiki=BazaR/json&demand=lists`
      resultimportlist.html(`<div class="alert alert-info">
        <span class="throbber">${listtranslations.loading}...</span> 
        ${listtranslations.recuperation} ${url}
      </div>`)

      $.ajax({
        method: 'GET',
        url
      }).done((data) => {
        resultimportlist.html('')
        let count = 0

        Object.entries(data).forEach(([idlist, listData]) => {
          count += 1
          let list = {}

          // Convert old data structure
          if (listData.titre_liste) {
            list = {
              title: listData.titre_liste,
              nodes: []
            }
            Object.entries(listData.label).forEach(([id, label]) => {
              list.nodes.push({ id, label, children: [] })
            })
          } else {
            list = listData
          }

          let select = `<option>${listtranslations.choose}</option>`
          list.nodes.forEach((node) => {
            select += `<option>${node.label}</option>`
          })

          let trclass = ''
          let existingmessage = ''
          if (existinglists.find('td').filter(function() {
            return $(this).text() === idlist
          }).length > 0) {
            trclass = ' class="error danger"'
            existingmessage = `<br>
                <span class="text-danger">${listtranslations.existingmessage}</span>`
          }

          resultimporttable.find('tbody').append(
            `<tr${trclass}>
                <td>
                  <label>
                    <input type="checkbox" name="imported-list[${idlist}]" 
                           value="${JSON.stringify(list).replace(/"/g, '&quot;')}">
                    <span></span>
                  </label>
                </td>
                <td>${idlist + existingmessage}</td>
                <td>${list.title}</td>
                <td><select class="form-control">${select}</select></td>
              </tr>`
          )
        })

        resultimportform.removeClass('hide')
        resultimportlist.prepend(`<div class="alert alert-success">
          ${listtranslations.nblistsfound} : ${count}
        </div>`)
      }).fail(() => {
        resultimportlist.html(`<div class="alert alert-danger">${listtranslations.noanswers}.</div>`)
      })
    } else {
      resultimportlist.html(`<div class="alert alert-danger">
        ${listtranslations.notvalidurl} : ${url}
      </div>`)
    }
  })
})
