// tabs.js — moves the form action buttons next to the last tab anchor
// (ticket 16: vanilla JS)
document.addEventListener('DOMContentLoaded', () => {
  const buttons = document.querySelector('#formulaire .form-actions.yw-form-group')
  const anchors = document.querySelectorAll('.anchor-for-for-actions')
  const target = anchors.length > 0 ? anchors[anchors.length - 1] : null
  if (buttons && target) {
    target.prepend(buttons)
  }
})
