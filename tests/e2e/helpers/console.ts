import { Page, TestInfo } from '@playwright/test'

/**
 * Collects everything the browser complained about during a test.
 *
 * This is the net that was missing while ticket 16 was built. Every regression it produced --
 * a script killed by a stray quote, `ywInitEach` keyed on an element that outlives the
 * navigation, `vue-select` running before Vue existed -- announced itself in the console and
 * nowhere else. PHPStan, PHPUnit and eslint were all green throughout.
 */
export type ConsoleWatcher = {
  /** Uncaught exceptions and console.error output, in order. */
  errors: () => string[]
}

/** Warnings that are noise rather than signal, and are not ours to fix. */
const IGNORED = [
  // leaflet registers a legacy event name; harmless and upstream
  /wrong event specified: touchleave/i,
  // vditor lazily fetches a theme stylesheet we do not vendor
  /vditor\/dist\/css\/content-theme/i,
  // Firefox/Chrome deprecation notices from vendored libraries
  /synchronous XMLHttpRequest/i
]

const isNoise = (text: string) => IGNORED.some((pattern) => pattern.test(text))

export const watchConsole = (page: Page): ConsoleWatcher => {
  const collected: string[] = []

  page.on('pageerror', (error) => {
    const text = `uncaught: ${error.message}`
    if (!isNoise(text)) collected.push(text)
  })

  page.on('console', (message) => {
    if (message.type() !== 'error') return
    const text = message.text()
    // The browser's own "Failed to load resource: … 404" carries no URL, so it cannot be
    // matched against the ignore list and would report a failure the response listener below
    // has already decided is noise. That listener sees the URL; this one is the duplicate.
    if (/Failed to load resource/i.test(text)) return
    if (!isNoise(text)) collected.push(`console.error: ${text}`)
  })

  // a 404 on a script or stylesheet is a page fault even when nothing throws: the browser
  // parses the HTML error page as JavaScript and the failure surfaces somewhere unrelated
  page.on('response', (response) => {
    const url = response.url()
    if (response.status() !== 404) return
    if (!/\.(js|css)(\?|$)/.test(url)) return
    if (isNoise(url)) return
    collected.push(`404: ${url}`)
  })

  return { errors: () => [...collected] }
}

/** Attach the collected output to the report, so a failure says what the browser said. */
export const attachConsole = async (watcher: ConsoleWatcher, testInfo: TestInfo) => {
  const errors = watcher.errors()
  if (errors.length > 0) {
    await testInfo.attach('browser-console', { body: errors.join('\n'), contentType: 'text/plain' })
  }
}
