const BASE_URL = `${wiki.baseUrl.replace(/\?+$/, '')}`

function addCommaSeparatedString(mainString, stringToAdd) {
  if (!stringToAdd) {
    return mainString
  }

  const mainArray = mainString
    ? mainString.split(',').map((item) => item.trim())
    : []
  const addArray = stringToAdd.split(',').map((item) => item.trim())

  addArray.forEach((item) => {
    if (!mainArray.includes(item)) {
      mainArray.push(item)
    }
  })

  return mainArray.join(',')
}

async function loadEntry(pageTag) {
  try {
    const response = await fetch(`${BASE_URL}?${pageTag}`, {
      method: 'GET',
      headers: { Accept: 'application/json' },
    })

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }

    return await response.json()
  } catch (error) {
    console.error('Error loading entry:', error)
    throw error
  }
}

async function createEntry(formId, data) {
  try {
    const response = await fetch(`${BASE_URL}api/entries/${formId}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(data),
    })

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }

    return await response.json()
  } catch (error) {
    console.error('Error creating entry:', error)
    throw error
  }
}

async function modifyEntry(data) {
  try {
    const response = await fetch(
      `${BASE_URL}api/entries/${data.id_typeannonce}`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(data),
      },
    )

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`)
    }

    return await response.json()
  } catch (error) {
    console.error('Error modifying entry:', error)
    throw error
  }
}

document.addEventListener('DOMContentLoaded', () => {
  function validateDataTargets(el, options = {}) {
    const { execute = false, onValid } = options
    const targetSel = el.dataset.targetId
    const valueSel = el.dataset.value
    const targetNode = targetSel ? document.querySelector(targetSel) : null
    const valueNode = valueSel ? document.querySelector(valueSel) : null

    if (!targetNode || !valueNode) {
      console.warn('Data-attribute validation failed:', {
        element: el,
        targetSel,
        valueSel,
        targetNode,
        valueNode,
      })
      return false
    }

    if (execute && typeof onValid === 'function') {
      onValid(targetNode, valueNode, el.dataset.action)
    }

    return true
  }

  document.querySelector('.page').addEventListener('click', (event) => {
    const clicked = event.target.closest('.bazar-api')
    if (clicked) {
      validateDataTargets(clicked, {
        execute: true,
        onValid: async (targetEl, valueEl, action) => {
          event.preventDefault()
          event.stopPropagation()

          const entryId = targetEl.value
          const fieldId = clicked.dataset.targetField
          const { onSuccess } = clicked.dataset
          const val = valueEl.value

          if (action === 'add' && val.length > 0) {
            try {
              const entry = await loadEntry(entryId)

              const currentFieldValue = entry[fieldId] || ''
              entry[fieldId] = addCommaSeparatedString(currentFieldValue, val)

              const response = await modifyEntry(entry)
              console.log('Entry modified:', response)

              if (onSuccess === 'refresh') {
                window.location.reload()
              }
            } catch (error) {
              console.error('Failed during add operation:', error)
            }
          }
        },
      })
    }
    return false
  })
})

window.createEntry = createEntry
