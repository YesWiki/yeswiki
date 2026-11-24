// A page sets its own menu, footer, CSS and OpenGraph from the layout panel under its editor (end-users 45).
import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { setPageContent } from '../helpers/page'
import { editorReady, saveEditor } from '../helpers/editor'

test.beforeEach(async () => {
  resetEnv()
})

const LANDING = 'PageAtterrissage'

const openPanel = async (page: Page) => {
  await page.goto(`/?${LANDING}/edit`)
  await editorReady(page)
  const panel = page.locator('[data-yw-page-layout]')
  await panel.locator('summary').click()
  return panel
}

test('a landing page drops the menu and the footer, and the next page has them again', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(
    page,
    LANDING,
    '# Bienvenue\n\n[Accueil](PagePrincipale)',
  )

  const panel = await openPanel(page)
  await panel.getByLabel('Menu', { exact: true }).selectOption('none')
  await panel.locator('select[name="PageFooter_mode"]').selectOption('none')
  await saveEditor(page)

  await expect(page.locator('#yw-topnav')).toHaveCount(0)
  await expect(page.locator('#yw-footer')).toHaveCount(0)
  await expect(page.locator('html')).toHaveAttribute('data-yw-navbar', 'none')

  await page.locator('#yw-main').getByRole('link', { name: 'Accueil' }).click()
  await expect(page.locator('#yw-topnav')).toBeVisible()
  await expect(page.locator('#yw-footer')).toBeVisible()
  await expect(page.locator('html')).not.toHaveAttribute(
    'data-yw-navbar',
    'none',
  )
})

test('the custom CSS and the OpenGraph title apply to that page only', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, LANDING, '# Bienvenue')

  const panel = await openPanel(page)
  await panel
    .locator('textarea[name="custom_css"]')
    .fill('#yw-main { outline: 3px solid rgb(255, 0, 0); }')
  await panel.getByLabel('Titre', { exact: true }).fill('Venez nous voir')
  await saveEditor(page)

  await expect(page.locator('#yw-main')).toHaveCSS(
    'outline-color',
    'rgb(255, 0, 0)',
  )
  await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
    'content',
    'Venez nous voir',
  )

  await page.goto('/?PagePrincipale')
  await expect(page.locator('#yw-main')).not.toHaveCSS(
    'outline-color',
    'rgb(255, 0, 0)',
  )
})

test('a CSS that could close its style tag is refused and nothing is saved', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, LANDING, '# Bienvenue')

  const panel = await openPanel(page)
  await panel
    .locator('textarea[name="custom_css"]')
    .fill('</style><script>alert(1)</script>')
  await saveEditor(page)

  await expect(page.locator('.alert-danger')).toContainText('CSS personnalisé')
  await expect(page.locator('textarea[name="custom_css"]')).toHaveValue(
    '</style><script>alert(1)</script>',
  )
})
