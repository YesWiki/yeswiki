import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { createEntry } from '../helpers/bazar'
import { attachConsole, watchConsole } from '../helpers/console'

test.beforeEach(async () => {
  resetEnv()
})

/** The Agenda's calendar, on FullCalendar 7: an event shows on its day, in French, weeks from Monday, and opens its entry (ticket 29). */
test('an event shows in the calendar and opens its entry', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  const today = new Date()
  const day = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`

  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await createEntry(page, 2, {
    bf_titre: 'Atelier du calendrier',
    bf_date_debut_evenement: day,
    bf_date_fin_evenement: day,
  })

  await page.goto('/?agenda&display=calendar')

  const calendar = page.locator('.yw-calendar')
  await expect(calendar).toBeVisible()
  await expect(calendar.locator('.yw-cal-button--today')).toHaveText(/Aujourd/)
  await expect(calendar.locator('[role="columnheader"]').first()).toContainText(
    /lun/i,
  )

  const event = calendar.locator('.yw-cal-event', {
    hasText: 'Atelier du calendrier',
  })
  await expect(event).toBeVisible()

  await calendar.locator('.yw-cal-button--listYear').click()
  await expect(
    calendar.locator('.yw-cal-event', { hasText: 'Atelier du calendrier' }),
  ).toBeVisible()

  await calendar
    .locator('.yw-cal-event', { hasText: 'Atelier du calendrier' })
    .click()
  await expect(page.locator('.yw-modal--open')).toBeVisible()

  await attachConsole(watcher, testInfo)
  expect(watcher.errors()).toEqual([])
})
