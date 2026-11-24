import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { watchConsole } from '../helpers/console'
import { login, ADMIN_USERNAME, ADMIN_PASSWORD } from '../helpers/login'
import { createEntry } from '../helpers/bazar'

/** The recurrence of an event is edited without Vue: the chosen repetition shows its own controls, and what is saved comes back when the entry is edited again. */

const START = '2030-01-07'

const storedRule = async (page: Page, tag: string) => {
  const response = await page.request.get('/?api/forms/2/entries/json')
  const entries = (await response.json()) as Record<string, unknown>[]
  return entries.find((entry) => entry.tag === tag)
    ?.bf_date_fin_evenement_data as Record<string, unknown> | undefined
}

const save = async (page: Page) => {
  await page.getByRole('button', { name: 'Enregistrer' }).click()
  await page.waitForLoadState('networkidle')
}

test.beforeEach(async ({ page }) => {
  resetEnv()
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
})

test('a weekly event keeps its days, its end and its exceptions', async ({
  page,
}) => {
  const watcher = watchConsole(page)
  const tag = await createEntry(page, 2, {
    bf_titre: 'Atelier hebdo',
    bf_date_debut_evenement: START,
    bf_date_fin_evenement: START,
  })
  await page.goto(`/?${tag}/edit`)

  const recurrence = page.locator('[data-recurrence]')
  const repetition = recurrence.locator('[data-recurrence-repetition]')
  await expect(recurrence.locator('[data-recurrence-rule]')).toBeHidden()

  await repetition.selectOption({ label: 'Toutes les semaines' })
  await expect(recurrence.locator('[data-recurrence-rule]')).toBeVisible()
  await expect(
    recurrence.getByRole('checkbox', { name: 'Lundi' }),
    'the day of the start date is ticked',
  ).toBeChecked()
  await recurrence.getByRole('checkbox', { name: 'Mercredi' }).check()
  await recurrence.getByLabel("Jusqu'au :").fill('2030-02-28')
  await expect(recurrence.locator('[data-recurrence-month]')).toBeHidden()

  const choice = recurrence.locator('[data-recurrence-except-choice]')
  await expect(choice.locator('option[value="2030-01-09"]')).toHaveCount(1)
  await choice.selectOption('2030-01-09')
  await expect(recurrence.locator('[data-recurrence-except]')).toContainText(
    '2030-01-09',
  )
  await expect(choice.locator('option[value="2030-01-09"]')).toHaveCount(0)

  await save(page)
  const rule = await storedRule(page, tag)
  expect(rule).toMatchObject({
    isRecurrent: '1',
    repetition: 'w',
    step: '1',
    limitdate: '2030-02-28',
    days: ['mon', 'wed'],
    except: ['2030-01-09'],
  })
  expect(rule).not.toHaveProperty('other')

  await page.goto(`/?${tag}/edit`)
  await expect(repetition).toHaveValue('w')
  await expect(
    recurrence.getByRole('checkbox', { name: 'Mercredi' }),
  ).toBeChecked()
  await expect(recurrence.locator('[data-recurrence-except]')).toContainText(
    '2030-01-09',
  )
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

test('a monthly event on a numbered day, then no repetition at all', async ({
  page,
}) => {
  const tag = await createEntry(page, 2, {
    bf_titre: 'Réunion mensuelle',
    bf_date_debut_evenement: START,
    bf_date_fin_evenement: START,
  })
  await page.goto(`/?${tag}/edit`)
  const recurrence = page.locator('[data-recurrence]')
  const repetition = recurrence.locator('[data-recurrence-repetition]')

  await repetition.selectOption({ label: 'Voir les options supplémentaires' })
  await repetition.selectOption({ label: 'Toutes les X semaines' })
  await expect(recurrence.locator('.recurrence__step:visible')).toHaveValue('2')

  await repetition.selectOption({ label: 'Tous les mois' })
  await recurrence
    .locator('[data-recurrence-in-month]')
    .selectOption('nthOfMonth')
  await expect(recurrence.locator('[data-recurrence-weekday]')).toBeHidden()
  await recurrence.locator('[data-recurrence-nth]').selectOption('15')
  await recurrence.getByLabel("Jusqu'au :").fill('2030-06-30')
  await save(page)
  expect(await storedRule(page, tag)).toMatchObject({
    repetition: 'm',
    whenInMonth: 'nthOfMonth',
    nth: '15',
  })

  await page.goto(`/?${tag}/edit`)
  await expect(recurrence.locator('[data-recurrence-nth]')).toHaveValue('15')
  await repetition.selectOption({ label: 'Pas de répétition' })
  await save(page)
  expect(await storedRule(page, tag)).toBeUndefined()
})
