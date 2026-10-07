import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { createPageWithContent } from '../helpers/page'
import { waitPastBotGuardMinimumAge } from '../helpers/botGuard'

test.beforeEach(async () => {
  resetEnv()
})

test('an anonymous visitor sends the contact form twice without reloading', async ({
  page,
}) => {
  await createPageWithContent(
    page,
    'BotGuardContact',
    '{{contact mail="nobody@example.org"}}',
  )
  await page.goto('/?BotGuardContact')
  const form = page.locator('form.ajax-mail-form')

  for (const subject of ['Premier message', 'Second message']) {
    await form.locator('input[name="name"]').fill('Une personne')
    await form.locator('input[name="email"]').fill('personne@example.org')
    await form.locator('input[name="subject"]').fill(subject)
    await form.locator('textarea[name="message"]').fill('Bonjour')
    await waitPastBotGuardMinimumAge(page)
    await form.locator('.mail-submit').click()
    await expect(form.locator('.yw-alert')).toBeVisible()
    await expect(form.locator('.yw-alert')).not.toContainText(
      "L'envoi n'a pas pu être vérifié",
    )
  }
})

test('the honeypot stays empty and out of reach', async ({ page }) => {
  await createPageWithContent(
    page,
    'BotGuardHoneypot',
    '{{contact mail="nobody@example.org"}}',
  )
  await page.goto('/?BotGuardHoneypot')
  const honeypot = page.locator('.yw-bot-guard input')

  await expect(honeypot).toHaveValue('')
  await expect(honeypot).toHaveAttribute('tabindex', '-1')
  await expect(page.locator('.yw-bot-guard')).toHaveAttribute(
    'aria-hidden',
    'true',
  )
  await expect(honeypot).toBeHidden()
})
