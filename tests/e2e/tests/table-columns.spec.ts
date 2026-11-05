import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { setPageContent } from '../helpers/page'
import { editorText, openEditorWith } from '../helpers/editor'
import { components } from '../helpers/wysiwyg'
import { login, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'
import { seedResources } from '../helpers/bazar'

const PANEL = '#actions-builder-panel'
const COLUMNS = 'columnfieldsids="bf_titre,bf_type"'

test.beforeEach(async ({ page }) => {
  resetEnv()
  await seedResources(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
})

test('a table draws the columns it is told to, one per field, with labels', async ({
  page,
}) => {
  await setPageContent(
    page,
    'TableColonnes',
    `{{entrylist id="4" template="table" ${COLUMNS}}}`,
  )

  await expect(page.locator('table thead th')).toHaveText([
    'Nom de la ressource',
    'Type de ressource',
  ])
  const row = page.locator('table tbody tr', { hasText: 'Framasoft' })
  await expect(row.locator('td').nth(1)).toHaveText('Partenaire ressource')
  await expect(row.getByRole('link', { name: 'Framasoft' })).not.toHaveClass(
    /modalbox/,
  )
})

test('a table told nothing stays the shared presentation', async ({ page }) => {
  await setPageContent(
    page,
    'TableSansColonnes',
    '{{entrylist id="4" template="table"}}',
  )

  await expect(page.locator('.yw-items--table')).toHaveCount(1)
  await expect(page.locator('table thead th')).toHaveText(['Titre'])
})

test('the facets of a table with columns filter its rows', async ({ page }) => {
  await setPageContent(
    page,
    'TableColonnesFacettes',
    `{{entrylist id="4" template="table" ${COLUMNS} groups="bf_type"}}`,
  )
  const rows = page.locator('table tbody tr:visible')
  await expect(rows).toHaveCount(2)

  await page.getByRole('checkbox', { name: /Partenaire/ }).check()
  await expect(rows).toHaveCount(1)
  await expect(rows).toContainText('Framasoft')
  await expect(page).toHaveURL(/facet/)

  await page.getByRole('checkbox', { name: /Site web/ }).check()
  await expect(rows).toHaveCount(2)
})

test('the rail of a table offers the columns and keeps it a table', async ({
  page,
}) => {
  await openEditorWith(page, '{{entrylist id="4" template="table"}}')
  await components(page).first().click()

  const columns = page
    .locator(`${PANEL} .yw-form-group.form-field`)
    .filter({ hasText: 'Colonnes du tableau' })
  await expect(columns).toBeVisible()
  await columns.locator('.vs__search').click()
  await page
    .locator('.vs__dropdown-option', { hasText: 'Type de ressource' })
    .first()
    .click()

  await expect
    .poll(() => editorText(page), { message: 'the columns are written' })
    .toContain('columnfieldsids="bf_type"')
  expect(await editorText(page)).toContain('template="table"')
})

test('the rail of a card does not offer them', async ({ page }) => {
  await openEditorWith(page, '{{entrylist id="4" template="card"}}')
  await components(page).first().click()

  await expect(
    page
      .locator(`${PANEL} .yw-form-group.form-field`)
      .filter({ hasText: 'Colonnes du tableau' }),
  ).toBeHidden()
})
