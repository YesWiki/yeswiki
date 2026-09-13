import {
  FORMAT_MENU,
  INLINE_TOOLS,
  matchesHotkey,
} from './editor-markup-syntax.js'

const SHORTCUTS = [
  ...FORMAT_MENU.flatMap((group) => group.entries),
  ...INLINE_TOOLS.flat(),
].filter((entry) => entry.hotkey)

export default function (aceContainer, toolbar) {
  aceContainer.addEventListener(
    'keydown',
    (event) => {
      const click = (selector) => {
        const button = toolbar.querySelector(selector)
        if (button) button.click()
        event.preventDefault()
        event.stopPropagation()
      }

      if (matchesHotkey(event, '⌘S')) {
        click('.aceditor-btn-save')

        return
      }

      const entry = SHORTCUTS.find((one) => matchesHotkey(event, one.hotkey))
      if (entry) click(`.aceditor-btn-${entry.name}`)
    },
    true,
  )
}
