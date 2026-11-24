import { execFileSync } from 'child_process'
import { fileURLToPath } from 'url'
import { expect, Locator, Page } from '@playwright/test'
import { instanceDir } from './instance'
import {
  ADMIN_PASSWORD,
  ADMIN_USERNAME,
  login,
  logout,
  signedPost,
} from './login'

export const checkCheckbox = async (parent: Locator, label: string) => {
  await parent
    .locator('.bazar-checkbox-cols > .checkbox')
    .filter({
      hasText: label,
    })
    .click()
}

/** Create an entry over the API as whoever the page is signed in as, and return its tag. */
export const createEntry = async (
  page: Page,
  formId: number,
  fields: Record<string, string>,
): Promise<string> => {
  const response = await signedPost(page, `/?api/entries/${formId}`, {
    data: fields,
  })
  expect(response.status()).toBe(201)
  const { success } = (await response.json()) as { success: string }

  return new URL(success).search.slice(1)
}

/** Two resources of different types in the Ressources Starter's form, created as the admin. */
export const seedResources = async (page: Page) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await createEntry(page, 4, {
    bf_titre: 'Yeswiki : le site officiel',
    bf_url: 'https://yeswiki.net',
    bf_type: '1',
  })
  await createEntry(page, 4, {
    bf_titre: 'Framasoft',
    bf_url: 'https://framasoft.org',
    bf_type: '3',
  })
  await logout(page)
}

/** Files the values `childIds` of a list under its value `parentId`. */
export const nestListValues = (
  listId: string,
  parentId: string,
  ...childIds: string[]
): void => {
  execFileSync(
    'php',
    [
      fileURLToPath(new URL('./lists-wiki.php', import.meta.url)),
      'nest',
      listId,
      parentId,
      ...childIds,
    ],
    { cwd: instanceDir(), encoding: 'utf8' },
  )
}

/** Adds a form with these fields to the wiki under test. */
export const createForm = (
  id: string,
  label: string,
  fields: Record<string, string>[],
): void => {
  execFileSync(
    'php',
    [
      fileURLToPath(new URL('./forms-wiki.php', import.meta.url)),
      'create',
      id,
      label,
      JSON.stringify(fields),
    ],
    { cwd: instanceDir(), encoding: 'utf8' },
  )
}
