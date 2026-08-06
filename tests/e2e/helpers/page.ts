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
  // saveEditor, not click + waitForLoadState: the latter waits on the CURRENT document's
  // load state, so if the navigation has not started yet it resolves immediately and the
  // caller carries on against the pre-save page. Same race as ticket 25's defect 9.
  await saveEditor(page)
}

/**
 * Give `tag` this content, whether or not the page already exists.
 *
 * `createPageWithContent` goes through the "créer" link, so it asserts the page is new and a
 * spec that does not reset the database fails on its second run. Reaching the editor by URL
 * works either way, which is what a test wanting *a page with this content* actually means.
 */
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
