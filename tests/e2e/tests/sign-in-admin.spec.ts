import { BrowserContext, expect, Page, test } from '@playwright/test'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import {
  forgetAccounts,
  PASSWORD,
  PEOPLE,
  PROVIDER_IDS,
  PROVIDERS,
  sectionFor,
  setProviders,
  signInAtPortal,
  signInAtWikiForm,
} from '../helpers/sso'

/** The administration screens of the sign-in providers (spec section 8.3) and the profile of an account a provider owns (8.4). */
/** The message the wiki shows after a redirect, in the page or as a toast. */
const TOAST = '[role="alert"], .toast-message .alert'
const GUEST_CARD = '.yw-account-guest__card'

/** The identifier is made from the name once; the portal accepts the callback of the identifier `oidc` only. */
const NAME = 'OIDC'

const oidc = PROVIDERS.oidc as {
  label: string
  connection: Record<string, string>
  attributes: Record<string, string>
}

const asAdmin = async (page: Page) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
}

/** The row of the list that shows this provider. */
const rowOf = (page: Page, id: string) =>
  page.locator('tbody tr').filter({
    has: page.locator(`form[action*="admin/sign-in/${id}/toggle"]`),
  })

test.describe('the sign-in administration screen', () => {
  test.beforeEach(() => {
    forgetAccounts(PEOPLE.marie.email)
    setProviders({ local: { type: 'local' } })
  })

  test.afterAll(() => {
    forgetAccounts(PEOPLE.marie.email)
    setProviders({ local: { type: 'local' } })
  })

  test('adds an OIDC provider, and a blank secret keeps the stored one', async ({
    page,
    browser,
  }) => {
    await asAdmin(page)
    await page.goto('/?admin/sign-in')
    await page.locator('a[href*="admin/sign-in/new/oidc"]').click()

    await page.locator('#sign-in-label').fill(NAME)
    await page
      .locator('#sign-in-connection-discovery_url')
      .fill(oidc.connection.discovery_url)
    await page
      .locator('#sign-in-connection-client_id')
      .fill(oidc.connection.client_id)
    await page
      .locator('#sign-in-connection-client_secret')
      .fill(oidc.connection.client_secret)
    for (const [name, value] of Object.entries(oidc.attributes)) {
      await page.locator(`#sign-in-attribute-${name}`).fill(value)
    }
    await page.locator('button.yw-btn--primary[type="submit"]').click()

    await expect(page).toHaveURL(/admin\/sign-in(?!\/)/)
    await expect(page.locator(TOAST)).toContainText(
      /The provider was added|Le fournisseur a été ajouté/,
    )
    const row = page.locator('tbody tr', { hasText: NAME })
    await expect(row).toBeVisible()
    const id = (await row.locator('small.yw-form-hint').innerText()).trim()
    expect(id).toBe(PROVIDER_IDS.oidc)

    // edited with the secret left blank: the field announces the stored one, and saving keeps it
    await page.locator(`a[href*="admin/sign-in/${id}/edit"]`).click()
    const secret = page.locator('#sign-in-connection-client_secret')
    await expect(secret).toHaveValue('')
    await expect(secret).toHaveAttribute('placeholder', /\S/)
    expect(await page.content(), 'the secret is on no page').not.toContain(
      oidc.connection.client_secret,
    )
    await page.locator('#sign-in-label').fill(`${NAME} renamed`)
    await page.locator('button.yw-btn--primary[type="submit"]').click()
    await expect(page.locator(TOAST)).toContainText(
      /The provider was saved|Le fournisseur a été enregistré/,
    )

    // the proof is a real sign-in: the portal only answers a client that presents the stored secret
    const context: BrowserContext = await browser.newContext()
    try {
      const visitor = await context.newPage()
      await visitor.goto('/?user')
      await visitor
        .locator(`${GUEST_CARD} .yw-sign-in__provider`, {
          hasText: `${NAME} renamed`,
        })
        .click()
      await signInAtPortal(visitor, PEOPLE.marie.login, PASSWORD)
      await visitor.goto('/?user')
      await expect(visitor.locator(GUEST_CARD)).toHaveCount(0)
    } finally {
      await context.close()
    }
  })

  test('the last provider switched on cannot be switched off', async ({
    page,
  }) => {
    await asAdmin(page)
    await page.goto('/?admin/sign-in')
    const local = rowOf(page, 'local')
    await expect(local.locator('.yw-badge--success')).toBeVisible()

    await local.locator('button[type="submit"]').click()

    await expect(page.locator(TOAST)).toContainText(
      /last provider that is switched on|dernier fournisseur activé/,
    )
    await expect(
      rowOf(page, 'local').locator('.yw-badge--success'),
    ).toBeVisible()
  })

  test('a profile a provider owns is read-only, with its notice', async ({
    page,
  }) => {
    setProviders(sectionFor('ldap'))
    await page.goto('/?user')
    await signInAtWikiForm(page, 'ldap', PEOPLE.marie.login, PASSWORD)

    await page.goto('/?user')
    const label = String(PROVIDERS.ldap.label)
    const notice = page.locator('.alert-info', { hasText: label })
    await expect(notice).toContainText(label)
    await expect(notice).toContainText(
      /cannot be changed here|ne peuvent pas être modifiés ici/,
    )
    await expect(page.locator('input[name="email"]')).toBeDisabled()
    await expect(page.locator('input[name="email"]')).toHaveValue(
      PEOPLE.marie.email,
    )
    // the name fields are shown as text, not as inputs
    await expect(
      page.locator('input[name="bf_nom"], input[name="bf_prenom"]'),
    ).toHaveCount(0)
    await expect(
      page.locator('[data-id="first_name"] .BAZ_texte').first(),
    ).toHaveText('Marie')
  })
})
