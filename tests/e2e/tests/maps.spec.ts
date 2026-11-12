import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { setPageContent } from '../helpers/page'
import {
  ADMIN_PASSWORD,
  ADMIN_USERNAME,
  login,
  signedPost,
} from '../helpers/login'

test.beforeEach(async ({ page }) => {
  resetEnv()
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const response = await signedPost(page, '/?api/entries/1', {
    data: {
      bf_nom: 'Carte',
      bf_prenom: 'Personne',
      bf_mail: 'personne@example.org',
      bf_geolocation: { latitude: '45.75', longitude: '4.85' },
    },
  })
  expect(response.status()).toBe(201)
})

/** A map list draws its background through leaflet-providers, falls back to OpenStreetMap for a background that no longer exists, and goes full screen. */

const errorsOn = (page: Page) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  return errors
}

const tileHosts = (page: Page) =>
  page
    .locator('.leaflet-tile-pane img.leaflet-tile')
    .evaluateAll((tiles) =>
      tiles.map((tile) => new URL((tile as HTMLImageElement).src).host),
    )

test('a map list shows OpenStreetMap tiles by default', async ({ page }) => {
  const errors = errorsOn(page)
  await page.goto('/?CartoAnnuaire')

  await expect(page.locator('.leaflet-container')).toBeVisible()
  await expect(
    page.locator('.leaflet-tile-pane img.leaflet-tile').first(),
  ).toBeAttached()
  expect(await tileHosts(page)).toContain('tile.openstreetmap.org')
  expect(errors).toEqual([])
})

test('a background leaflet-providers no longer has falls back to OpenStreetMap', async ({
  page,
}) => {
  await page.goto('/?CartoAnnuaire/raw')
  const call =
    (await page.locator('body').innerText()).match(
      /\{\{entrylist[^}]*\}\}/,
    )?.[0] ?? ''
  expect(call).not.toBe('')
  await setPageContent(
    page,
    'CarteFondDisparu',
    call.replace('}}', ' provider="Stamen.Watercolor"}}'),
  )

  const errors = errorsOn(page)
  const warnings: string[] = []
  page.on('console', (message) => {
    if (message.type() === 'warning') warnings.push(message.text())
  })
  await page.goto('/?CarteFondDisparu')

  await expect(
    page.locator('.leaflet-tile-pane img.leaflet-tile').first(),
  ).toBeAttached()
  expect(await tileHosts(page)).toContain('tile.openstreetmap.org')
  expect(warnings.join('\n')).toContain('Stamen.Watercolor')
  expect(errors).toEqual([])
})

test('the full screen button, titled in French, puts the map in full screen and back', async ({
  page,
}) => {
  await page.goto('/?CartoAnnuaire')
  const button = page.locator('.leaflet-control-zoom-fullscreen').first()
  const fullScreenMap = () =>
    page.evaluate(
      () =>
        document.fullscreenElement?.classList.contains('leaflet-container') ??
        false,
    )

  await expect(button).toHaveAttribute('title', 'Mode plein écran')
  await button.click()
  await expect.poll(fullScreenMap).toBe(true)
  await expect(button).toHaveAttribute('aria-pressed', 'true')
  await expect(button).toHaveAttribute('title', 'Retour à la vue normale')

  await button.click()
  await expect.poll(fullScreenMap).toBe(false)
})
