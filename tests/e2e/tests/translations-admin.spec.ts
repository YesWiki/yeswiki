import { expect, Page, test } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import {
  editorReady,
  replaceEditorTextNewContent,
  saveEditor,
} from '../helpers/editor'
import { setPageContent } from '../helpers/page'
import { createEntry } from '../helpers/bazar'

/** The screen that says how far the wiki has been translated, and leads to where it is done. */

const SCREEN = '/?admin/translations'
const PAGE = 'PageDeCouverture'

test.beforeEach(async () => {
  resetEnv()
})

/** The `en` cell of the row whose content is `label`, in whichever table it is in. */
const cellFor = (page: Page, label: string) =>
  page
    .locator('.yw-translations tbody tr', { hasText: label })
    .first()
    .locator('a[href*="editlang=en"]')
    .first()

test('every kind of Content is counted, each on its own line', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await createEntry(page, 2, {
    bf_titre: 'Une sortie',
    bf_date_debut_evenement: '2024-04-10',
    bf_date_fin_evenement: '2024-04-12',
  })
  await createEntry(page, 4, { bf_titre: 'Une ressource', bf_type: '1' })
  await page.goto(SCREEN)

  const summary = page.locator('.yw-translations').first()
  await expect(summary).toBeVisible()

  const lines = await summary
    .locator('tbody tr th[scope="row"]')
    .allTextContents()
  const said = lines.map((line) => line.replace(/\s+/g, ' ').trim())

  expect(
    said.some((line) => line.endsWith('page')),
    `no line for ordinary pages in ${said.join(' | ')}`,
  ).toBe(true)
  expect(
    said.some((line) => line.endsWith('formulaire')),
    'no line for the forms themselves',
  ).toBe(true)
  expect(
    said.filter((line) => line.endsWith('fiche')).length,
    'entries are counted per form, not lumped together',
  ).toBeGreaterThan(1)

  await expect(
    page.locator('.yw-translations thead th[data-yw-lang-label]').first(),
    'the rule that hides other languages has eaten the column that names one',
  ).toBeVisible()
})

test('a cell leads to the editor for that Content in that language', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, PAGE, 'Du contenu en français')

  await page.goto(SCREEN)
  const cell = cellFor(page, PAGE)
  await expect(cell).toHaveAttribute('href', /PageDeCouverture\/edit/)
  await cell.click()

  await expect(page).toHaveURL(/editlang=en/)
  await expect(
    page.locator('.yw-corner-tools a[hreflang="en"]'),
    'the editor is writing the English translation',
  ).toHaveClass(/yw-switcher__option--on/)
})

test('translating a page moves its count on the screen', async ({ page }) => {
  test.slow()
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, PAGE, 'Du contenu en français')

  await page.goto(SCREEN)
  const before = await cellFor(page, PAGE).innerText()
  expect(before.trim()).toMatch(/^0\//)

  await page.goto(`/?${PAGE}/edit&editlang=en`)
  await editorReady(page)
  await replaceEditorTextNewContent(page, 'Some content in English')
  await saveEditor(page)

  await page.goto(SCREEN)
  const after = await cellFor(page, PAGE).innerText()
  expect(
    Number(after.trim().split('/')[0]),
    'the translation just written is not counted',
  ).toBeGreaterThan(0)
})

test('a value list is reachable from the screen, which it was not from anywhere else', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto(`${SCREEN}&group=list`)

  const cell = page.locator('.yw-translations__state[href*="editlang=en"]')
  test.skip((await cell.count()) === 0, 'this wiki has no value list')

  await expect(cell.first()).toHaveAttribute('href', /action=modif_liste/)
  await cell.first().click()

  await expect(
    page.locator('#yw-main form [name="nodes"]'),
    'the list editor, rather than the table it used to bounce back to',
  ).toHaveCount(1)
})

test('the filter narrows the table to what is left to do', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto(`${SCREEN}&language=en&state=full`)

  const rows = page.locator('.yw-translations').last().locator('tbody tr')
  const shown = await rows.count()

  await page.goto(`${SCREEN}&language=en&state=empty`)
  const untranslated = await page
    .locator('.yw-translations')
    .last()
    .locator('tbody tr')
    .count()

  expect(
    untranslated,
    'a wiki nobody has translated has everything left to do',
  ).toBeGreaterThan(shown)
})
