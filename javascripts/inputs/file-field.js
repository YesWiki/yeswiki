function handleFileSelect(evt) {
  const target = evt.target || evt.srcElement
  const { id } = target
  const { files } = target // FileList object

  for (var i = 0, f; f = files[i]; i++) {
    const fileMaxSize = document.getElementById(id).dataset.maxSize
    if (f.size > fileMaxSize) {
      alert(_t('FILEFIELD_TOO_LARGE_FILE', { fileMaxSize }))
      document.getElementById(id).type = ''
      document.getElementById(id).type = 'file'
      continue
    }
  }
}

const fileinputs = document.getElementsByClassName('yw-file-upload')
for (let i = 0; i < fileinputs.length; i++) {
  fileinputs.item(i).addEventListener('change', handleFileSelect, false)
}
