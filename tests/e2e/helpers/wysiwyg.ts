import { Locator, Page } from '@playwright/test'

/**
 * The wysiwyg editor's surface, for tests that mean "the thing being written".
 *
 * What replaces a caret here: a component is a widget, not text, so it is not landed in --
 * it is clicked, and clicking anything else is how it is left. The rails behave exactly as
 * they did in the source editor; only the gesture that opens them is different.
 */

/** The editable document. */
export const surface = (page: Page): Locator =>
  page.locator('.vditor-wysiwyg .vditor-reset')

/** Every component widget in it -- one per `{{tag}}`, wrappers included. */
export const components = (page: Page): Locator =>
  page.locator('.vditor-wysiwyg .yw-component')

/** The widgets of one kind of component, `{{attach}}` say. */
export const componentsNamed = (page: Page, name: string): Locator =>
  page.locator(`.vditor-wysiwyg .yw-component[data-yw-component="${name}"]`)

/** The widget the rail is open on: the wysiwyg answer to the source editor's marker. */
export const openedComponent = (page: Page): Locator =>
  page.locator('.vditor-wysiwyg .yw-component--editing')

/** Put the caret in a paragraph, which is also how every rail is dismissed. */
export const clickText = async (page: Page, text: string) => {
  await surface(page).locator('p', { hasText: text }).first().click()
}

/** A link in the document. Clicking one opens the link rail rather than following it. */
export const link = (page: Page, text: string): Locator =>
  surface(page).getByRole('link', { name: text })

/** The wysiwyg toolbar's button for one of the wiki's own items. */
export const toolbarButton = (page: Page, type: string): Locator =>
  page.locator(`.vditor-toolbar [data-type="${type}"]`)
