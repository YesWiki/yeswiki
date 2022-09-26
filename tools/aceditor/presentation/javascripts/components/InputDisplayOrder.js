import InputDisplayOrderMultiinput from './InputDisplayOrderMultiinput.js'

export default {
  mixins: [InputDisplayOrderMultiinput],
  methods: {
    parseNewValues(newValues) {
      if (newValues.displayorder) {
        this.elements = []
        let types = newValues.displayorder.split(',')
        let titles = newValues.titles ? newValues.titles.split(',') : []
        for(var i = 0; i < types.length; i++) {
          let isTag = (types[i].length > 4 && types[i].slice(0,4) == "tag:");
          let isForm = !Number.isNaN(Number(types[i])) && parseInt(types[i]) > 0;
          this.elements.push({
              type: ['page','pages'].includes(types[i])
                ? 'pages' 
                : (
                  ['logpage','logspages','logpages','logspage'].includes(types[i])
                  ? 'logpages'
                  : (isTag ? 'tag'
                    : ( isForm ? 'form' : "" )
                  )
                ),
              title: titles.length >= i ? titles[i] : '' ,
              value: isTag
                ? types[i].slice(4) 
                : ( isForm ? parseInt(types[i]) :""),
            }
          );
        }
      }
    },
    getValues() {
      return {
        displayorder: this.elements.map(g => {
          switch (g.type) {
            case 'pages':
              return 'pages'
            case 'logpages':
              return 'logpages';
            case 'tag':
              return `tag:${g.value.replace(',','')}`;
            case 'form':
              return g.value ;
            default:
              return "";
          }
        }).join(','),
        titles: this.elements.map(g => g.title).join(','),
      }
    }
  }
};
