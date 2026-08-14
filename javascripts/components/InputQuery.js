import InputMultiInput from './InputMultiInput.js'

/**
 * The `query` parameter, as conditions rather than as a string.
 *
 * What it writes is what it always was -- `bf_type=3,4|bf_ville!=Lyon` -- but that string
 * is three separators doing three different jobs: `|` between conditions is AND, `,`
 * inside one is OR, and the operator hides in the middle of a field name and a value.
 * Nobody remembers that, so it was a parameter you could only copy from documentation.
 *
 * One row per condition, each of them a field, an operator and the values it accepts.
 */
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
          // the longest operators first, or `>=` is read as `>` with a `=` in the value
          const match = /^([^=!<>]+?)\s*(==|!=|<=|>=|=|<|>)\s*(.*)$/.exec(
            condition,
          )
          if (!match) return
          this.elements.push({
            name: match[1].trim(),
            // `==` and `=` mean the same thing to the search; the select offers one of them
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
