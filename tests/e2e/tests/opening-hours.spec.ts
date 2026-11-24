import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { watchConsole } from '../helpers/console'
import { login, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'
import { createEntry, createForm } from '../helpers/bazar'

/** Opening hours are typed as rows of days and times, and the entry shows today's hours with the week behind them. */

test.beforeEach(async ({ page }) => {
  resetEnv()
  createForm('90', 'Lieux', [
    { type: 'texte', name: 'bf_titre', label: 'Nom' },
    { type: 'openinghours', name: 'bf_horaires', label: 'Horaires' },
  ])
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
})

test('hours typed in the editor are what the entry shows', async ({ page }) => {
  const watcher = watchConsole(page)
  const tag = await createEntry(page, 90, {
    bf_titre: 'La boulangerie',
    bf_horaires: 'Mo,Tu,We,Th,Fr,Sa,Su 09:00-12:00',
  })
  await page.goto(`/?${tag}/edit`)

  const editor = page.locator('[data-opening-hours-editor]')
  await expect(editor.locator('[data-opening-hours-interval]')).toHaveCount(1)
  await expect(editor.locator('[data-opening-hours-start]')).toHaveValue(
    '09:00',
  )

  await editor.getByRole('button', { name: 'Ajouter un horaire' }).click()
  await editor.locator('[data-opening-hours-start]').nth(1).fill('14:00')
  await editor.locator('[data-opening-hours-end]').nth(1).fill('18:00')
  await editor.getByRole('button', { name: 'Ajouter un nouveau jour' }).click()
  await editor.getByRole('button', { name: 'Supprimer le jour' }).nth(1).click()
  await expect(editor.locator('[data-opening-hours-value]')).toHaveValue(
    'Mo,Tu,We,Th,Fr,Sa,Su 09:00-12:00,14:00-18:00',
  )

  await page.getByRole('button', { name: 'Enregistrer' }).click()
  await page.goto(`/?${tag}`)
  const display = page.locator('.opening-hours-display')
  await expect(display.locator('summary')).toContainText('09:00')
  await expect(display.locator('summary')).toContainText('18:00')
  await display.locator('summary').click()
  await expect(display.locator('tbody tr')).toHaveCount(7)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})
