// tabs.js — moves the form action buttons next to the last tab anchor
// (ticket 16: vanilla JS)
// ticket 14: ywInitEach, so a tabbed form arriving in a fragment gets the same treatment.
// The marker rides on the buttons element, which is what must only move once.
ywInitEach('#formulaire .form-actions.yw-form-group', (buttons) => {
  const anchors = document.querySelectorAll('.anchor-for-for-actions')
  const target = anchors.length > 0 ? anchors[anchors.length - 1] : null
  if (target) {
    target.prepend(buttons)
  }
})
