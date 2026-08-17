/** The slot to the right of an editor, and the rails that take turns in it. */
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
