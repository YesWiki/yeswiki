import { setup, app } from './actions-builder-app.js'

const ACTIONS_BACKWARD_COMPATIBILITY = {
  calendrier: 'bazarcalendar',
  map: 'bazarcarto'
}

export default class {
  app

  constructor() {
    // Initialize only once the app
    if (window.actionBuilderApp) {
      this.app = window.actionBuilderApp
    } else {
      setup()
      this.app = new Vue(app)
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
