import { execFileSync } from 'child_process'
import { fileURLToPath } from 'url'
import { expect, Page } from '@playwright/test'
import { instanceDir } from './instance'

/**
 * The identity servers of the development stack (docker/README.md): LemonLDAP::NG as OpenID Connect
 * provider, SAML identity provider and CAS server, in front of one OpenLDAP directory.
 */
export const PORTAL = 'http://auth.yeswiki.test'
export const PORTAL_HOST = new URL(PORTAL).host

export const LDAP_URI = 'ldap://yeswiki-ldap:1389'
export const PEOPLE_BASE = 'ou=people,dc=acme,dc=example'
export const GROUPS_BASE = 'ou=groups,dc=acme,dc=example'
const ADMIN_DN = 'cn=admin,dc=acme,dc=example'
const ADMIN_PASSWORD = 'AdminPassword1'

export const PASSWORD = 'TestPassword1'
export const PEOPLE = {
  marie: {
    login: 'marie',
    firstName: 'Marie',
    lastName: 'Dupont',
    email: 'marie.dupont@acme.example',
    groups: ['wiki-editors', 'staff'],
  },
  jean: {
    login: 'jean',
    firstName: 'Jean',
    lastName: 'Martin',
    email: 'jean.martin@acme.example',
    groups: ['staff'],
  },
  nomail: { login: 'nomail' },
}

export const personDn = (login: string): string => `uid=${login},${PEOPLE_BASE}`

/** What every client of the portal exports, under the directory's names. */
const ATTRIBUTES = {
  id: 'uid',
  email: 'mail',
  first_name: 'givenName',
  last_name: 'sn',
  groups: 'groups',
}

export type ProviderType = 'oidc' | 'saml' | 'cas' | 'ldap'

/** The types whose login and password are typed into the wiki's own form, not at the portal. */
export const PASSWORD_TYPES: ProviderType[] = ['ldap']

/** The identifier each type is registered under at the portal: it is part of the addresses the portal accepts. */
export const PROVIDER_IDS: Record<ProviderType, string> = {
  oidc: 'oidc',
  saml: 'saml-a',
  cas: 'cas',
  ldap: 'ldap',
}

/** One entry of `sso_providers` per type, against the portal and the directory. The task adding a type settles its `connection`. */
export const PROVIDERS: Record<ProviderType, Record<string, unknown>> = {
  oidc: {
    type: 'oidc',
    label: 'Acme OIDC',
    connection: {
      discovery_url: `${PORTAL}/.well-known/openid-configuration`,
      client_id: 'wiki-oidc',
      client_secret: 'wiki-oidc-secret',
    },
    attributes: ATTRIBUTES,
  },
  saml: {
    type: 'saml',
    label: 'Acme SAML',
    connection: { metadata_url: `${PORTAL}/saml/metadata` },
    attributes: ATTRIBUTES,
  },
  cas: {
    type: 'cas',
    label: 'Acme CAS',
    connection: { server_url: `${PORTAL}/cas`, version: '3.0' },
    attributes: ATTRIBUTES,
  },
  ldap: {
    type: 'ldap',
    label: 'Acme directory',
    connection: {
      uri: LDAP_URI,
      bind_dn_template: `uid={login},${PEOPLE_BASE}`,
      // the directory has no memberOf: groups come from a search, by their cn, under the `groups` name the others use
      group_base: GROUPS_BASE,
    },
    attributes: ATTRIBUTES,
  },
}

/** A section with local accounts and the type's provider, its entry changed by `overrides`. */
export const sectionFor = (
  type: ProviderType,
  overrides: Record<string, unknown> = {},
): Record<string, unknown> => ({
  local: { type: 'local' },
  [PROVIDER_IDS[type]]: { ...PROVIDERS[type], ...overrides },
})

const helper = (name: string): string =>
  fileURLToPath(new URL(`./${name}`, import.meta.url))

const php = (script: string, args: string[]): string =>
  execFileSync('php', [helper(script), ...args], {
    cwd: instanceDir(),
    encoding: 'utf8',
  })

/** How long the web server's opcode cache may keep serving the previous configuration file (opcache.revalidate_freq). */
const OPCACHE_REVALIDATE_MS = 3100

/** Writes `sso_providers` into the wiki's configuration file, and waits until the web server reads the new one. */
export const setProviders = (section: object): void => {
  php('set-sso-providers.php', [JSON.stringify(section)])
  Atomics.wait(
    new Int32Array(new SharedArrayBuffer(4)),
    0,
    0,
    OPCACHE_REVALIDATE_MS,
  )
}

export type JournalRow = {
  id: number | string
  action: string
  target: string
  channel: string
  context: Record<string, unknown>
}

