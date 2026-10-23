import { downloadCSV, removeCSVCrochet } from './bazar.js'

document.getElementById('btnCSV')?.addEventListener('click', (event) => {
  event.preventDefault()
  downloadCSV(removeCSVCrochet($('.precsv').html()), event.currentTarget.dataset.filename)
})
