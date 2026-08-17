import { Page } from '@playwright/test'
import {
  replaceEditorTextCallback,
  replaceEditorTextNewContent,
  saveEditor,
} from './editor'
import { errorShouldBe } from './alert'

export const createPageWithContent = async (
  page: Page,
  tag: string,
  content: string,
) => {
  await page.goto(`/?${tag}`)

  await page.getByRole('link', { name: 'créer' }).click()
  await replaceEditorTextNewContent(page, content)
  await saveEditor(page)
}

/** Give `tag` this content, whether or not the page already exists. */
export const setPageContent = async (
  page: Page,
  tag: string,
  content: string,
) => {
  await page.goto(`/?${tag}/edit`)
  await replaceEditorTextNewContent(page, content)
  await saveEditor(page)
}

export const removePage = async (page: Page, tag: string) => {
  await page.goto(`/?${tag}`)
  await page.locator('.footer').getByRole('link', { name: 'Supprimer' }).click()
  await page
    .locator('#YesWikiModal.modal.in')
    .getByRole('button', { name: 'Supprimer' })
    .click()
  await errorShouldBe(page, `La page ${tag} a définitivement été supprimée`)
}
