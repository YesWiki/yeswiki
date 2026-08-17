ywInitEach('#btn-import-lists', () => {
  const btnimportlist = document.getElementById('btn-import-lists')
  const resultimportlist = document.getElementById('import-lists-result')
  const resultimporttable = document.getElementById('import-lists-table')
  const resultimportform = document.getElementById('import-lists-form')
  const listtranslations = document.getElementById('list-translations').dataset
  const existinglists = document.getElementById('existing-lists-table')

  if (!btnimportlist) {
    return
  }

  btnimportlist.addEventListener('click', () => {
    resultimportlist.innerHTML = ''
    resultimportform.classList.add('hide')
    resultimporttable.querySelector('tbody').innerHTML = ''

    let url = document.getElementById('url-import-lists').value

    const rgHttpUrl =
      /^(http|https):\/\/(([a-zA-Z0-9$\-_.+!*'(),;:&=]|%[0-9a-fA-F]{2})+@)?(((25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])(\.(25[0-5]|2[0-4][0-9]|[0-1][0-9][0-9]|[1-9][0-9]|[0-9])){3})|localhost|([a-zA-Z0-9\-À-ſ]+\.)+([a-zA-Z]{2,}))(:[0-9]+)?(\/(([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*(\/([a-zA-Z0-9$\-_.+!*'(),;:@&=]|%[0-9a-fA-F]{2})*)*)?(\?([a-zA-Z0-9$\-_.+!*'(),;:@&=/?]|%[0-9a-fA-F]{2})*)?(#([a-zA-Z0-9$\-_.+!*'(),;:@&=/?]|%[0-9a-fA-F]{2})*)?)?$/

    if (rgHttpUrl.test(url)) {
      const taburl = url.split('wakka.php')
      url = `${taburl[0].replace(/\/+$/g, '')}/?wiki=BazaR/json&demand=lists`
      resultimportlist.innerHTML = `<div class="yw-alert yw-alert--info">
        <span class="throbber">${listtranslations.loading}...</span>
        ${listtranslations.recuperation} ${url}
      </div>`

      fetch(url)
        .then((response) => {
          if (!response.ok) throw new Error(`HTTP ${response.status}`)
          return response.json()
        })
        .then((data) => {
          resultimportlist.innerHTML = ''
          let count = 0

          Object.entries(data).forEach(([idlist, listData]) => {
            count += 1
            let list = {}

            if (listData.titre_liste) {
              list = {
                title: listData.titre_liste,
                nodes: [],
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
            const existingMatch = Array.from(
              existinglists.querySelectorAll('td'),
            ).some((td) => td.textContent === idlist)
            if (existingMatch) {
              trclass = ' class="error danger"'
              existingmessage = `<br>
                  <span class="text-danger">${listtranslations.existingmessage}</span>`
            }

            resultimporttable.querySelector('tbody').insertAdjacentHTML(
              'beforeend',
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
                  <td><select class="yw-input">${select}</select></td>
                </tr>`,
            )
          })

          resultimportform.classList.remove('hide')
          resultimportlist.insertAdjacentHTML(
            'afterbegin',
            `<div class="yw-alert yw-alert--success">
              ${listtranslations.nblistsfound} : ${count}
            </div>`,
          )
        })
        .catch(() => {
          const msg = `${listtranslations.noanswers}.`
          resultimportlist.innerHTML = `<div class="yw-alert yw-alert--danger">${msg}</div>`
        })
    } else {
      resultimportlist.innerHTML = ''
      const errorDiv = document.createElement('div')
      errorDiv.classList.add('yw-alert', 'yw-alert--danger')
      errorDiv.textContent = `${listtranslations.notvalidurl} : ${url}`
      resultimportlist.appendChild(errorDiv)
    }
  })
})
