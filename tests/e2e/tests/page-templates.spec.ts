// A page an admin marks as a template is offered to whoever creates a page (end-users 22).
import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { setPageContent } from '../helpers/page'
import { editorReady, editorText, saveEditor } from '../helpers/editor'

test.beforeEach(async () => {
  resetEnv()
})

const TEMPLATE = 'ModeleCompteRendu'

const markAsTemplate = async (page: Page) => {
  await setPageContent(page, TEMPLATE, '# Compte rendu\n\nPrésents :')
  await page.goto(`/?${TEMPLATE}/edit`)
  await editorReady(page)
  await page.getByLabel('Proposer cette page comme modèle').check()
  await saveEditor(page)
}

test('a new page starts from the template the writer picks', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await markAsTemplate(page)

  await page.goto('/?CompteRenduDuJour')
  await page.getByRole('link', { name: 'créer' }).click()
  const chooser = page.locator('.yw-template-chooser')
  await expect(chooser).toContainText('Page vierge')
  await chooser.getByRole('link', { name: /Compte rendu/ }).click()

  await editorReady(page)
  expect(await editorText(page)).toContain('Présents :')
  await saveEditor(page)
  await expect(page.locator('#yw-main')).toContainText('Présents :')

  await page.goto('/?CompteRenduDuJour/edit')
  await editorReady(page)
  await expect(
    page.getByLabel('Proposer cette page comme modèle'),
  ).not.toBeChecked()
})

test('the blank page opens an empty editor, and a wiki without templates skips the choice', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  await page.goto('/?PageSansModele/edit')
  await editorReady(page)
  await expect(page.locator('.yw-template-chooser')).toHaveCount(0)

  await markAsTemplate(page)
  await page.goto('/?PageSansModele/edit')
  await page
    .locator('.yw-template-chooser')
    .getByRole('link', { name: /Page vierge/ })
    .click()
  await editorReady(page)
  expect(await editorText(page)).not.toContain('Présents :')
})
