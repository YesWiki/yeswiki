// admin-config-languages.js -- the two language fields on ?GererConfig, kept in step.
//
// A wiki's main language is not one of the *other* languages it offers, and which one is the
// main one changes the moment the select changes -- not when the form is saved. Without this
// the list still offered the language you had just made the wiki's own, and ticking it wrote a
// configuration that named the same language twice.
//
// The server renders every language and hides the one it knows to be the main one, so this
// only has to move that `hidden` as the select moves. Rendering only the others would leave
// nothing to give back when the main language changes away from a language.
//
// ywInitEach, not a load listener: this screen arrives through an hx-boost navigation like
// every other, so the elements wired at load are not the ones on screen afterwards.
ywInitEach('[data-yw-other-languages]', (list) => {
  const select = document.querySelector('[data-yw-main-language]')
  if (!select) return

  function sync() {
    list.querySelectorAll('[data-language]').forEach((option) => {
      const isMain = option.dataset.language === select.value
      option.hidden = isMain
      // ...and it stops being one of the others, rather than staying ticked out of sight:
      // a hidden checked box still posts, and the wiki would be told its main language is
      // also one of its alternatives
      if (isMain) {
        const box = option.querySelector('input[type="checkbox"]')
        if (box) box.checked = false
      }
    })
  }

  select.addEventListener('change', sync)
  sync()
})
