// Deleted content goes to a trash it can be restored from or erased in (end-users 13).
import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { setPageContent } from '../helpers/page'

test.beforeEach(async () => {
  resetEnv()
})

const TAG = 'PageMiseALaCorbeille'

const deleteThroughTheHandler = async (page: Page) => {
  await page.goto(`/?${TAG}/deletepage`)
  await page.locator('input[type="submit"].yw-btn--danger').click()
  await page.waitForURL((url) => !url.search.includes('deletepage'))
}

test('a deleted page waits in the trash and comes back with its content', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, TAG, 'Un texte à retrouver')
  await deleteThroughTheHandler(page)

  await page.goto(`/?${TAG}`)
  await expect(page.locator('.yw-trashed-page')).toContainText(
    'Cette page est dans la corbeille',
  )
  await expect(page.locator('body')).not.toContainText('Un texte à retrouver')

  await page.goto('/?dashboard/trash')
  const row = page.locator('.yw-trash tbody tr', { hasText: TAG })
  await expect(row).toBeVisible()
  await expect(row).toContainText('Page')

  await page.goto(`/?${TAG}`)
  await page.locator('.yw-trashed-page button[value="restore"]').click()
  await expect(page).toHaveURL(new RegExp(`\\?${TAG}$`))
  await expect(page.locator('body')).toContainText('Un texte à retrouver')
  await expect(page.locator('body')).toContainText(`${TAG} est restauré`)
})

test('erasing from the trash asks first, then the page is gone for good', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await setPageContent(page, TAG, 'Un texte à effacer')
  await deleteThroughTheHandler(page)

  await page.goto('/?dashboard/trash')
  const row = page.locator('.yw-trash tbody tr', { hasText: TAG })
  await row.locator('button[value="erase"]').click()
  const dialog = page.locator('dialog.yw-dialog')
  await expect(dialog).toBeVisible()
  await dialog.locator('[data-yw-dialog-confirm]').click()

  await expect(page.locator('body')).toContainText(
    `${TAG} est supprimé définitivement`,
  )
  await expect(
    page.locator('.yw-trash tbody tr', { hasText: TAG }),
  ).toHaveCount(0)
  await page.goto(`/?${TAG}`)
  await expect(page.locator('.yw-trashed-page')).toHaveCount(0)
})
