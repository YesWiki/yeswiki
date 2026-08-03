/**
 * The slot to the right of an editor, and the rails that take turns in it.
 *
 * Three panels dock there now -- the actions builder, the link editor, the file picker --
 * and they are opened by unrelated things: a cursor moving, a toolbar button, a Vditor
 * menu item. None of them can be made responsible for knowing about the others, and a
 * page can carry several editors, each with its own instances. So a rail says "the slot
 * is mine" and every other registered rail is asked to close itself.
 *
 * Closing goes through each rail's own close(), never through hiding its element: a rail
 * has state to reset -- what it was editing, whether it is placing something new -- and a
 * panel hidden behind its back would leave that state claiming to be open.
 */
const rails = new Set()

/** @param rail an object with a close() method; safe to call when already closed. */
export function registerRail(rail) {
  rails.add(rail)
}

export function claimRailSlot(rail) {
  rails.forEach((other) => {
    if (other !== rail) other.close()
  })
}

/** Close every rail: the cursor moved somewhere none of them is about. */
export function closeRails() {
  claimRailSlot(null)
}
