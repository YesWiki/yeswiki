import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'fs'
import { dirname } from 'path'
import { expect, Page, test } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { instancePath } from '../helpers/instance'
import { createEntry } from '../helpers/bazar'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

const TEMPLATE = 'custom/templates/core/fiche-1.twig'
const clearTemplate = () => rmSync(instancePath(TEMPLATE), { force: true })

const writeTemplate = (source: string) => {
  mkdirSync(dirname(instancePath(TEMPLATE)), { recursive: true })
  writeFileSync(instancePath(TEMPLATE), source)
}

/** The form's own preview can hold a Vditor too, so every lookup stays in this section. */
const templateSection = (page: Page) => page.locator('#entry-presentation')
const surface = (page: Page) =>
  templateSection(page).locator('.vditor-wysiwyg .vditor-reset')

test.beforeEach(async ({ page }) => {
  resetEnv()
  clearTemplate()
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
})

test.afterEach(clearTemplate)

const openTemplateSection = async (page) => {
  await page.goto('/?annuaire/edit')
  await page.locator('#entry-presentation summary').first().click()
}

const saveForm = async (page) => {
  await page.locator('#bazar-form-editor button[type="submit"]').first().click()
  await page.waitForURL((url) => !url.search.includes('/edit'))
}

const pickField = async (page, label: string) => {
  await page.locator('[data-yw-fb-template-fields-toggle]').click()
  await page
    .locator('[data-yw-fb-template-fields] li')
    .first()
    .getByRole('button', { name: label, exact: true })
    .click()
}

test('a new template is written in Vditor, fields included, and survives a heading and a save', async ({
  page,
}) => {
  await openTemplateSection(page)
  await expect(surface(page)).toBeVisible()

  await surface(page).click()
  await pickField(page, 'Valeur HTML')
  await pickField(page, 'Libellé')
  await pickField(page, 'Valeur brute')

  const posted = await page.locator('#entry_template').inputValue()
  expect(posted).toContain('|raw }}')
  expect(posted).toContain('.label }}')
  expect(posted).toContain('{{ entry.')
  expect(posted).not.toContain('⦃')

  await page.evaluate(() =>
    window['ywEditors'].entry_template.setValue(
      '## {{ html.bf_titre|raw }}\n\n| A | B |\n| --- | --- |\n| {{ html.bf_titre|raw }} | **gras** |\n\n{{button link="PagePrincipale" text="Ok"}}\n',
    ),
  )
  await expect(
    templateSection(page).locator('.yw-component[data-yw-component="button"]'),
  ).toBeVisible()

  await saveForm(page)
  expect(existsSync(instancePath(TEMPLATE))).toBe(true)
  const saved = readFileSync(instancePath(TEMPLATE), 'utf8')
  expect(saved.startsWith('{# markdown #}\n')).toBe(true)
  expect(saved).toContain('## {{ html.bf_titre|raw }}')
  expect(saved).toContain('| {{ html.bf_titre|raw }} |')

  await page.goto('/?annuaire/edit')
  await expect
    .poll(() =>
      page.evaluate(() => window['ywEditors']?.entry_template?.getValue()),
    )
    .toContain('## {{ html.bf_titre|raw }}')
  const reopened = await page.evaluate(() =>
    window['ywEditors'].entry_template.getValue(),
  )
  expect(reopened).toContain('{{ html.bf_titre|raw }}')
  expect(reopened).toContain('{{button link="PagePrincipale" text="Ok"}}')
  expect(reopened).not.toContain('{# markdown #}')
})

test('a handwritten HTML template stays in Ace, with the same field picker', async ({
  page,
}) => {
  writeTemplate('<div class="x">{{ html.bf_titre|raw }}</div>\n')
  await openTemplateSection(page)

  await expect(page.locator('.yw-templates__ace')).toBeVisible()
  await expect(templateSection(page).locator('.vditor-wysiwyg')).toHaveCount(0)
  await pickField(page, 'Libellé')
  await expect(page.locator('#entry-template')).toHaveValue(/\.label \}\}/)
})

test('the components rail opens on the form screen, and the saved template renders an entry', async ({
  page,
}) => {
  await openTemplateSection(page)
  await expect(surface(page)).toBeVisible()
  await templateSection(page)
    .locator('.vditor-toolbar button[data-type="yw-component"]')
    .click()
  await expect(page.locator('#actions-builder-panel')).toBeVisible()
  await expect(
    templateSection(page).locator(
      '.vditor-toolbar button[data-type="yw-switch-editor"]',
    ),
  ).toHaveCount(0)

  await openTemplateSection(page)
  await expect(surface(page)).toBeVisible()
  await page.evaluate(() =>
    window['ywEditors'].entry_template.setValue(
      '## {{ formFields.bf_nom.label }}\n\n**{{ entry.bf_nom }}** {{ html.bf_prenom|raw }}\n',
    ),
  )
  await saveForm(page)

  const tag = await createEntry(page, 1, {
    bf_nom: 'Dupont',
    bf_prenom: 'Camille',
    bf_mail: 'camille@example.org',
    antispam: '1',
  })
  await page.goto(`/?${tag}`)
  await expect(page.locator('h2', { hasText: 'Nom' })).toBeVisible()
  await expect(page.locator('strong', { hasText: 'Dupont' })).toBeVisible()
  await expect(page.locator('body')).toContainText('Camille')
})
