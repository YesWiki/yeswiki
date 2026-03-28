/**
 * Vue 3 Template Renderer utility
 * Renders slot content to HTML strings for use in non-Vue contexts (like DataTables)
 */
const { createApp, h } = Vue

const templatesForRendering = {}

const getTemplateFromSlot = (id, base, name, params = {}) => {
  const key = `${name}-${JSON.stringify(params)}`
  if (!(id in templatesForRendering)) {
    templatesForRendering[id] = {}
  }
  if (!(key in templatesForRendering[id])) {
    // Vue 3: $slots contains functions that return VNodes
    const slots = base.$slots
    if (slots && name in slots) {
      const slotFn = slots[name]
      // Create a temporary app to render the slot to HTML
      const tempContainer = document.createElement('div')
      const app = createApp({
        render() {
          return h('div', {}, slotFn(params))
        }
      })
      app.mount(tempContainer)
      let outerHtml = ''
      const children = tempContainer.firstChild?.childNodes || []
      for (let index = 0; index < children.length; index++) {
        outerHtml += children[index].outerHTML || children[index].textContent
      }
      app.unmount()
      templatesForRendering[id][key] = outerHtml
    } else {
      templatesForRendering[id][key] = ''
    }
  }
  return templatesForRendering[id][key]
}

const render = (id, base, name, params = {}, replacement = []) => {
  let output = getTemplateFromSlot(id, base, name, params)
  replacement.forEach(([anchor, rep]) => {
    output = output.replace(anchor, rep)
  })
  return output
}

export default { getTemplateFromSlot, render }
