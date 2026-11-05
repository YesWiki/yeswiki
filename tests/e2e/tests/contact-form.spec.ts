import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { openEditorWith, useSourceEditor } from '../helpers/editor'

test.beforeEach(async () => {
  resetEnv()
})

test('every field of the contact form lines up with the send button', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await useSourceEditor(page)
  await openEditorWith(page, '{{contact mail="contact@example.com"}}')
  await page.getByRole('button', { name: 'Enregistrer' }).first().click()
  await page.waitForLoadState()
  await page.goto('/?PagePrincipale')

  const form = page.locator('form.ajax-mail-form')
  const send = await form.locator('button[type="submit"]').boundingBox()
  const rows = form.locator(
    '.input-group, input.contact-subject, textarea.contact-message',
  )
  await expect(rows).toHaveCount(4)
  for (const row of await rows.all()) {
    const box = await row.boundingBox()
    expect(Math.round(box.x)).toBe(Math.round(send.x))
    expect(Math.round(box.width)).toBe(Math.round(send.width))
  }
})
