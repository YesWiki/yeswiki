// The emoji picker the editors open from their toolbar: the icon picker's emoji tab, alone, loaded on the first click.
import { legacyIconToSprite } from './yw-icon-map.js'

/** Opens the picker under `anchor`; `onPick` gets the chosen emoji as text. */
export async function openEmojiPicker(anchor, onPick) {
  const { openIconPicker } = await import('./icon-picker.js')
  openIconPicker({
    anchor,
    sources: ['emoji'],
    onPick: ({ value }) => {
      if (value) onPick(value)
    },
  })
}

/** The Vditor toolbar item that opens the picker and hands the emoji to `insert`. */
export function emojiMenuItem(insert) {
  return {
    name: 'yw-emoji',
    tip: _t('EDITOR_EMOJI'),
    tipPosition: 'n',
    icon: legacyIconToSprite('mood-smile'),
    click(event) {
      const anchor = event.target.closest('button') ?? event.target
      openEmojiPicker(anchor, insert)
    },
  }
}
