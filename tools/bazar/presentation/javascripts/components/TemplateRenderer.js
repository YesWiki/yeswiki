const templatesForRendering = {}

const getTemplateFromSlot = (id, base, name, params = {}) => {
  const key = `${name}-${JSON.stringify(params)}`
  if (!(id in templatesForRendering)) {
    templatesForRendering[id] = {}
  }
  if (!(key in templatesForRendering[id])) {
    if (name in base.$scopedSlots) {
      const slot = base.$scopedSlots[name]
      const constructor = Vue.extend({
        render(h) {
          return h('div', {}, slot(params))
        }
      })
      const instance = new constructor()
      instance.$mount()
      let outerHtml = ''
      for (let index = 0; index < instance.$el.childNodes.length; index++) {
        outerHtml += instance.$el.childNodes[index].outerHTML || instance.$el.childNodes[index].textContent
      }
      templatesForRendering[id][key] = outerHtml
    } else {
      templatesForRendering[id][key] = ''
    }
  }
  return templatesForRendering[id][key]
}

const render = (id, base, name, params = {}, replacement = []) => {
  let output = getTemplateFromSlot(id, base, name, params)
  replacement.forEach(([anchor, replacement]) => {
    output = output.replace(anchor, replacement)
  })
  return output
}

export default { getTemplateFromSlot, render }