/** The Journal rows whose action is LIKE `pattern`, oldest first. */
export const journal = (pattern: string): JournalRow[] =>
  JSON.parse(php('sso-wiki.php', ['journal', pattern]))

/** Deletes the wiki accounts that have these emails, so a sign-in meets the wiki for the first time. */
export const forgetAccounts = (...emails: string[]): void => {
  php('sso-wiki.php', ['forget', ...emails])
}

/** The name of the account that has this email, null when none has. */
export const accountWithEmail = (email: string): string | null =>
  JSON.parse(php('sso-wiki.php', ['account', email]))

export const groupsOf = (account: string): string[] =>
  JSON.parse(php('sso-wiki.php', ['groups', account]))

export const addToGroup = (group: string, account: string): void => {
  php('sso-wiki.php', ['add-to-group', group, account])
}

/** Deletes the failed and throttled sign-ins of these logins, so the attempt limit no longer counts them. */
export const forgetAttempts = (...logins: string[]): void => {
  php('sso-wiki.php', ['forget-attempts', ...logins])
}

/** Types the login and password into the portal's form, once the browser has been sent there. */
export const signInAtPortal = async (
  page: Page,
  login: string,
  password: string,
): Promise<void> => {
  await page.waitForURL((url) => url.host === PORTAL_HOST, {
    waitUntil: 'domcontentloaded',
  })
  await page.locator('#userfield').fill(login)
  await page.locator('#passwordfield').fill(password)
  await page.locator('#lform button[type="submit"]').click()
  await expect(page).not.toHaveURL((url) => url.host === PORTAL_HOST)
}

/**
 * Types the login and password into the wiki's own sign-in form, on the page the browser is at, under the tab of the provider
 * `providerId` when the form has several; then waits for the page the wiki answers with.
 */
export const signInAtWikiForm = async (
  page: Page,
  providerId: string,
  login: string,
  password: string,
): Promise<void> => {
  const form = page.locator('form.login-form')
  if ((await form.locator('.yw-sign-in__tab').count()) > 0) {
    // the radio is drawn under its label: a person clicks the label
    const choice = `input[name="provider"][value="${providerId}"]`
    const tab = form
      .locator('.yw-sign-in__tab')
      .filter({ has: page.locator(choice) })
    await tab.click()
    await expect(tab.locator(choice)).toBeChecked()
  }
  await form.locator('#inputWikiName').fill(login)
  await form.locator('#inputPassword').fill(password)
  await form.locator('button[type="submit"]').click()
  await page.waitForLoadState('domcontentloaded')
}

/** Types the login and password into an administrator's test of a password provider, then waits for the result. */
export const testAtWikiForm = async (
  page: Page,
  login: string,
  password: string,
): Promise<void> => {
  await page.locator('#sign-in-test-login').fill(login)
  await page.locator('#sign-in-test-password').fill(password)
  await page.locator('#sign-in-test-password').press('Enter')
  await page.waitForLoadState('domcontentloaded')
}

/**
 * How many binds the directory has answered since it started, from its monitor backend. Reading it is itself a bind,
 * counted in what it returns: two reads in a row differ by one.
 */
export const directoryBinds = (): number => {
  const out = execFileSync(
    'ldapsearch',
    [
      '-x',
      '-LLL',
      '-H',
      LDAP_URI,
      '-D',
      ADMIN_DN,
      '-w',
      ADMIN_PASSWORD,
      '-b',
      'cn=Bind,cn=Operations,cn=Monitor',
      '-s',
      'base',
      'monitorOpCompleted',
    ],
    { encoding: 'utf8' },
  )
  const count = out.match(/^monitorOpCompleted: (\d+)$/m)?.[1]
  expect(count, 'the directory counts its binds').toBeTruthy()

  return Number(count)
}

/** Whether the directory accepts this DN and password. */
export const bindAs = (dn: string, password: string): boolean => {
  try {
    execFileSync(
      'ldapwhoami',
      ['-x', '-H', LDAP_URI, '-D', dn, '-w', password],
      {
        stdio: 'ignore',
      },
    )

    return true
  } catch {
    return false
  }
}

/** Replaces one value of a directory entry, as its administrator. */
export const setDirectoryValue = (
  dn: string,
  attribute: string,
  value: string,
): void => {
  execFileSync(
    'ldapmodify',
    ['-x', '-H', LDAP_URI, '-D', ADMIN_DN, '-w', ADMIN_PASSWORD],
    {
      input: `dn: ${dn}\nchangetype: modify\nreplace: ${attribute}\n${attribute}: ${value}\n`,
      stdio: ['pipe', 'ignore', 'inherit'],
    },
  )
}
