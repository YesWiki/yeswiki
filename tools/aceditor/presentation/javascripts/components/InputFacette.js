import InputMultiInput from './InputMultiInput.js'

export default {
  mixins: [InputMultiInput],
  methods: {
    parseNewValues(newValues) {
      if (newValues.groups) {
        this.elements = []
        let groups = newValues.groups.split(',')
        let titles = newValues.titles ? newValues.titles.split(',') : []
        let icons = newValues.groupicons ? newValues.groupicons.split(',') : []
        for(var i = 0; i < groups.length; i++) {
          this.elements.push({
            field: groups[i],
            title: titles.length >= i ? titles[i] : '' ,
            icon: icons.length >= i ? icons[i] : ''
          })
        }
      }
    },
    getValues() {
      return {
        groups: this.elements.map(g => g.field).filter(e => e != "").join(','),
        titles: this.elements.map(g => g.title).filter(e => e != "").join(','),
        groupicons: this.elements.map(g => g.icon).filter(e => e != "").join(',')
      }
    }
  }
};
