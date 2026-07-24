import InputMultiInput from './InputMultiInput.js'
import InputPageList from './InputPageList.js'

export default {
  mixins: [InputMultiInput],
  components: { InputPageList },
  methods: {
    parseNewValues(newValues) {
      this.elements = []
      const links = newValues.links ? newValues.links.split(',') : [window.wiki.pageTag, '']
      const titles = newValues.titles ? newValues.titles.split(',') : [window.wiki.pageTag, '']
      for (let i = 0; i < links.length; i++) {
        this.elements.push({
          link: links[i],
          title: titles[i]
        })
      }
    },
    getValues() {
      return {
        links: this.elements.map((g) => g.link.replace(/\s+/g, '-')).filter((e) => e != '').join(','),
        titles: this.elements.map((g) => g.title).filter((e) => e != '').join(',')
      }
    }
  }
}
