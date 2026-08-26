import { fileURLToPath } from 'url'

const CHECKOUT = fileURLToPath(new URL('../../../', import.meta.url)).replace(
  /\/$/,
  '',
)

/** Where the wiki under test keeps its files: the checkout under `fpm`, its own folder under `binary`. */
export const instanceDir = (): string =>
  process.env.YESWIKI_TEST_RUNTIME === 'binary'
    ? process.env.YESWIKI_TEST_INSTANCE || '/tmp/yeswiki-e2e'
    : process.env.YESWIKI_TEST_ROOT || CHECKOUT

export const instancePath = (relative: string): string =>
  `${instanceDir()}/${relative}`

/** The URL the wiki's own PHP can fetch one of those files at. */
export const instanceUrl = (relative: string): string => {
  const base = process.env.YESWIKI_TEST_BASE_URL || 'http://yeswiki-web/?'

  return `${base.replace(/\/?\?$/, '')}/${relative}`
}
