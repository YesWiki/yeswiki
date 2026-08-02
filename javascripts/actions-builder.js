import { setup, appConfig } from './actions-builder-app.js'

const { createApp } = Vue

const ACTIONS_BACKWARD_COMPATIBILITY = {
  calendar: 'bazarcalendar',
  map: 'entrymap'
}

export default class {
  app

  constructor() {
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

  get allAvailableActions() {
    return Object.values(actionsBuilderData.action_groups)
      .map((e) => Object.keys(e.actions)).flat()
  }

  get allAvailableActionsWithBackward() {
    return this.allAvailableActions.concat(Object.keys(ACTIONS_BACKWARD_COMPATIBILITY))
  }

  getActionConfiguration(actionName) {
    for (const group of Object.values(actionsBuilderData.action_groups)) {
      if (group.actions[actionName]) {
        return group.actions[actionName]
      }
    }
    return {}
  }

  open(editor, options) {
    // Handle backward compat
    if (options.action) {
      const [actionName] = options.action.split(' ')
      const newActionName = ACTIONS_BACKWARD_COMPATIBILITY[actionName] || actionName
      options.action = options.action.replace(new RegExp(`^${actionName}`), newActionName)
    }
    this.app.open(editor, options)
  }
}
