import { expect, test } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login, logout } from '../helpers/login'

/** A form's tag is its own screen: its list, a way to add, and the designer under /edit (ticket 63). */

test.beforeEach(async ({ page }) => {
  resetEnv()
  await logout(page)
})

test('a bazar form tag lists its entries with an add button', async ({
  page,
}) => {
  await page.goto('/?Annuaire')

  await expect(page.locator('.form-screen__title')).toContainText('Annuaire')
  await expect(page.locator('.form-screen__add')).toHaveAttribute(
    'href',
    /view=saisir&action=saisir_fiche&id=1/,
  )
  await expect(page.locator('.bazar-search')).toBeVisible()
  await expect(page.locator('#form-builder-container')).toHaveCount(0)
})

test('the Pages form tag lists pages, as cards and as a table', async ({
  page,
}) => {
  await page.goto('/?pages')

  await expect(page.locator('.form-screen__title')).toContainText('Pages')
  await expect(page.locator('.bazar-search')).toBeVisible()
  await expect(
    page.locator('#form-screen-list .yw-item'),
    'the seeded pages are listed',
  ).not.toHaveCount(0)

  await page.goto('/?pages&display=table')
  await expect(page.locator('.in-tableau-template')).toBeVisible()
})

test('an enum field gives the list a facet above it, and the display switches', async ({
  page,
}) => {
  await page.goto('/?Ressources')

  await expect(page.locator('.yw-facet-select')).toBeVisible()
  await expect(page.locator('#form-screen-list .yw-items--card')).toBeVisible()
  await expect(page.locator('.export-links')).toBeVisible()

  await page.locator('label[for="form-screen-display-table"]').click()
  await expect(
    page.locator('#form-screen-list .in-tableau-template'),
  ).toBeVisible()
  await expect(page).toHaveURL(/display=table/)
})

test('the add button opens the entry form on the same tag', async ({
  page,
}) => {
  await page.goto('/?Annuaire')
  await page.locator('.form-screen__add').click()

  await expect(page).toHaveURL(/\?Annuaire&view=saisir/)
  await expect(
    page.locator('form#formulaire input[name="bf_nom"]'),
  ).toBeVisible()
  await expect(
    page.locator('.form-screen__actions a', { hasText: 'Retour' }),
  ).toBeVisible()
})

test('/edit on a form tag opens the designer for an admin', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?pages/edit')

  await expect(page.locator('#form-builder-container')).toBeVisible()
  await expect(page.locator('input[name="label"]')).toHaveValue('Pages')
})

test('/edit on a form tag sends a signed-out visitor back to the list', async ({
  page,
}) => {
  await page.goto('/?pages/edit')

  await expect(page).toHaveURL(/\?pages&view=formulaire&msg=/)
  await expect(page.locator('.form-screen__title')).toContainText('Pages')
  await expect(page.locator('#form-builder-container')).toHaveCount(0)
})
