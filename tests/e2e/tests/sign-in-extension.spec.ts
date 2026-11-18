import { execFileSync } from 'child_process'
import { expect, Page, test } from '@playwright/test'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { instanceDir } from '../helpers/instance'
import {
  forgetAccounts,
  journal,
  setProviders,
  signInAtWikiForm,
} from '../helpers/sso'

/** A sign-in provider type added by the helloworld extension. */
const TOAST = '[role="alert"], .toast-message .alert'
const TYPE = 'hello-password'
const SHARED_PASSWORD = 'hello-shared-secret'
const PERSON = 'bob'
const PERSON_EMAIL = `${PERSON}@example.invalid`

const yeswicli = (...args: string[]): string =>
  execFileSync('./yeswicli', args, { cwd: instanceDir(), encoding: 'utf8' })

const helloworldIsOn = (): boolean =>
  /^\[on\]\s+helloworld\b/m.test(yeswicli('extension:list'))

/** Adds a provider through the form the type's settings generate; returns the identifier made from its name. */
const addHelloProvider = async (page: Page, name: string): Promise<string> => {
  await page.goto('/?admin/sign-in')
  await page.locator(`a[href*="admin/sign-in/new/${TYPE}"]`).click()
  await page.locator('#sign-in-label').fill(name)
  const secret = page.locator('#sign-in-connection-password')
  await expect(secret).toHaveAttribute('type', 'password')
  await secret.fill(SHARED_PASSWORD)
  await page.locator('button.yw-btn--primary[type="submit"]').click()
  await expect(page.locator(TOAST)).toContainText(
    /The provider was added|Le fournisseur a été ajouté/,
  )
  const row = page.locator('tbody tr', { hasText: name })
  await expect(row).toBeVisible()

  return (await row.locator('small.yw-form-hint').innerText()).trim()
}

test.describe('a provider type added by an extension', () => {
  let wasOn = false

  test.beforeAll(() => {
    wasOn = helloworldIsOn()
    if (!wasOn) {
      yeswicli('extension:enable', 'helloworld')
    }
    setProviders({ local: { type: 'local' } })
  })

  test.afterAll(() => {
    forgetAccounts(PERSON_EMAIL)
    if (wasOn && !helloworldIsOn()) {
      yeswicli('extension:enable', 'helloworld')
    } else if (!wasOn && helloworldIsOn()) {
      yeswicli('extension:disable', 'helloworld')
    }
    setProviders({ local: { type: 'local' } })
  })

  test('signs in, is refused on a conflict, and is unavailable without its extension', async ({
    page,
  }) => {
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
    const first = await addHelloProvider(page, 'Hello one')
    const second = await addHelloProvider(page, 'Hello two')
    expect(second).not.toBe(first)
    await page.context().clearCookies()

    await page.goto('/?user')
    await signInAtWikiForm(page, first, PERSON, SHARED_PASSWORD)
    await page.goto('/?user')
    await expect(page.locator('.yw-account-guest__card')).toHaveCount(0)
    await expect(page.locator('body')).toContainText(PERSON)
    await page.context().clearCookies()

    const before = Math.max(0, ...journal('%').map((row) => Number(row.id)))
    await page.goto('/?user')
    await signInAtWikiForm(page, second, PERSON, SHARED_PASSWORD)
    await page.goto('/?user')
    await expect(
      page.locator('form.login-form'),
      'the conflict signed nobody in',
    ).toBeVisible()
    const refusals = journal('login.refused.%').filter(
      (row) => Number(row.id) > before && row.action.endsWith(`.${second}`),
    )
    expect(refusals.map((row) => row.action)).toEqual([
      expect.stringMatching(
        /^login\.refused\.(email_conflict|linked_elsewhere)\./,
      ),
    ])
    expect(JSON.stringify(refusals)).not.toContain(SHARED_PASSWORD)

    yeswicli('extension:disable', 'helloworld')
    await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
    await expect(async () => {
      await page.goto('/?admin/sign-in')
      await expect(
        page.locator(`a[href*="admin/sign-in/new/${TYPE}"]`),
      ).toHaveCount(0, { timeout: 500 })
    }).toPass({ timeout: 15000 })
    const row = page.locator('tbody tr', { hasText: 'Hello one' })
    await expect(row.locator('.yw-badge--warning')).toBeVisible()
    await expect(
      row.locator(`a[href*="admin/sign-in/${first}/edit"]`),
    ).toHaveCount(0)
  })
})
