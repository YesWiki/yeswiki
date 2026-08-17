import { Page, TestInfo } from '@playwright/test'

/** Collects everything the browser complained about during a test. */
export type ConsoleWatcher = {
  /** Uncaught exceptions and console.error output, in order. */
  errors: () => string[]
}

/** Warnings that are noise rather than signal, and are not ours to fix. */
const IGNORED = [
  /wrong event specified: touchleave/i,
  /vditor\/dist\/css\/content-theme/i,
  /synchronous XMLHttpRequest/i,
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
    if (/Failed to load resource/i.test(text)) return
    if (!isNoise(text)) collected.push(`console.error: ${text}`)
  })

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
export const attachConsole = async (
  watcher: ConsoleWatcher,
  testInfo: TestInfo,
) => {
  const errors = watcher.errors()
  if (errors.length > 0) {
    await testInfo.attach('browser-console', {
      body: errors.join('\n'),
      contentType: 'text/plain',
    })
  }
}
