import { expect, test } from '@playwright/test'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { editorReady, editorText } from '../helpers/editor'
import { surface, toolbarButton } from '../helpers/wysiwyg'

/** What was typed a moment ago, before the wysiwyg's debounced input has fired. */
const typeAList = async (page) => {
  await page.goto('/?BacASable/edit')
  await editorReady(page)
  await page.evaluate(() => window['ywEditors'].body.setValue('intro'))
  await surface(page).locator('p', { hasText: 'intro' }).click()
  await page.keyboard.press('End')
  await page.keyboard.press('Enter')
  await page.keyboard.type('- un')
  await page.keyboard.press('Enter')
  await page.keyboard.type('deux')
  await page.keyboard.press('Enter')
  await page.keyboard.press('Enter')
  await page.keyboard.type('- [ ] tâche')
}

test('switching to the source editor right after typing keeps the lists', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await typeAList(page)

  await toolbarButton(page, 'yw-switch-editor').click()
  await page.waitForSelector('.ace_editor')
  await editorReady(page)

  const text = await editorText(page)
  expect(text).toContain('- un\n- deux')
  expect(text).toContain('- [ ] tâche')
})

test('saving right after typing keeps what was typed', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await typeAList(page)

  const saved = page.waitForResponse(
    (response) => response.request().method() === 'POST',
  )
  await toolbarButton(page, 'yw-save').click()
  await saved
  await page.waitForLoadState()

  await page.goto('/?BacASable/edit')
  await editorReady(page)
  expect(await editorText(page)).toContain('- [ ] tâche')
})
