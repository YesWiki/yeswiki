// Adds the wiki's anti-CSRF token to every request this page sends to the wiki itself:
// fetch and XMLHttpRequest calls get a header, POST forms get a hidden field.
;(() => {
  const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS']
  const HEADER = 'X-CSRF-Token'
  const FIELD = 'csrf-token'

  const token = () => (typeof wiki !== 'undefined' && wiki.antiCsrfToken) || ''

  const isHere = (url) => {
    try {
      return (
        new URL(url, window.location.href).origin === window.location.origin
      )
    } catch (_error) {
      return false
    }
  }

  const changes = (method) =>
    !SAFE_METHODS.includes(String(method || 'GET').toUpperCase())

  const nativeFetch = window.fetch
  if (nativeFetch) {
    window.fetch = function guardedFetch(input, init = {}) {
      const request = input instanceof Request ? input : null
      const method = init.method || (request ? request.method : 'GET')
      const url = request ? request.url : String(input)
      if (!changes(method) || !isHere(url) || !token()) {
        return nativeFetch.call(this, input, init)
      }
      const headers = new Headers(
        init.headers || (request ? request.headers : undefined),
      )
      if (!headers.has(HEADER)) headers.set(HEADER, token())
      return nativeFetch.call(this, input, { ...init, headers })
    }
  }

  const nativeOpen = XMLHttpRequest.prototype.open
  const nativeSend = XMLHttpRequest.prototype.send
  XMLHttpRequest.prototype.open = function guardedOpen(method, url, ...rest) {
    this.yeswikiCsrf = changes(method) && isHere(url)
    return nativeOpen.call(this, method, url, ...rest)
  }
  XMLHttpRequest.prototype.send = function guardedSend(body) {
    if (this.yeswikiCsrf && token()) {
      try {
        this.setRequestHeader(HEADER, token())
      } catch (_error) {
        this.yeswikiCsrf = false
      }
    }
    return nativeSend.call(this, body)
  }

  const arm = (form) => {
    if (!(form instanceof HTMLFormElement) || !token()) return
    if (String(form.method).toUpperCase() !== 'POST' || !isHere(form.action))
      return
    if (form.querySelector(`input[name="${FIELD}"]`)) return
    const field = document.createElement('input')
    field.type = 'hidden'
    field.name = FIELD
    field.value = token()
    form.appendChild(field)
  }

  document.addEventListener('submit', (event) => arm(event.target), true)

  const nativeSubmit = HTMLFormElement.prototype.submit
  HTMLFormElement.prototype.submit = function guardedSubmit() {
    arm(this)
    return nativeSubmit.call(this)
  }
})()
