import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { watchConsole } from '../helpers/console'
import { login, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'

/** The backups screen is drawn by the server: a backup is followed in place until it lands in the list, and leaves it when deleted. */

test.beforeEach(async ({ page }) => {
  resetEnv()
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
})

test('a database backup is followed until it is listed, then deleted', async ({
  page,
}) => {
  test.slow()
  const watcher = watchConsole(page)
  await page.goto('/?admin/backups')
  const archive = page.locator('#backups-archive')
  const list = page.locator('#backups-list')
  await expect(list).toContainText("Pas d'archives de backup trouvées")
  await page.evaluate(() => {
    ;(window as unknown as { __stillHere: boolean }).__stillHere = true
  })

  await archive
    .getByRole('radio', { name: 'Seulement la base de données' })
    .check()
  await archive.getByRole('button', { name: 'Démarrer la sauvegarde' }).click()
  const confirmation = archive.getByRole('checkbox')
  if (await confirmation.isVisible().catch(() => false)) {
    await confirmation.check()
    await archive
      .getByRole('button', { name: 'Démarrer la sauvegarde' })
      .click()
  }

  await expect(archive).toContainText('Sauvegarde terminée', { timeout: 90000 })
  const row = list.getByRole('checkbox', { name: /_archive_only_db\.zip$/ })
  await expect(row).toHaveCount(1)
  expect(
    await page.evaluate(
      () => (window as unknown as { __stillHere?: boolean }).__stillHere,
    ),
  ).toBe(true)

  await row.check()
  await list.getByRole('button', { name: 'Supprimer la sélection' }).click()
  await page.locator('[data-yw-dialog-confirm]').click()
  await expect(list).toContainText("Pas d'archives de backup trouvées")
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

test('fetching from a wiki that does not answer says why', async ({ page }) => {
  await page.goto('/?admin/backups')
  const remote = page.locator('#backups-remote')
  await remote.getByLabel('Adresse du wiki distant').fill('http://127.0.0.1:9/')
  await remote
    .getByLabel("Nom d'un administrateur du wiki distant")
    .fill('admin')
  await remote.getByLabel('Mot de passe', { exact: true }).fill('secret')
  await remote.getByRole('button', { name: 'Récupérer' }).click()
  await expect(remote.locator('.alert-danger')).toBeVisible({ timeout: 30000 })
  await expect(remote.getByLabel('Adresse du wiki distant')).toHaveValue(
    'http://127.0.0.1:9/',
  )
})
