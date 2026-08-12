import { setup, appConfig } from './actions-builder-app.js'

const { createApp } = Vue

const ACTIONS_BACKWARD_COMPATIBILITY = {
  calendar: 'bazarcalendar',
  map: 'entrymap',
}

export default class {
  app

  constructor() {
    // A page can render an editor without the rails -- the comment form did. Mounting
    // then leaves `app` undefined, and every later call would throw on it, so the
    // builder becomes inert instead: no panel, nothing to open.
    if (!document.getElementById('actions-builder-app')) return
    // Initialize only once the app
    if (window.actionBuilderApp) {
      this.app = window.actionBuilderApp
    } else {
      const vueApp = createApp(appConfig)
      setup(vueApp)
      this.app = vueApp.mount('#actions-builder-app')
      window.actionBuilderApp = this.app
    }
  }

  /** Every `{{tag}}` a declared Component knows about -- what the editors highlight. */
  get allAvailableActions() {
    return [
      ...new Set(
        Object.values(actionsBuilderData.components || {}).flatMap(
          (component) => component.tags || [],
        ),
      ),
    ]
  }

  get allAvailableActionsWithBackward() {
    return this.allAvailableActions.concat(
      Object.keys(ACTIONS_BACKWARD_COMPATIBILITY),
    )
  }

  /** By tag, since that is what a page holds; the unpinned component is the general one. */
  getActionConfiguration(actionName) {
    const components = Object.values(actionsBuilderData.components || {})

    return (
      components.find((c) => (c.tags || []).includes(actionName) && !c.pins) ||
      components.find((c) => (c.tags || []).includes(actionName)) ||
      {}
    )
  }

  /** True while the rail is placing a component the document does not contain yet. */
  get isPlacingNewAction() {
    return this.app ? this.app.isPlacingNewAction : false
  }

  close() {
    if (this.app) this.app.close()
  }

  open(editor, options) {
    if (!this.app) return
    // Handle backward compat
    if (options.action) {
      const [actionName] = options.action.split(' ')
      const newActionName =
        ACTIONS_BACKWARD_COMPATIBILITY[actionName] || actionName
      options.action = options.action.replace(
        new RegExp(`^${actionName}`),
        newActionName,
      )
    }
    this.app.open(editor, options)
  }
}
