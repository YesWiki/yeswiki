// A form is sent once (end-users 14), confirmations use the wiki's dialog (31), and an error page speaks the wiki's language (15).
import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { waitPastBotGuardMinimumAge } from '../helpers/botGuard'

test.beforeEach(async () => {
  resetEnv()
})

const entryTitles = async (page: Page): Promise<string[]> => {
  const response = await page.request.get('/?api/forms/1/entries/json')
  const entries = (await response.json()) as Record<string, { bf_nom?: string }>
  return Object.values(entries).map((entry) => entry.bf_nom ?? '')
}

test('a double click on save creates one entry', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=1')
  await page.locator('#bf_nom').fill('Doublon')
  await page.locator('#bf_prenom').fill('Pas')
  await page.locator('#bf_mail').fill('pas.doublon@example.com')
  await waitPastBotGuardMinimumAge(page)

  await page.locator('.bazar-form [name="valider"]').dblclick()
  await page.waitForURL((url) => !url.search.includes('saisir_fiche'))

  expect(
    (await entryTitles(page)).filter((name) => name === 'Doublon'),
  ).toHaveLength(1)
})

test('a submit the dialog cancelled leaves the form sendable, and an accepted one is sent with its button', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale')
  await page.evaluate(() => {
    const form = document.createElement('form')
    form.method = 'post'
    form.action = window.location.href
    form.id = 'yw-test-form'
    form.dataset.ywConfirm = 'Vraiment ?'
    form.innerHTML =
      '<button type="submit" name="go" value="1">Envoyer</button>'
    document.body.append(form)
  })
  const dialog = page.locator('dialog.yw-dialog')

  await page.locator('#yw-test-form button').click()
  await expect(dialog).toBeVisible()
  await expect(dialog.locator('.yw-dialog__message')).toHaveText('Vraiment ?')
  await expect(dialog.locator('[data-yw-dialog-cancel]')).toHaveText('Annuler')
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  expect(
    await page.evaluate(
      () =>
        document.querySelector<HTMLFormElement>('#yw-test-form')!.dataset
          .ywSubmitting,
    ),
  ).toBeUndefined()

  await page.locator('#yw-test-form button').click()
  const sent = page.waitForRequest((request) => request.method() === 'POST')
  await dialog.locator('[data-yw-dialog-confirm]').click()
  expect((await sent).postData()).toContain('go')
})

test('going back to the editor after a save gives the save button back', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(() => window['ywEditors']?.body !== undefined)
  await waitPastBotGuardMinimumAge(page)
  await page
    .locator('#ACEditor .vditor-wiki-save')
    .evaluate((button) =>
      (button as HTMLButtonElement).form!.requestSubmit(
        button as HTMLButtonElement,
      ),
    )
  await page.waitForURL((url) => !url.search.includes('/edit'))

  await page.goBack()
  await page.waitForFunction(() => window['ywEditors']?.body !== undefined)
  await expect(page.locator('#ACEditor .vditor-wiki-save')).not.toHaveClass(
    /yw-btn--busy/,
  )
  expect(
    await page.evaluate(
      () =>
        (document.querySelector('#ACEditor') as HTMLFormElement).dataset
          .ywSubmitting,
    ),
  ).toBeUndefined()
})

test('an htmx post that fails gives its button back', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.route('**/yw-test-failing-post', (route) =>
    route.fulfill({
      status: 500,
      contentType: 'application/json',
      body: '{"error":"Échec voulu"}',
    }),
  )
  await page.goto('/?PagePrincipale')
  await page.evaluate(() => {
    const button = document.createElement('button')
    button.id = 'yw-test-button'
    button.setAttribute('hx-post', '/yw-test-failing-post')
    button.setAttribute('hx-swap', 'none')
    button.textContent = 'Essayer'
    document.body.append(button)
    window['htmx'].process(button)
  })

  await page.locator('#yw-test-button').click()
  await expect(page.locator('.toast-message')).toContainText('Échec voulu')
  await expect(page.locator('#yw-test-button')).not.toHaveAttribute(
    'aria-disabled',
    'true',
  )
  await expect(page.locator('#yw-test-button')).not.toHaveClass(/yw-btn--busy/)
})

test('deleting a file asks through the wiki dialog, and Cancel keeps it', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/files')
  await page.locator('[data-yw-files-input]').setInputFiles({
    name: 'garder.txt',
    mimeType: 'text/plain',
    buffer: Buffer.from('garder'),
  })
  const card = page.locator('.yw-file', { hasText: 'garder.txt' })
  await expect(card).toBeVisible()
  const dialog = page.locator('dialog.yw-dialog')

  await card.hover()
  await card.locator('[data-yw-file-delete]').click()
  await expect(dialog).toBeVisible()
  await expect(dialog.locator('[data-yw-dialog-confirm]')).toHaveClass(
    /yw-btn--danger/,
  )
  await expect(dialog.locator('[data-yw-dialog-cancel]')).toBeFocused()
  await dialog.locator('[data-yw-dialog-cancel]').click()
  await expect(card).toBeVisible()

  await card.hover()
  await card.locator('[data-yw-file-delete]').click()
  await dialog.locator('[data-yw-dialog-confirm]').click()
  await expect(card).toHaveCount(0)
})

test('an unknown screen answers with a page in the layout and a way home', async ({
  page,
}) => {
  const response = await page.goto('/?dashboard/nothing')

  expect(response?.status()).toBe(404)
  await expect(page.locator('#yw-error-page-title')).toHaveText(
    'Page introuvable',
  )
  await expect(page.locator('#yw-topnav')).toBeVisible()
  await page.locator('.yw-error-page__search input[name="q"]').fill('accueil')
  await page.locator('.yw-error-page__search button[type="submit"]').click()
  await expect(page).toHaveURL(/search/)
  const api = await page.request.get('/?api/nothing')
  expect(api.status()).toBe(404)
  expect(await api.json()).toHaveProperty('error')
})
