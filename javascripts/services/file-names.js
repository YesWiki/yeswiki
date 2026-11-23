/** The original filename of each file entry by tag, so a condition on a file's extension holds for a file named by its tag. */

const { reactive } = Vue
const names = reactive({})
let loading = null

const hasExtension = (value) => /\.[a-z0-9]+$/i.test(value)

/** Fetches the file entries once; every later call waits on the same request. */
export function loadFileNames() {
  loading ??= fetch(wiki.url('api/files'))
    .then((response) => (response.ok ? response.json() : []))
    .catch(() => [])
    .then((entries) => {
      for (const entry of Array.isArray(entries) ? entries : []) {
        const name = String(entry.original_filename || entry.tag || '')
        const extension = String(entry.extension || '').toLowerCase()
        names[entry.tag] =
          extension && !name.toLowerCase().endsWith(`.${extension}`)
            ? `${name}.${extension}`
            : name
      }
    })

  return loading
}

/** Resolves once the names are known when this component's code names a file by its tag, at once otherwise. */
export function fileNamesReady(wikiCode) {
  const file = /\bfile\s*=\s*"([^"]*)"/.exec(String(wikiCode || ''))?.[1]

  return file && !hasExtension(file) ? loadFileNames() : Promise.resolve()
}

/** What to test a file setting against: the original filename when the value is a file entry's tag. */
export function fileNameFor(value) {
  if (!value || hasExtension(value)) return value
  loadFileNames()

  return names[value] ?? value
}
