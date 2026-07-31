const importBtn = document.getElementById('btn-bazar-imports')
const importForm = document.getElementById('form-bazar-import')
const progressContainer = document.getElementById('import-progress-container')
const importStatus = document.getElementById('import-status')
const progressCurrent = document.getElementById('progress-current')
const progressTotal = document.getElementById('progress-total')
const progressBar = document.getElementById('progress-bar')
const importLog = document.getElementById('import-log')
const formId = importForm.querySelector('[name="id"]').value
const CONCURRENCY_LIMIT = 5

function updateProgressUI(processed, successful, total) {
  progressCurrent.textContent = processed

  const percentage = total > 0 ? (processed / total) * 100 : 0
  progressBar.style.width = `${percentage}%`

  if (percentage > 10) {
    progressBar.textContent = `${Math.round(percentage)}%`
  } else {
    progressBar.textContent = ''
  }
}

async function processEntry(entryCheckbox, counters) {
  const currentEntry = JSON.parse(entryCheckbox.value)
  const entryIdentifier = currentEntry.title || currentEntry.bf_titre || `#${counters.processed + 1}`

  try {
    const response = await fetch(`?api/entries/${formId}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
      },
      body: entryCheckbox.value
    })

    if (!response.ok) {
      let errorMessage = `Server error (Status: ${response.status})`
      try {
        const errorData = await response.json()
        errorMessage = errorData.message || JSON.stringify(errorData)
      } catch (e) { /* Ignore */ }
      throw new Error(errorMessage)
    }

    const result = await response.json()
    counters.successful++
    const logItem = document.createElement('li')
    logItem.style.color = 'green'

    const link = document.createElement('a')
    link.href = result.success
    link.target = '_blank'
    link.rel = 'noopener noreferrer'
    link.textContent = `"${entryIdentifier}"`

    logItem.append('✅ Import de ', link, '.')
    importLog.appendChild(logItem)
  } catch (error) {
    const logItem = document.createElement('li')
    logItem.textContent = `❌ Erreur: "${entryIdentifier}". Raison: ${error.message}`
    logItem.style.color = 'red'
    importLog.appendChild(logItem)
  } finally {
    counters.processed++
    setTimeout(() => {
      importLog.scrollTo({
        top: importLog.scrollHeight,
        behavior: 'smooth'
      })
    }, 0)
    updateProgressUI(counters.processed, counters.successful, counters.total)
  }
}

importBtn.addEventListener('click', async(event) => {
  event.preventDefault()

  const toImport = Array.from(importForm.querySelectorAll('input[name^=importfiche]:checked'))
  const totalEntries = toImport.length

  if (totalEntries === 0) {
    alert('Sélectionner au moins une fiche à importer.')
    return false
  }

  importBtn.disabled = true
  progressContainer.style.display = 'block'
  importStatus.textContent = 'Import en cours...'
  progressTotal.textContent = totalEntries
  importLog.innerHTML = ''
  progressBar.style.backgroundColor = '#4CAF50'

  const counters = {
    processed: 0,
    successful: 0,
    total: totalEntries
  }

  updateProgressUI(0, 0, totalEntries)

  for (let i = 0; i < totalEntries; i += CONCURRENCY_LIMIT) {
    const chunk = toImport.slice(i, i + CONCURRENCY_LIMIT)
    const promisesInChunk = chunk.map((entryCheckbox) => processEntry(entryCheckbox, counters))
    await Promise.all(promisesInChunk)
  }

  importStatus.textContent = `Import finalisé. (${counters.successful}/${totalEntries} fiches importées avec succès).`
  if (counters.successful < totalEntries) {
    progressBar.style.backgroundColor = '#f44336'
  }
  importBtn.disabled = false

  return false
}, false)
