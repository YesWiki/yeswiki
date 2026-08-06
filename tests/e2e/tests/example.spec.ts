import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { errorShouldBe } from '../helpers/alert'
import { replaceEditorTextCallback, saveEditor } from '../helpers/editor'

test.beforeEach(async () => {
  resetEnv()
})

test('has title', async ({ page }) => {
  await page.goto('/')

  // Expect a title "to contain" a substring.
  await expect(page).toHaveTitle(/MyTestWiki : PagePrincipale/)
})

test('can edit main page title', async ({ page }) => {
  await page.goto('/')
  await expect(page.locator('h1')).toContainText(
    /Félicitations, votre wiki est installé !/,
  )

  await page.getByRole('link', { name: 'Éditer la page' }).click()
  await replaceEditorTextCallback(page, (value) =>
    value.replace(
      'Félicitations, votre wiki est installé !',
      'Test de modification de titre',
    ),
  )
  await saveEditor(page)

  await expect(page.locator('h1')).toContainText(
    /Test de modification de titre/,
  )
})

test('have an error message when editing with no change', async ({ page }) => {
  await page.goto('/')
  await page.getByRole('link', { name: 'Éditer la page' }).click()
  await page.waitForLoadState()

  await saveEditor(page)

  await errorShouldBe(
    page,
    "Cette page n'a pas été enregistrée car elle n'a subi aucune modification.",
  )
})
