import { expect, test } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv({ onboarded: false })
})

test('a visitor is told the wiki is being set up', async ({ page }) => {
  await page.goto('/')

  await expect(page.locator('.yw-onboarding h1')).toContainText(
    'en cours de préparation',
  )
  await expect(
    page.locator('.yw-onboarding input[type="checkbox"]'),
  ).toHaveCount(0)
})

test('an admin picks a Starter and gets its pages, its menu entry and a home page', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/')

  const starters = page.locator('.yw-onboarding__starter')
  await expect(starters).toHaveCount(4)
  await starters.filter({ hasText: 'Agenda' }).locator('input').check()
  await page
    .getByRole('button', { name: 'Créer le wiki avec ces choix' })
    .click()

  await expect(page).toHaveURL(/PagePrincipale$/)
  await expect(page.locator('.yw-onboarding')).toHaveCount(0)
  await expect(page.locator('h1')).toContainText('MyTestWiki')
  await expect(page.locator('#yw-topnav')).toContainText('Agenda')
  await expect(page.locator('#yw-topnav')).not.toContainText('Annuaire')

  await page.goto('/?VueAgenda')
  await expect(page.locator('nav.yw-menu')).toContainText("Voir l'agenda")
})

test('an admin can start from an empty wiki', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/')

  await page.getByRole('button', { name: "Partir d'un wiki vide" }).click()

  await expect(page.locator('h1')).toContainText('MyTestWiki')
  await expect(page.locator('#yw-topnav')).toContainText('Bac à sable')
  await expect(page.locator('#yw-topnav')).not.toContainText('Agenda')
})
