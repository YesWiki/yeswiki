import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { setPageContent } from '../helpers/page'
import { watchConsole } from '../helpers/console'
import {
  login,
  logout,
  signedPost,
  ADMIN_USERNAME,
  ADMIN_PASSWORD,
} from '../helpers/login'
import { createEntry } from '../helpers/bazar'

/** The map and the calendar are drawn on the server: a search narrows them without leaving the page, and an entry opens beside them. */

const today = () => {
  const day = new Date()
  return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`
}

const seed = async (page: Page, pageTag: string, content: string) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  for (const [name, latitude, longitude] of [
    ['Lyonnaise', '45.75', '4.85'],
    ['Rennaise', '48.11', '-1.68'],
  ]) {
    const response = await signedPost(page, '/?api/entries/1', {
      data: {
        bf_nom: name,
        bf_prenom: 'Personne',
        bf_mail: `${name.toLowerCase()}@example.org`,
        bf_geolocation: { latitude, longitude },
      },
    })
    expect(response.status()).toBe(201)
  }
  await createEntry(page, 2, {
    bf_titre: 'Atelier vélo',
    bf_date_debut_evenement: today(),
    bf_date_fin_evenement: today(),
  })
  await createEntry(page, 2, {
    bf_titre: 'Repair café',
    bf_date_debut_evenement: today(),
    bf_date_fin_evenement: today(),
  })
  await setPageContent(page, pageTag, content)
  await logout(page)
}

const tileZoom = (page: Page) =>
  page
    .locator('.leaflet-tile-pane img.leaflet-tile')
    .first()
    .evaluate((tile) =>
      new URL((tile as HTMLImageElement).src).pathname.split('/').at(-3),
    )

const searchbox = (page: Page) =>
  page.getByRole('searchbox', { name: 'Rechercher', exact: true })

const markStillHere = (page: Page) =>
  page.evaluate(() => {
    ;(window as unknown as { __stillHere: boolean }).__stillHere = true
  })

const stillHere = (page: Page) =>
  page.evaluate(
    () => (window as unknown as { __stillHere?: boolean }).__stillHere,
  )

test.beforeEach(() => {
  resetEnv()
})

test('a search narrows the map in place and the map keeps its view', async ({
  page,
}) => {
  await seed(
    page,
    'CarteCherchable',
    '{{entrylist id="1" template="map" search="true" lat="47" lon="2" zoom="5"}}',
  )
  const watcher = watchConsole(page)
  await page.goto('/?CarteCherchable')
  await expect(page.locator('.bazar-marker')).toHaveCount(2)

  expect(await tileZoom(page)).toBe('5')
  await page.locator('.leaflet-control-zoom-in').click()
  await expect.poll(() => tileZoom(page)).toBe('6')
  await markStillHere(page)

  await searchbox(page).pressSequentially('Rennaise', { delay: 20 })
  await expect(page.locator('.bazar-marker')).toHaveCount(1)
  await expect(page.locator('.bazar-marker')).toHaveAttribute(
    'aria-label',
    /Rennaise/,
  )
  expect(await stillHere(page)).toBe(true)
  await expect
    .poll(() => tileZoom(page), {
      message: 'the map went back to its starting zoom',
    })
    .toBe('6')
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

test('a marker opens its entry in the side panel', async ({ page }) => {
  await seed(
    page,
    'CarteAvecPanneau',
    '{{entrylist id="1" template="map" entrydisplay="sidebar"}}',
  )
  await page.goto('/?CarteAvecPanneau')
  const side = page.locator('.yw-entry-map__side')
  await expect(side).toBeHidden()

  await page.locator('.bazar-marker[aria-label*="Lyonnaise"]').click()
  await expect(side).toBeVisible()
  await expect(side).toContainText('Lyonnaise')

  await side.getByRole('button', { name: 'Fermer la fiche' }).click()
  await expect(side).toBeHidden()
})

test('a marker opens its entry in a popup', async ({ page }) => {
  await seed(
    page,
    'CarteAvecBulles',
    '{{entrylist id="1" template="map" entrydisplay="popup"}}',
  )
  await page.goto('/?CarteAvecBulles')
  await page.locator('.bazar-marker[aria-label*="Rennaise"]').click()
  await expect(page.locator('.leaflet-popup-content')).toContainText('Rennaise')
})

test('a map and its table list the same entries', async ({ page }) => {
  await seed(
    page,
    'CarteEtTableau',
    '{{entrylist id="1" template="map-and-table" search="true"}}',
  )
  await page.goto('/?CarteEtTableau')
  await expect(page.locator('.bazar-marker')).toHaveCount(2)
  await expect(page.locator('.yw-entry-map__table')).toContainText('Lyonnaise')

  await searchbox(page).fill('Lyonnaise')
  await expect(page.locator('.bazar-marker')).toHaveCount(1)
  await expect(page.locator('.yw-entry-map__table')).not.toContainText(
    'Rennaise',
  )
})

test('a search narrows the calendar and an event opens beside it', async ({
  page,
}) => {
  await seed(
    page,
    'AgendaCherchable',
    '{{entrylist id="2" template="calendar" search="true" entrydisplay="sidebar" showicalbutton="true"}}',
  )
  const watcher = watchConsole(page)
  await page.goto('/?AgendaCherchable')
  const calendar = page.locator('.yw-calendar')
  await expect(calendar.locator('.yw-cal-event')).toHaveCount(2)
  await expect(page.locator('.ical-export-button')).toHaveAttribute(
    'href',
    /api\/entries\/ical.*id=2/,
  )
  await markStillHere(page)

  await searchbox(page).pressSequentially('Repair', { delay: 20 })
  await expect(calendar.locator('.yw-cal-event')).toHaveCount(1)
  await expect(calendar.locator('.yw-cal-event')).toContainText('Repair café')
  await expect(page.locator('.ical-export-button')).toHaveAttribute(
    'href',
    /query=tag%3D/,
  )
  expect(await stillHere(page)).toBe(true)

  await page.locator('.yw-calendar .yw-cal-event').click()
  await expect(page.locator('.yw-entry-calendar__side')).toBeVisible()
  await expect(page.locator('.yw-entry-calendar__side')).toContainText(
    'Repair café',
  )
  await expect(page.locator('.yw-modal--open')).toHaveCount(0)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})
