import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async() => {
  resetEnv()
})

/**
 * The editor's file picker, in a browser.
 *
 * Everything here is structurally invisible to phpunit: the list of already-uploaded
 * files is fetched by the modal's own script, so a URL that does not reach the route
 * looks exactly like "this wiki has no files" -- which is what it looked like, for as
 * long as the fetch asked for `/?/api/files` and got the home page back. A test that
 * only asserts the route's JSON would still have passed.
 */

// 1x1 transparent PNG
const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
  'base64'
)

const openPicker = async(page: Page) => {
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(() => window['aceditor-body']?.editor !== undefined, null, {timeout: 15000})
  await page.locator('.aceditor-btn-file').first().click()
  await expect(page.locator('#YesWikiFilePickerModal')).toBeVisible()
}

const uploadThroughPicker = async(page: Page, name: string, mimeType: string, buffer: Buffer) => {
  await page.locator('[data-yw-file-picker-tab="upload"]').click()
  await page.locator('#YesWikiFilePickerModal input[name="upFile"]').setInputFiles({name, mimeType, buffer})
  await page.locator('#YesWikiFilePickerModal .btn-do-upload').click()
  // the upload lands the file in the list and selects it
  await expect(page.locator('[data-yw-file-picker-selected-name]')).toHaveText(name)
}

const resultNames = (page: Page) => page.locator('#YesWikiFilePickerModal .file-picker__name')

test('the picker lists the files already uploaded, and filters them', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await uploadThroughPicker(page, 'minutes.txt', 'text/plain', Buffer.from('some notes'))

  // both are in the list without reopening anything -- the regression this covers is an
  // empty list, so the count matters more than the order
  await expect(resultNames(page)).toHaveText(['minutes.txt', 'holiday.png'])

  // each family says how many files it holds
  const families = page.locator('#YesWikiFilePickerModal .file-picker__family')
  await expect(families).toHaveText(['Tous (2)', 'Images (1)', 'Documents (1)'])

  await families.filter({hasText: 'Images'}).click()
  await expect(resultNames(page)).toHaveText(['holiday.png'])

  // the extension list is built from what the family left, and .txt is not in it
  const extensions = page.locator('#YesWikiFilePickerModal [data-yw-file-picker-extensions] option')
  await expect(extensions).toHaveText(['Toutes les extensions', '.png'])

  await families.filter({hasText: 'Tous'}).click()
  // searching the extension finds a kind of file, not just a name
  await page.locator('#YesWikiFilePickerModal input[name="search"]').fill('txt')
  await expect(resultNames(page)).toHaveText(['minutes.txt'])

  await page.locator('#YesWikiFilePickerModal input[name="search"]').fill('nothing matches this')
  await expect(resultNames(page)).toHaveCount(0)
  await expect(page.locator('[data-yw-file-picker-empty]')).toBeVisible()
})

test('picking a file inserts an attach action for it', async({page}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openPicker(page)

  await uploadThroughPicker(page, 'holiday.png', 'image/png', PNG)
  await page.locator('#YesWikiFilePickerModal .file-picker__result').first().click()
  await page.locator('#YesWikiFilePickerModal .btn-insert-upload').click()

  const content = await page.evaluate(() => window['aceditor-body'].editor.getValue())
  expect(content).toMatch(/\{\{attach file="[^"]+" desc="holiday\.png"/)
})
