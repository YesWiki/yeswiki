import { setup, appConfig } from './actions-builder-app.js'

const { createApp } = Vue

const ACTIONS_BACKWARD_COMPATIBILITY = {
  calendar: 'bazarcalendar',
  map: 'entrymap',
}

/**
 * The rail is one app for the whole session, until a boosted navigation swaps the page it
 * was mounted on: the element it rendered into is then off the document, and the panel the
 * new page brought is still raw template. So it is remounted on whichever one is on screen.
 */
let rail = null

export default class {
  app

  constructor() {
    const host = document.getElementById('actions-builder-app')
    if (!host) return
    if (!rail?.instance.$el?.isConnected) {
      rail?.vueApp.unmount()
      const vueApp = createApp(appConfig)
      setup(vueApp)
      rail = { vueApp, instance: vueApp.mount(host) }
    }
    this.app = rail.instance
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
