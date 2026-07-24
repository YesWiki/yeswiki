export default class {
  id = null
  classes = []
  attrs = {}

  constructor(string) {
    this.parse(string)
  }

  // Given a string like "foo=bar .toto.test #hello"
  // return { attrs: { foo: "bar"}, id: "hello", classes: ['toto', 'test'] }
  parse(string) {
    if (!string) return

    string.split(/\s+/).filter((s) => !!s).forEach((part) => {
      if (part.startsWith('#')) {
        this.id = part.slice(1)
      } else if (part.startsWith('.')) {
        this.classes = part.split('.').filter((c) => !!c)
      } else {
        let [key, value] = part.split('=')
        if (value === undefined) value = true
        this.attrs[key] = value
      }
    })
  }

  stringify() {
    const result = []
    if (this.id) result.push(`#${this.id}`)
    if (this.classes) result.push(this.classes.filter((c) => c.length > 0).map((c) => `.${c}`).join('').trim())
    Object.entries(this.attrs).forEach(([key, value]) => {
      result.push(`${key}=${value}`)
    })
    return result.join(' ')
  }
}
