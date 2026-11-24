import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { watchConsole } from '../helpers/console'
import {
  login,
  signedPost,
  ADMIN_USERNAME,
  ADMIN_PASSWORD,
} from '../helpers/login'

/** The history of a page is drawn on the server: moving between revisions and views swaps it in place, and an old revision can be restored. */

const PAGE = 'PageAHistoire'

const save = async (page: Page, body: string) => {
  const response = await signedPost(page, `/?api/pages/${PAGE}`, {
    form: { body },
  })
  expect(response.status()).toBe(200)
  await page.waitForTimeout(1100)
}

test.beforeEach(async ({ page }) => {
  resetEnv()
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await save(page, 'Première version')
  await save(page, 'Deuxième version')
  await save(page, 'Troisième version')
})

test('moving through the history swaps it in place', async ({ page }) => {
  const watcher = watchConsole(page)
  await page.goto(`/?${PAGE}/revisions`)
  const history = page.locator('#revisions')
  await expect(history.locator('.timeline-item')).toHaveCount(3)
  await expect(history.locator('h3')).toContainText('N°3')
  await expect(
    history.getByRole('button', { name: 'Version actuelle' }),
  ).toBeDisabled()
  await page.evaluate(() => {
    ;(window as unknown as { __stillHere: boolean }).__stillHere = true
  })

  await history.getByRole('link', { name: 'N°2', exact: true }).click()
  await expect(history.locator('h3')).toContainText('N°2')
  await expect(page).toHaveURL(/revision=\d+/)

  await history
    .getByRole('link', { name: 'Modifs apportées par cette version' })
    .click()
  await expect(history.locator('.preview-content ins')).toContainText(
    'Deuxième',
  )
  await expect(history.locator('.preview-content del')).toContainText(
    'Première',
  )

  await history
    .getByRole('link', { name: 'Comparaison avec version actuelle' })
    .click()
  await expect(history.locator('.preview-content')).toContainText('Troisième')

  await history.getByRole('switch', { name: 'Afficher le code Wiki' }).click()
  await expect(history.getByRole('switch')).toHaveAttribute(
    'aria-checked',
    'true',
  )
  await expect(page).toHaveURL(/code=1/)
  await expect(history.locator('pre.preview-content')).toBeVisible()

  expect(
    await page.evaluate(
      () => (window as unknown as { __stillHere?: boolean }).__stillHere,
    ),
  ).toBe(true)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

test('an old revision comes back as the current one', async ({ page }) => {
  await page.goto(`/?${PAGE}/revisions`)
  await page.getByRole('link', { name: /^N°1 - / }).click()
  await expect(page.locator('#revisions h3')).toContainText('N°1')

  await page
    .getByRole('button', { name: 'Restaurer cette version', exact: true })
    .click()
  await expect(page.locator('#yw-main')).toContainText('Première version')

  await page.goto(`/?${PAGE}/revisions`)
  await expect(page.locator('.timeline-item')).toHaveCount(4)
})
