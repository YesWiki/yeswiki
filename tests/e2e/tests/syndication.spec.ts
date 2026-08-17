import { test, expect } from '@playwright/test'
import { writeFileSync, rmSync } from 'fs'
import { resetEnv } from '../helpers/db'
import { attachConsole, watchConsole } from '../helpers/console'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { setPageContent } from '../helpers/page'

/** A feed, rendered as cards. */

const FIXTURE = '/var/www/html/files/yw-test-feed.xml'
const FEED_URL = 'http://yeswiki-web/files/yw-test-feed.xml'

const FEED = `<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0"><channel>
  <title>Un flux</title>
  <link>/relative/channel</link>
  <item>
    <title>Un article</title>
    <link>/relative/article</link>
    <description>Une description assez longue pour etre coupee par le parametre maxchars, avec plusieurs mots au dela de la limite demandee.</description>
  </item>
  <item>
    <title>Un autre</title>
    <link>/relative/autre</link>
    <description>Court.</description>
  </item>
</channel></rss>`

test.beforeEach(async () => {
  resetEnv()
  writeFileSync(FIXTURE, FEED)
})

test.afterAll(() => {
  rmSync(FIXTURE, { force: true })
})

test('a feed renders as cards, and prints nothing of its own', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(
    page,
    'FeedCards',
    `{{syndication url="${FEED_URL}" template="card"}}`,
  )
  await page.goto('/?FeedCards')

  await expect(page.locator('.yw-item--card')).toHaveCount(2)
  await expect(
    page.locator('body'),
    'the page carries the feed, not PHP notices about reading it',
  ).not.toContainText('Deprecated:')

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/** ...and a summary written for a feed reader can be cut down to a card's worth of it. */
test('maxchars cuts a long description and links to the rest', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(
    page,
    'FeedCards',
    `{{syndication url="${FEED_URL}" template="card" maxchars="40"}}`,
  )
  await page.goto('/?FeedCards')

  const cut = page.locator('.yw-item--card', { hasText: 'Un article' })
  await expect(cut).toContainText('Une description assez longue pour etre')
  await expect(cut, 'and not the rest of it').not.toContainText('demandee')
  await expect(cut.locator('a.lien_lire_suite')).toHaveCount(1)

  await expect(
    page.locator('.yw-item--card', { hasText: 'Un autre' }),
  ).toContainText('Court.')
})
