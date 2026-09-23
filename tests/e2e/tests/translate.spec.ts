import { expect, Page, test } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import {
  editorReady,
  editorText,
  replaceEditorTextNewContent,
  saveEditor,
} from '../helpers/editor'
import { setPageContent } from '../helpers/page'
import { createEntry } from '../helpers/bazar'

test.beforeEach(async () => {
  resetEnv()
})

const PAGE = 'PageATraduire'

/** The one language switch, which an edit screen turns into the switch between translations. */
const switchTo = (page: Page, code: string) =>
  page.locator(`.yw-corner-tools .yw-switcher a[hreflang="${code}"]`)

/** It lives in a menu that has to be opened, so it is followed rather than clicked. */
const follow = async (page: Page, code: string) => {
  const href = await switchTo(page, code).getAttribute('href')
  await page.goto(href ?? '')
}

/** Clicked the way a reader clicks it, which is what the unsaved-changes guard listens for. */
const clickSwitch = async (page: Page, code: string) => {
  await page
    .locator('.yw-corner-tools [data-yw-dropdown-toggle]')
    .last()
    .click()
  await switchTo(page, code).click()
}

test('the page editor offers the languages, and says when it is translating', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, PAGE, 'Du contenu en français')

  await page.goto(`/?${PAGE}/edit`)
  await expect(page.locator('.yw-corner-tools .yw-switcher a')).toHaveCount(3)
  await expect(
    switchTo(page, 'fr'),
    'the language the page is written in is marked as the source',
  ).toHaveClass(/yw-switcher__option--source/)
  await expect(
    switchTo(page, 'en'),
    'and one that has nothing translated yet says so',
  ).toHaveClass(/yw-switcher__option--empty/)

  await follow(page, 'en')
  await expect(page).toHaveURL(/editlang=en/)
  await expect(switchTo(page, 'en')).toHaveClass(/yw-switcher__option--on/)
})

test('a page is translated in the editor it is written in, and the source stays put', async ({
  page,
}) => {
  test.slow()
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, PAGE, 'Du contenu en français')

  await page.goto(`/?${PAGE}/edit&editlang=en`)
  await editorReady(page)
  expect(
    (await editorText(page)).trim(),
    'nothing translated yet, so the editor starts empty',
  ).toBe('')

  await replaceEditorTextNewContent(page, 'Some content in English')
  await saveEditor(page)

  await page.goto(`/?${PAGE}&lang=en`)
  await expect(page.locator('#yw-main')).toContainText(
    'Some content in English',
  )

  await page.goto(`/?${PAGE}&lang=fr`)
  await expect(page.locator('#yw-main')).toContainText('Du contenu en français')
})

test('a field left empty keeps showing the source, it does not go blank', async ({
  page,
}) => {
  test.slow()
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, PAGE, 'Du contenu en français')

  await page.goto(`/?${PAGE}/edit&editlang=en`)
  await editorReady(page)
  await saveEditor(page)

  await page.goto(`/?${PAGE}&lang=en`)
  await expect(page.locator('#yw-main')).toContainText('Du contenu en français')
})

test('the entry editor shows only the fields worth translating', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const tag = await createEntry(page, 2, {
    bf_titre: 'Super événement à Bordeaux',
    bf_description: 'Un événement autour du vin',
    bf_date_debut_evenement: '2024-04-10',
    bf_date_fin_evenement: '2024-04-12',
    bf_ville: 'Bordeaux',
  })

  await page.goto(`/?${tag}/edit`)
  const all = await page.locator('[name^="bf_"]').count()
  expect(all, 'the entry draws its fields').toBeGreaterThan(0)

  await page.goto(`/?${tag}/edit&editlang=en`)
  const translatable = await page.locator('[name^="bf_"]').count()

  expect(translatable).toBeGreaterThan(0)
  expect(
    translatable,
    'a date, an email or an image is the same in every language',
  ).toBeLessThan(all)
})

test('switching language with unsaved changes asks first', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, PAGE, 'Du contenu en français')

  await page.goto(`/?${PAGE}/edit`)
  await editorReady(page)
  await replaceEditorTextNewContent(page, 'Une modification non enregistrée')

  let asked = false
  page.on('dialog', (dialog) => {
    asked = true
    dialog.dismiss().catch(() => {})
  })

  await clickSwitch(page, 'en')
  await page.waitForTimeout(500)

  expect(asked, 'the unsaved-changes guard spoke up').toBe(true)
})
