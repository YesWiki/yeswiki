import InputMultiInput from './InputMultiInput.js'

/** The `query` parameter, as conditions rather than as a string. */
export default {
  mixins: [InputMultiInput],
  methods: {
    parseNewValues(newValues) {
      this.elements = []
      const written = newValues[this.name]
      if (typeof written !== 'string' || written.trim() === '') return

      written
        .split('|')
        .map((condition) => condition.trim())
        .filter(Boolean)
        .forEach((condition) => {
          const match = /^([^=!<>]+?)\s*(==|!=|<=|>=|=|<|>)\s*(.*)$/.exec(
            condition,
          )
          if (!match) return
          this.elements.push({
            name: match[1].trim(),
            operator: match[2] === '==' ? '=' : match[2],
            values: match[3].trim(),
          })
        })
    },
    getValues() {
      const query = this.elements
        .filter((element) => element.name && element.values !== '')
        .map(
          (element) =>
            `${element.name}${element.operator || '='}${element.values}`,
        )
        .join('|')

      return { [this.name]: query }
    },
  },
}
