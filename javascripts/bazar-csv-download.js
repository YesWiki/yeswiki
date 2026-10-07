// Saves the previewed bazar CSV under data-filename.
import { downloadCSV, removeCSVCrochet } from './bazar.js'

ywInitEach('#btnCSV', (button) => {
  button.addEventListener('click', (event) => {
    event.preventDefault()
    downloadCSV(
      removeCSVCrochet(document.querySelector('.precsv').innerHTML),
      button.dataset.filename,
    )
  })
})
