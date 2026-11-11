import { mkdirSync, rmSync, writeFileSync } from 'fs'
import { dirname } from 'path'
import { test, expect } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { instancePath, onReplicas } from '../helpers/instance'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.skip(onReplicas(), "a template written to one replica's custom/ is not the other replicas'")

const PROBES = [
  'core/admin/keywords.twig',
  'core/admin/custom-templates.twig',
  'core/fiche-1.twig',
  'core/entries/index-dynamic-templates/picker-probe.twig',
]
const clearProbes = () => {
  PROBES.forEach((probe) =>
    rmSync(instancePath(`custom/templates/${probe}`), { force: true }),
  )
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
  await page.locator('.yw-corner-tools [data-yw-scheme]').click()
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

const writeProbe = (probe: string, source: string) => {
  const path = instancePath(`custom/templates/${probe}`)
  mkdirSync(dirname(path), { recursive: true })
  writeFileSync(path, source)
}

const openProbe = async (page, probe: string) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto(`/?admin/custom-templates&file=${encodeURIComponent(probe)}`)
  await expect(page.locator('.yw-templates__ace')).toBeVisible()
}

const editorText = (page) =>
  page.evaluate(() =>
    window['ace'].edit(document.querySelector('.yw-templates__ace')).getValue(),
  )

const pickFirstField = async (page, label: string) => {
  await page.locator('[data-yw-fb-template-fields-toggle]').click()
  await page
    .locator('[data-yw-fb-template-fields] li')
    .first()
    .getByRole('button', { name: label, exact: true })
    .click()
}

test('an entry template offers the fields of the form in its name', async ({
  page,
}) => {
  writeProbe('core/fiche-1.twig', '<div></div>\n')
  await openProbe(page, 'core/fiche-1.twig')

  await expect(page.locator('#yw-template-form')).toHaveCount(0)
  await pickFirstField(page, 'Valeur HTML')

  expect(await editorText(page)).toContain('{{ html.bf_nom|raw }}')
})

test('a list template finds its form from what it reads, and loads what it inserts', async ({
  page,
}) => {
  const probe = 'core/entries/index-dynamic-templates/picker-probe.twig'
  writeProbe(
    probe,
    `{% extends "@core/entries/index-dynamic.twig" %}\n{% set necessary_fields = ['bf_prenom', 'bf_fonction'] %}\n`,
  )
  await openProbe(page, probe)

  await expect(page.locator('#yw-template-form option:checked')).toHaveText(
    'Annuaire',
  )
  await pickFirstField(page, 'Valeur brute')

  const source = await editorText(page)
  expect(source).toContain('<span v-text="entry.bf_nom"></span>')
  expect(source).toContain("['bf_prenom', 'bf_fonction', 'bf_nom']")
})

test('a computed necessary_fields is left alone, with a word about it', async ({
  page,
}) => {
  const probe = 'core/entries/index-dynamic-templates/picker-probe.twig'
  writeProbe(
    probe,
    `{% extends "@core/entries/index-dynamic.twig" %}\n{% set necessary_fields = (necessary_fields|default([]))|merge(['bf_prenom']) %}\n`,
  )
  await openProbe(page, probe)
  await pickFirstField(page, 'Valeur brute')

  await expect(page.locator('[data-yw-template-fields-notice]')).toContainText(
    'necessary_fields',
  )
})

test('a template with no form behind it has no field picker', async ({
  page,
}) => {
  writeProbe('core/admin/keywords.twig', '<div></div>\n')
  await openProbe(page, 'core/admin/keywords.twig')

  await expect(page.locator('[data-yw-template-fields]')).toHaveCount(0)
})
