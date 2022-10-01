export default class {
  constructor(onComplete) {
    this.onComplete = onComplete
  }

  get $modal() {
    return $('#YesWikiLinkModal')
  }

  get $inputUrl() {
    return this.$modal.find('input[name=url]')
  }

  get $inputText() {
    return this.$modal.find('input[name=text]')
  }

  initialize() {
    this.$modal.find('.btn-insert').on('click', (e) => {
      let wikiurl = this.$inputUrl.val() || ''

      // Replace spaces by -
      wikiurl = wikiurl.replace(/\s+/g, '-')
      this.$inputUrl.val(wikiurl)

      // Validate page name or url
      const isUrl = /^https?:\/\//.test(wikiurl)
      // We do not allow "." on purpose, even if it's part of WN_PAGE_TAG regular expression
      // because we want inputs like "yeswiki.net" to be interpreted as URL and not page names
      const haveSpecialChars = /[{}|\.\\"'<>~:/?#[\]@!$&()*+,;=%]/.test(wikiurl)
      const validWikiUrl = wikiurl && (isUrl || !haveSpecialChars)
      if (!validWikiUrl) {
        e.stopImmediatePropagation()
        this.$modal.find('.link-error').removeClass('hidden')
        return
      }

      // Create wiki code
      const text = this.$inputText.val() || wikiurl
      const linkOption = this.$modal.find('.link-options').val()
      let result = ''
      if (linkOption === 'link') {
        result = `[[${wikiurl} ${text}]]`
      } else {
        const klass = ({ ext: 'new-window', $modal: '$modalbox' })[linkOption]
        const params = klass ? `class="${klass}" ` : ''
        result = `{{button link="${wikiurl}" text="${text}" ${params}nobtn="1"}}`
      }
      this.onComplete(result)
    })
  }

  open(text = '', url = '') {
    this.$inputUrl.val(url)
    this.$inputText.val(text)

    this.$modal.modal('show').on('shown.bs.modal', () => {
      this.$inputUrl.trigger('focus')
    })
  }
}
