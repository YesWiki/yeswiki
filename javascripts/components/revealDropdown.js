/** Scrolls a just-opened v-select menu into view, for a field at the bottom of a scrolling rail. */
export default function revealDropdown() {
  this.$nextTick(() => {
    this.$el
      .querySelector('.vs__dropdown-menu')
      ?.scrollIntoView({ block: 'nearest' })
  })
}
