import { Page } from '@playwright/test'
import { errorShouldBe } from './alert'

export const setPagePermission = async (
  page: Page,
  tag: string,
  newReadPermissions: string = null,
  newWritePermissions: string = null,
) => {
  await page.goto(`/?${tag}/acls`)
  if (newReadPermissions !== null) {
    await page.locator('[name="read_acl"]').fill(newReadPermissions)
  }

  if (newWritePermissions !== null) {
    await page.locator('[name="write_acl"]').fill(newWritePermissions)
  }

  await page.getByRole('button', { name: 'Enregistrer' }).click()
  await errorShouldBe(page, "Droits d'accès mis à jour")
}
