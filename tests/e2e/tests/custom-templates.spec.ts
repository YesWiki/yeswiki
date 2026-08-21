import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

const PROBES = ['core/admin/keywords.twig', 'core/admin/custom-templates.twig']
const clearProbes = () => {
  const paths = PROBES.map((p) => `/var/www/html/custom/templates/${p}`).join(
    ' ',
  )
  execSync(`rm -f ${paths}`)
}

test.beforeEach(async () => {
  resetEnv()
  clearProbes()
})

test.afterEach(async () => {
  clearProbes()
})

const FLASH = '[role="alert"]'

/** Write into the editor the way a person does, once it is actually there. */
async function typeIntoEditor(page, source: string) {
  await expect(page.locator('.yw-templates__ace')).toBeVisible()
  await page.waitForFunction(() => typeof window['ace'] !== 'undefined', null, {
    timeout: 10000,
  })
  await page.evaluate((text) => {
    window['ace']
      .edit(document.querySelector('.yw-templates__ace'))
      .setValue(text)
  }, source)
}

/** Template overrides, as a screen (ticket 30). */
test('an admin copies a template, edits it, and the wiki renders the edit', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/custom-templates')

  await expect(page.locator('.yw-dashboard__sidebar')).toContainText(
    'Apparence',
  )
  await expect(page.locator('.yw-dashboard__link--current')).toContainText(
    'Gabarits',
  )
  // The list renders a table or an empty-state paragraph, never both, and a wiki with no
  // override at all gets the paragraph -- so asserting on the table alone fails on a clean
  // wiki with "element(s) not found" rather than on the thing it means to check.
  await expect(
    page.locator('.yw-templates__table, .yw-templates__empty'),
  ).not.toContainText('core/admin/keywords.twig')

  await page
    .locator('.yw-templates__start select')
    .selectOption('admin/keywords.twig')
  await page.locator('button[name="create"]').click()
  await expect(page.locator(FLASH)).toContainText(/gabarit/i)

  const textarea = page.locator('#yw-template-source')
  await expect(page.locator('.yw-templates__ace')).toBeVisible()
  await expect(textarea).toBeHidden()

  const mode = await page.evaluate(
    () =>
      window['ace']
        .edit(document.querySelector('.yw-templates__ace'))
        .session.getMode().$id,
  )
  expect(mode).toBe('ace/mode/twig')

  await typeIntoEditor(
    page,
    '{% extends "@shipped/dashboard/layout.twig" %}{% block dashboard_content %}OVERRIDE-IS-LIVE{% endblock %}',
  )
  await expect(textarea).toHaveValue(/OVERRIDE-IS-LIVE/)

  await page
    .locator('.yw-templates__actions button[type="submit"]')
    .first()
    .click()
  await expect(page.locator(FLASH)).toContainText(/enregistr/i)

  await page.goto('/?admin/keywords')
  await expect(page.locator('.yw-dashboard__canvas')).toContainText(
    'OVERRIDE-IS-LIVE',
  )

  await page.goto('/?admin/custom-templates')
  await expect(page.locator('.yw-templates__table')).toContainText(
    'core/admin/keywords.twig',
  )
  await expect(page.locator('.yw-templates__table')).toContainText(
    '@core/admin/keywords.twig',
  )
})

/** The same pair of defects the stylesheet screen had, on the screen that shares its shape. */
test('the template editor is dark when the reader is, and survives leaving the screen', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  await page.goto('/?PagePrincipale')
  await page.locator('.yw-topnav-tools__menu:has([data-yw-scheme])').hover()
  await page.locator('[data-yw-scheme-set="dark"]').click()

  await page.goto('/?admin/custom-templates')
  await page
    .locator('.yw-templates__start select')
    .selectOption('admin/keywords.twig')
  await page.locator('button[name="create"]').click()

  const editor = page.locator('.yw-templates__ace')
  await expect(editor).toBeVisible()

  const surface = await page.evaluate(() => {
    const probe = document.createElement('span')
    probe.style.color = 'var(--yw-surface-raised)'
    document.body.appendChild(probe)
    const value = getComputedStyle(probe).color
    probe.remove()
    return value
  })
  await expect
    .poll(() => editor.evaluate((el) => getComputedStyle(el).backgroundColor))
    .toBe(surface)

  await page.locator('.yw-dashboard__sidebar a[href*="admin/preset"]').click()
  await expect(page.locator('#yw-template-source')).toHaveCount(0)
  await page
    .locator('.yw-dashboard__sidebar a[href*="admin/custom-templates"]')
    .click()
  await page.locator('.yw-templates__table a[href*="file="]').first().click()

  await expect(
    page.locator('.yw-templates__ace'),
    'the editor did not come back: the module ran once for the whole document',
  ).toBeVisible()
  await expect(page.locator('#yw-template-source')).toBeHidden()
})

test('a template that does not compile is refused, and the wiki keeps working', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/custom-templates')

  await page
    .locator('.yw-templates__start select')
    .selectOption('admin/keywords.twig')
  await page.locator('button[name="create"]').click()

  await typeIntoEditor(page, '{% block never_closed %}broken')
  await page
    .locator('.yw-templates__actions button[type="submit"]')
    .first()
    .click()

  await expect(page.locator(FLASH)).toContainText(/block/i)

  await page.goto('/?admin/keywords')
  await expect(page.locator('.yw-dashboard__canvas')).toBeVisible()
})

test('reverting puts the shipped template back', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/custom-templates')

  await page
    .locator('.yw-templates__start select')
    .selectOption('admin/keywords.twig')
  await page.locator('button[name="create"]').click()
  await expect(page.locator('.yw-templates__table')).toContainText(
    'core/admin/keywords.twig',
  )

  page.on('dialog', (dialog) => dialog.accept())
  await page.locator('button[name="revert"]').click()

  await expect(
    page.locator('.yw-templates__table, .yw-templates__empty'),
  ).not.toContainText('core/admin/keywords.twig')
  await page.goto('/?admin/keywords')
  await expect(page.locator('.yw-dashboard__canvas')).toBeVisible()
})

/** The safety net: the screen that removes overrides cannot itself be overridden. */
test('the screen that fixes overrides renders as shipped, whatever is in custom/templates', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  for (const target of ['admin/keywords.twig', 'admin/custom-templates.twig']) {
    await page.goto('/?admin/custom-templates')
    await page.locator('.yw-templates__start select').selectOption(target)
    await page.locator('button[name="create"]').click()
    await typeIntoEditor(
      page,
      '{% extends "@shipped/dashboard/layout.twig" %}{% block dashboard_content %}HIJACKED{% endblock %}',
    )
    await page
      .locator('.yw-templates__actions button[type="submit"]')
      .first()
      .click()
  }

  await page.goto('/?admin/keywords')
  await expect(page.locator('.yw-dashboard__canvas')).toContainText('HIJACKED')

  await page.goto('/?admin/custom-templates')
  await expect(page.locator('.yw-dashboard__canvas')).not.toContainText(
    'HIJACKED',
  )
  await expect(page.locator('.yw-templates__table')).toContainText(
    'core/admin/custom-templates.twig',
  )
})
