import { test, expect, APIRequestContext } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { openEditorWith, useSourceEditor } from '../helpers/editor'
import { waitPastBotGuardMinimumAge } from '../helpers/botGuard'

const MAILPIT = process.env.YESWIKI_TEST_MAILPIT_URL

test.skip(!MAILPIT, 'no Mailpit to read the mail back from')

test.beforeEach(async () => {
  resetEnv()
})

/** How many mails Mailpit holds with that subject. */
const caught = async (request: APIRequestContext, subject: string) => {
  const response = await request.get(`${MAILPIT}/api/v1/search`, {
    params: { query: `subject:"${subject}"` },
  })
  expect(response.ok()).toBe(true)

  return (await response.json()).messages_count as number
}

test('a visitor writes through the contact form and the relay gets the mail once', async ({
  page,
  browser,
  request,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await useSourceEditor(page)
  await openEditorWith(page, '{{contact mail="contact@example.com"}}')
  await page.getByRole('button', { name: 'Enregistrer' }).first().click()
  await page.waitForLoadState()

  const subject = `Une question sur le wiki ${Date.now()}`
  const visitor = await (await browser.newContext()).newPage()
  await visitor.goto('/?PagePrincipale')
  const form = visitor.locator('form.ajax-mail-form')
  await form.locator('input[name="name"]').fill('Camille Martin')
  await form.locator('input[name="email"]').fill('camille@example.org')
  await form.locator('input[name="subject"]').fill(subject)
  await form
    .locator('textarea[name="message"]')
    .fill(
      "Bonjour, je voudrais savoir comment proposer une page sur l'atelier de jeudi.",
    )
  await waitPastBotGuardMinimumAge(visitor)
  await form.locator('.mail-submit').click()

  await expect(form.locator('.yw-alert--success')).toBeVisible()
  await expect.poll(() => caught(request, subject)).toBe(1)
  await visitor.waitForTimeout(3000)
  expect(
    await caught(request, subject),
    'sent once, not once per replica',
  ).toBe(1)
})
