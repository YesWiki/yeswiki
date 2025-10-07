const BASE_URL = `${wiki.baseUrl.replace(/\?+$/, '')}`

/**
 * Loads an entry's value from the API.
 * @param {string} pageTag - The tag of the page to load.
 * @returns {Promise<object>} A promise that resolves with the JSON entry data.
 * @throws {Error} If the network request fails or the response is not OK.
 */
async function loadEntry(pageTag) {
  try {
    const response = await fetch(`${BASE_URL}?${pageTag}`, {
      method: 'GET',
      headers: { Accept: 'application/json' }
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

/**
 * Creates a new entry via the API.
 * @param {string} formId - The ID of the form to create the entry for.
 * @param {object} data - The data for the new entry.
 * @returns {Promise<object>} A promise that resolves with the JSON response from the API.
 * @throws {Error} If the network request fails or the response is not OK.
 */
async function createEntry(formId, data) {
  try {
    const response = await fetch(`${BASE_URL}api/entries/${formId}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
      },
      body: JSON.stringify(data)
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

/**
 * Modifies an existing entry via the API.
 * @param {string} pageTag - The tag of the page to modify.
 * @param {object} data - The updated data for the entry.
 * @returns {Promise<object>} A promise that resolves with the JSON response from the API.
 * @throws {Error} If the network request fails or the response is not OK.
 */
async function modifyEntry(pageTag, data) {
  try {
    console.log(pageTag, data)
    const response = await fetch(`${BASE_URL}?${pageTag}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json'
      },
      body: JSON.stringify(data)
    })

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
    const results = []
    const targetSel = el.dataset.targetId
    const valueSel = el.dataset.value
    const targetNode = targetSel ? document.querySelector(targetSel) : null
    const valueNode = valueSel ? document.querySelector(valueSel) : null

    results.push({ el, target: targetNode, valueEl: valueNode })

    if (!targetNode || !valueNode) {
      console.warn('Data‑attribute validation failed:', {
        element: el,
        targetSel,
        valueSel,
        targetNode,
        valueNode
      })
      return false
    }

    // Optional immediate action
    if (execute && typeof onValid === 'function') {
      onValid(targetNode, valueNode, el.dataset.action)
    }

    return false
  }

  document.querySelector('.page').addEventListener('click', (event) => {
    event.preventDefault()
    event.stopPropagation()
    const clicked = event.target.closest('.bazar-api')
    if (clicked) {
      validateDataTargets(clicked, {
        execute: true,
        onValid: (targetEl, valueEl, action) => {
          const entryId = targetEl.value
          const fieldId = clicked.dataset.targetField
          const val = valueEl.value
          if (action === 'add') {
            console.log(targetEl.value, valueEl.value, action)
            loadEntry(entryId)
              .then((entry) => {
                console.log('Loaded entry:', entry)
                entry.antispam = 1
                if (entry[fieldId] === undefined || entry[fieldId].length == 0) {
                  entry[fieldId] = val
                } else {
                  entry[fieldId] = `${entry[fieldId]},${val}`
                }
                modifyEntry(entryId, entry)
                  .then((response) => console.log('Entry modified:', response))
                  .catch((error) => console.error('Failed to modify entry:', error))
              })
              .catch((error) => console.error('Failed to load entry:', error))

            // // Create a new entry
            // const newEntryData = {
            //     title: 'My New Post',
            //     content: 'This is the content of my new post.'
            // };
            // createEntry('blogForm', newEntryData)
            //     .then(response => console.log('Entry created:', response))
            //     .catch(error => console.error('Failed to create entry:', error));
          }
        }
      })
    }
    return false
  })
})
