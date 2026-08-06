import { execSync } from 'child_process'
import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

// The overrides these tests create are FILES, and `resetEnv()` resets the database. So a
// leftover from one test makes the next one's "copy this template" refuse -- which is what
// turned one real failure into four. Removed by name rather than by wiping the directory:
// `custom/` is the instance's own, and a developer's real overrides live there too.
const PROBES = ['core/admin/keywords.twig', 'core/admin/custom-templates.twig']
const clearProbes = () => {
  const paths = PROBES.map((p) => `/var/www/html/custom/templates/${p}`).join(' ')
  execSync(`rm -f ${paths}`)
}

test.beforeEach(async() => {
  resetEnv()
  clearProbes()
})

test.afterEach(async() => {
  clearProbes()
})

// The flash, and only the flash. `.alert` alone also matches this screen's permanent
// "a template is code" warning and the actions builder's Vue hint box, and Playwright's
// strict mode fails on two matches -- which reads as a product bug and is not one.
const FLASH = '[role="alert"]'

/**
 * Write into the editor the way a person does, once it is actually there.
 *
 * ACE is layered over the textarea by a deferred module, so `window.ace` does not exist the
 * instant the screen renders. Waiting for the surface to be visible is not enough either --
 * the div is in the markup before the module runs. Two tests here evaluated straight after
 * the redirect and failed on `Cannot read properties of undefined (reading 'edit')`, which
 * reads exactly like the editor being broken and was a race in the test.
 */
async function typeIntoEditor(page, source: string) {
  await expect(page.locator('.yw-templates__ace')).toBeVisible()
  await page.waitForFunction(() => typeof window['ace'] !== 'undefined', null, { timeout: 10000 })
  await page.evaluate((text) => {
    window['ace'].edit(document.querySelector('.yw-templates__ace')).setValue(text)
  }, source)
}

/**
 * Template overrides, as a screen (ticket 30).
 *
 * There is no sandbox and that was measured, not skipped: Twig's sandbox propagates into
 * `{% extends %}`, so an override cannot be sandboxed without sandboxing the core template it
 * extends. A custom template is code, the boundary is `@admins`, and what replaces a policy
 * is the four things that actually go wrong -- which is what these cover.
 */
test('an admin copies a template, edits it, and the wiki renders the edit', async({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/custom-templates')

  await expect(page.locator('.yw-dashboard__sidebar')).toContainText('Apparence')
  await expect(page.locator('.yw-dashboard__link--current')).toContainText('Gabarits')
  // this override in particular is not there yet. Not "the list is empty": `custom/` is the
  // instance's own directory, and a developer running this suite may well have overrides of
  // their own sitting in it.
  await expect(page.locator('.yw-templates__table')).not.toContainText('core/admin/keywords.twig')

  // start from the shipped template: the copy is verbatim, so the first save changes nothing
  await page.locator('.yw-templates__start select').selectOption('admin/keywords.twig')
  await page.locator('button[name="create"]').click()
  await expect(page.locator(FLASH)).toContainText(/gabarit/i)

  // ...and the screen comes back with it open
  const textarea = page.locator('#yw-template-source')
  await expect(page.locator('.yw-templates__ace')).toBeVisible()
  await expect(textarea).toBeHidden()

  // `ace/mode/twig`, vendored beside the HTML and CSS modes: a Twig tag is highlighted as a
  // Twig tag rather than as stray text inside markup
  const mode = await page.evaluate(() =>
    window['ace'].edit(document.querySelector('.yw-templates__ace')).session.getMode().$id)
  expect(mode).toBe('ace/mode/twig')

  // typed through ACE, which is what a person uses, and which must reach the field the form
  // posts -- the textarea is downstream of the editor
  await typeIntoEditor(
    page,
    '{% extends "@shipped/dashboard/layout.twig" %}{% block dashboard_content %}OVERRIDE-IS-LIVE{% endblock %}'
  )
  await expect(textarea).toHaveValue(/OVERRIDE-IS-LIVE/)

  await page.locator('.yw-templates__actions button[type="submit"]').first().click()
  await expect(page.locator(FLASH)).toContainText(/enregistr/i)

  // the part no unit test can see: the wiki is rendering it
  await page.goto('/?admin/keywords')
  await expect(page.locator('.yw-dashboard__canvas')).toContainText('OVERRIDE-IS-LIVE')

  // and it is listed as an override of a template that ships
  await page.goto('/?admin/custom-templates')
  await expect(page.locator('.yw-templates__table')).toContainText('core/admin/keywords.twig')
  await expect(page.locator('.yw-templates__table')).toContainText('@core/admin/keywords.twig')
})

test('a template that does not compile is refused, and the wiki keeps working', async({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/custom-templates')

  await page.locator('.yw-templates__start select').selectOption('admin/keywords.twig')
  await page.locator('button[name="create"]').click()

  await typeIntoEditor(page, '{% block never_closed %}broken')
  await page.locator('.yw-templates__actions button[type="submit"]').first().click()

  // Twig's own message, which names the line -- refused before anything was written
  await expect(page.locator(FLASH)).toContainText(/block/i)

  // the copy from before the broken save is what still renders: a wiki whose editor accepted
  // a template that cannot parse would be a wiki with a 500 on that screen
  await page.goto('/?admin/keywords')
  await expect(page.locator('.yw-dashboard__canvas')).toBeVisible()
})

test('reverting puts the shipped template back', async({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/custom-templates')

  await page.locator('.yw-templates__start select').selectOption('admin/keywords.twig')
  await page.locator('button[name="create"]').click()
  await expect(page.locator('.yw-templates__table')).toContainText('core/admin/keywords.twig')

  page.on('dialog', (dialog) => dialog.accept())
  await page.locator('button[name="revert"]').click()

  await expect(page.locator('.yw-templates__table, .yw-templates__empty'))
    .not.toContainText('core/admin/keywords.twig')
  await page.goto('/?admin/keywords')
  await expect(page.locator('.yw-dashboard__canvas')).toBeVisible()
})

/**
 * The safety net: the screen that removes overrides cannot itself be overridden.
 *
 * Asserted with a *working* override in place, not a broken one -- "it did not show up" is
 * also what you see when overriding does not work at all, so the control (the same override
 * on another screen, which must take it) is what makes this mean anything.
 */
test('the screen that fixes overrides renders as shipped, whatever is in custom/templates', async({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  for (const target of ['admin/keywords.twig', 'admin/custom-templates.twig']) {
    await page.goto('/?admin/custom-templates')
    await page.locator('.yw-templates__start select').selectOption(target)
    await page.locator('button[name="create"]').click()
    await typeIntoEditor(
      page,
      '{% extends "@shipped/dashboard/layout.twig" %}{% block dashboard_content %}HIJACKED{% endblock %}'
    )
    await page.locator('.yw-templates__actions button[type="submit"]').first().click()
  }

  // the control: an ordinary screen does take it
  await page.goto('/?admin/keywords')
  await expect(page.locator('.yw-dashboard__canvas')).toContainText('HIJACKED')

  // and this one does not, so it can still remove the override
  await page.goto('/?admin/custom-templates')
  await expect(page.locator('.yw-dashboard__canvas')).not.toContainText('HIJACKED')
  await expect(page.locator('.yw-templates__table')).toContainText('core/admin/custom-templates.twig')
})
