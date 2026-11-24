import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { watchConsole } from '../helpers/console'
import { login, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'

/** The value list editor is drawn by the server: values are added, nested, coloured and moved in the page, and sent as one JSON field. */

const NEW_LIST = '/?dashboard/lists&view=listes&action=saisir_liste'

const values = (page: Page) =>
  page.locator(
    '[data-list-root] > [data-list-node] > .list-node [data-list-label]',
  )

const labelsOf = (page: Page) =>
  values(page).evaluateAll((inputs) =>
    inputs.map((input) => (input as HTMLInputElement).value),
  )

const addValue = async (
  page: Page,
  scope: Page | ReturnType<Page['locator']>,
  label: string,
) => {
  const input = scope.locator('[data-list-new]').last()
  await input.fill(label)
  await input.press('Enter')
}

const editorOf = async (page: Page, title: string) => {
  await page.goto('/?dashboard/lists&view=listes')
  await page
    .locator('tr', { hasText: title })
    .locator('a[href*="modif_liste"]')
    .first()
    .click()
  await expect(page.locator('[data-list-form]')).toBeVisible()
}

test.beforeEach(async ({ page }) => {
  resetEnv()
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
})

test('a list is built, nested, coloured and reordered, then comes back as saved', async ({
  page,
}) => {
  const watcher = watchConsole(page)
  await page.goto(NEW_LIST)
  await page.getByLabel('Nom de la liste').fill('Les récoltes')

  const root = page.locator('.list-node-children.root > .list-new-node')
  await addValue(page, root, 'Fruits rouges')
  await addValue(page, root, 'Légumes')
  await expect.poll(() => labelsOf(page)).toEqual(['Fruits rouges', 'Légumes'])
  await expect(page.locator('[data-list-id]').first()).toHaveValue(
    'fruits_rouges',
  )

  const fruits = page.locator('[data-list-root] > [data-list-node]').first()
  await fruits.locator('[data-list-toggle]').first().click()
  await addValue(page, fruits.locator('[data-list-children]').first(), 'Fraise')
  await expect(
    fruits.locator('[data-list-children] [data-list-label]'),
  ).toHaveValue('Fraise')

  const legumes = page.locator('[data-list-root] > [data-list-node]').nth(1)
  await legumes.locator('[data-list-color]').first().fill('#2e7d32')
  await expect(legumes.locator('[data-list-color-clear]').first()).toBeVisible()

  expect(
    await page
      .locator('[data-list-root]')
      .evaluate((list) =>
        Object.keys(list).some((key) => key.startsWith('Sortable')),
      ),
    'the list can be reordered by dragging',
  ).toBe(true)
  const from = await legumes.locator('.input-group-addon').nth(1).boundingBox()
  const to = await fruits.locator('.list-node').first().boundingBox()
  await page.mouse.move(from!.x + from!.width / 2, from!.y + from!.height / 2)
  await page.mouse.down()
  await page.mouse.move(to!.x + 40, to!.y + 4, { steps: 12 })
  await page.mouse.move(to!.x + 40, to!.y + 2, { steps: 4 })
  await page.mouse.up()
  await expect.poll(() => labelsOf(page)).toEqual(['Légumes', 'Fruits rouges'])

  await page.getByRole('button', { name: 'Enregistrer' }).click()
  await page.waitForURL((url) => !url.search.includes('saisir_liste'))
  await editorOf(page, 'Les récoltes')
  await expect.poll(() => labelsOf(page)).toEqual(['Légumes', 'Fruits rouges'])
  const saved = page.locator('[data-list-root] > [data-list-node]')
  await expect(saved.first().locator('[data-list-color]').first()).toHaveValue(
    '#2e7d32',
  )
  await expect(
    saved.nth(1).locator('[data-list-children] [data-list-label]'),
  ).toHaveValue('Fraise')
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

test('a key used twice keeps the list from being sent', async ({ page }) => {
  await page.goto(NEW_LIST)
  await page.getByLabel('Nom de la liste').fill('Doublons')
  const root = page.locator('.list-node-children.root > .list-new-node')
  await addValue(page, root, 'Pomme')
  await addValue(page, root, 'Pomme')

  await page.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(page.locator('.alert-danger')).toContainText('pomme')
  await expect(page).toHaveURL(/saisir_liste/)
})
