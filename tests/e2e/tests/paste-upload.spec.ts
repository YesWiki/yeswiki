// A file pasted or dropped into an editor is uploaded and inserted (end-users 11), and every background upload shows its progress (end-users 25).
import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { editorReady, editorText, useSourceEditor } from '../helpers/editor'

test.beforeEach(async () => {
  resetEnv()
})

const PNG =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='

/** Pastes into the element what the clipboard would hold after a screenshot, or after copying text. */
const paste = (
  page: Page,
  selector: string,
  { image = false, text = '' }: { image?: boolean; text?: string },
) =>
  page.evaluate(
    ({ selector, image, text, png }) => {
      const data = new DataTransfer()
      if (image) {
        const bytes = Uint8Array.from(atob(png), (c) => c.charCodeAt(0))
        data.items.add(new File([bytes], 'capture.png', { type: 'image/png' }))
      }
      if (text) data.setData('text/plain', text)
      const target = document.querySelector(selector) as HTMLElement
      target.focus()
      target.dispatchEvent(
        new ClipboardEvent('paste', {
          clipboardData: data,
          bubbles: true,
          cancelable: true,
        }),
      )
    },
    { selector, image, text, png: PNG },
  )

const WYSIWYG = '.vditor-wysiwyg pre.vditor-reset'
const ACE = '.ace_text-input'

const pageFiles = async (page: Page) =>
  (await (await page.request.get('/?api/files')).json()) as Array<{
    tag: string
    extension: string
  }>

test('a screenshot pasted into the wysiwyg editor is uploaded and attached', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale/edit')
  await editorReady(page)

  await paste(page, WYSIWYG, { image: true })

  await expect
    .poll(() => editorText(page))
    .toMatch(/\{\{attach file="[^"]+" desc="capture\.webp"\}\}/)
  expect(await editorText(page)).not.toContain('data:image')
  expect((await pageFiles(page)).map((file) => file.extension)).toContain(
    'webp',
  )
  await expect(page.locator('.yw-upload-progress')).toHaveCount(0)
})

test('the same in the source editor, and text still pastes as text', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await useSourceEditor(page)
  await page.goto('/?PagePrincipale/edit')
  await editorReady(page)
  await page.waitForSelector(ACE, { state: 'attached', timeout: 15000 })

  await paste(page, ACE, { text: 'collé tel quel' })
  await expect.poll(() => editorText(page)).toContain('collé tel quel')

  await paste(page, ACE, { image: true })
  await expect
    .poll(() => editorText(page))
    .toMatch(
      /collé tel quel.*\{\{attach file="[^"]+" desc="capture\.webp"\}\}/s,
    )
})

test("an entry's markdown field uploads a pasted image before the entry exists", async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  const field = page.locator('[data-yw-upload-page-tag]').filter({
    has: page.locator('textarea.vditor-html'),
  })
  await expect(field.first()).toHaveAttribute('data-yw-upload-token', /.+/)
  await page.waitForSelector(`${WYSIWYG}`, { timeout: 15000 })

  await paste(page, WYSIWYG, { image: true })

  await expect(page.locator('textarea.vditor-html').first()).toHaveValue(
    /<img src="[^"]*api\/files\/[^"]+\/download" alt="capture\.webp"/,
  )
})

test('a refused upload leaves nothing behind and says why', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.route('**/?api/files', (route) =>
    route.request().method() === 'POST'
      ? route.fulfill({
          status: 400,
          contentType: 'application/json',
          body: JSON.stringify({ error: 'Le fichier dépasse 2 Mo' }),
        })
      : route.continue(),
  )
  await page.goto('/?PagePrincipale/edit')
  await editorReady(page)
  const before = await editorText(page)

  await paste(page, WYSIWYG, { image: true })

  await expect(page.locator('.toast-message')).toContainText(
    'Le fichier dépasse 2 Mo',
  )
  await expect(page.locator('.yw-upload-progress')).toHaveCount(0)
  expect(await editorText(page)).toBe(before)
})

test('the file picker shows the upload going, and closing it stops the upload', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  let reached = false
  await page.route('**/?api/files', async (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    reached = true
    await new Promise((resolve) => setTimeout(resolve, 10_000))
    return route.continue().catch(() => {})
  })
  await page.goto('/?PagePrincipale/edit')
  await editorReady(page)
  await page
    .locator('.vditor-toolbar__item > button[data-type="yw-file"]')
    .click()
  const panel = page.locator('#YesWikiFilePickerPanel')
  const aborted = page.waitForEvent('requestfailed', (request) =>
    request.url().includes('api/files'),
  )

  await panel.locator('input[name="upFile"]').setInputFiles({
    name: 'holiday.png',
    mimeType: 'image/png',
    buffer: Buffer.from(PNG, 'base64'),
  })
  await expect(panel.locator('.yw-upload-progress progress')).toBeVisible()
  await expect(panel.locator('.yw-upload-progress__label')).toHaveText(
    'holiday.png',
  )
  await expect.poll(() => reached).toBe(true)
  await panel.locator('[data-yw-file-picker-close]').click()

  await aborted
  await expect(panel.locator('.file-picker-upload-error')).toBeHidden()
})

test('the Files admin counts the files it sends', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const seen: string[] = []
  await page.route(/api\/admin\/files/, async (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    seen.push(
      (await page
        .locator('[data-yw-files-result] .yw-upload-progress__label')
        .textContent()) ?? '',
    )
    return route.continue()
  })
  await page.goto('/?admin/files')
  await page.locator('[data-yw-files-input]').setInputFiles([
    { name: 'un.txt', mimeType: 'text/plain', buffer: Buffer.from('un') },
    { name: 'deux.txt', mimeType: 'text/plain', buffer: Buffer.from('deux') },
  ])

  await expect(page.locator('[data-yw-files-result]')).toHaveClass(
    /yw-files__result--ok/,
  )
  expect(seen).toEqual([
    'Fichier 1 sur 2 : un.txt',
    'Fichier 2 sur 2 : deux.txt',
  ])
})

test('an entry with a large attachment shows its upload, then lands where a submit lands', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=7')
  let sentBytes = 0
  let release = () => {}
  const released = new Promise<void>((resolve) => {
    release = resolve
  })
  await page.route(/saisir_fiche/, async (route) => {
    if (route.request().method() !== 'POST') return route.continue()
    sentBytes = route.request().postDataBuffer()?.length ?? 0
    await released
    return route.fulfill({
      status: 303,
      headers: { Location: '/?PagePrincipale' },
    })
  })

  await page.locator('input[type="file"].yw-file-upload').setInputFiles({
    name: 'rapport.pdf',
    mimeType: 'application/pdf',
    buffer: Buffer.alloc(5 * 1024 * 1024, 0x25),
  })
  await page.locator('.bazar-form [name="valider"]').click()

  await expect(page.locator('.bazar-form .yw-upload-progress')).toBeVisible()
  await expect.poll(() => sentBytes).toBeGreaterThan(5 * 1024 * 1024)
  release()
  await page.waitForURL(/\?PagePrincipale$/, { timeout: 20_000 })
})

test('a light entry form submits as it always did', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=7')
  const types: string[] = []
  page.on('request', (request) => {
    if (request.method() === 'POST') types.push(request.resourceType())
  })

  await page.locator('input[type="file"].yw-file-upload').setInputFiles({
    name: 'note.txt',
    mimeType: 'text/plain',
    buffer: Buffer.from('une note'),
  })
  await page.locator('.bazar-form [name="valider"]').click()
  await page.waitForLoadState('load')

  expect(types).toContain('document')
  expect(types).not.toContain('xhr')
})
