import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { attachConsole, watchConsole } from '../helpers/console'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { editorReady, editorText, openEditorWith } from '../helpers/editor'
import {
  clickText,
  components,
  openedComponent,
  toolbarButton,
} from '../helpers/wysiwyg'

test.beforeEach(async () => {
  resetEnv()
})

/** The actions builder is a docked rail beside the editor, not a modal over it -- the shape the form designer established (styles/yw-admin.css's .yw-fb). */

const PANEL = '#actions-builder-panel'

const PALETTE = `${PANEL} .actions-builder-panel__palette`
const COMPONENT = `${PANEL} .actions-builder-panel__component`
const BACK = `${PANEL} .actions-builder-panel__back`
/** The panel of settings, which is what replaces the palette once a component is picked. */
const SETTINGS = `${PANEL} .action-parameters-container`

/** Open the rail on its palette. */
const openBuilder = async (page: Page) => {
  await toolbarButton(page, 'yw-component').click()
  await expect(page.locator(PANEL)).toBeVisible()
}

const openEditor = (page: Page) =>
  openEditorWith(page, 'plain text, no action here')

/** Narrow the palette to one component and pick it, whatever order the groups come in. */
const pickFromPalette = async (page: Page, label: string) => {
  await page.locator(`${PANEL} input[type="text"]`).first().fill(label)
  await page.locator(COMPONENT, { hasText: label }).first().click()
  await expect(page.locator(SETTINGS)).toBeVisible()
}

test('the builder is a rail down the right-hand side', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)

  await expect(page.locator(PANEL)).toBeHidden()

  await openBuilder(page)

  const panel = await page.locator(PANEL).boundingBox()
  const viewport = page.viewportSize()
  expect(
    panel.x + panel.width,
    'it is docked against the right edge',
  ).toBeGreaterThan(viewport.width - 2)
  expect(
    panel.x,
    'and it is a rail, not a sheet over everything',
  ).toBeGreaterThan(viewport.width / 2)

  await expect(page.locator(`form#ACEditor ${PANEL}`)).toHaveCount(0)

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/** It slides in and out rather than blinking: a panel that appears by itself when a component is clicked has to show where it came from, or it reads as the page jumping. */
test('closing the rail slides it back off the right edge', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  const offset = () =>
    page.evaluate(
      () =>
        new DOMMatrix(
          getComputedStyle(document.getElementById('actions-builder-panel'))
            .transform,
        ).m41,
    )

  expect(await offset(), 'parked off-screen while closed').toBeGreaterThan(0)
  await openBuilder(page)
  await expect.poll(offset, { message: 'slid into place' }).toBe(0)

  await page.locator(`${PANEL} .yw-close`).click()

  await expect(page.locator(PANEL)).toBeHidden()
  await expect
    .poll(offset, { message: 'and back out again' })
    .toBeGreaterThan(0)
})

/** What was just placed is what the rail is now on -- it stays open on the component it wrote, ready to adjust it, and the document has it before anything is adjusted. */
test('inserting from the rail writes the action and stays on it', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  await openBuilder(page)
  await pickFromPalette(page, 'Bouton')

  await expect.poll(() => editorText(page)).toMatch(/\{\{button/)
  await expect(page.locator(PANEL)).toBeVisible()
  await expect(page.locator(SETTINGS)).toBeVisible()
  await expect(
    components(page),
    'and it is drawn where it was written',
  ).toHaveCount(1)
})

/** ...and it is shown, which on a long page it was not. */
test('what the palette writes is scrolled into view', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    Array.from({ length: 60 }, (unused, i) => `ligne ${i}`).join('\n\n'),
  )
  await page.evaluate(() => window.scrollTo(0, 0))

  await openBuilder(page)
  await pickFromPalette(page, 'Bouton')

  await expect
    .poll(
      async () => {
        const box = await components(page).first().boundingBox()
        const viewport = page.viewportSize()

        return box !== null && box.y >= 0 && box.y < viewport.height
      },
      { message: 'the component the page did not have is on screen' },
    )
    .toBe(true)
  await expect(components(page).first().locator('iframe')).toHaveCount(1)
})

/** A page being created is an empty document: no blocks, and no last child to insert after. */
test('a component can be added to a page that has nothing in it yet', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)

  await page.goto('/?UnePageVide/edit&newpage=1')
  await editorReady(page)
  await openBuilder(page)
  await pickFromPalette(page, 'Bouton')
  await expect.poll(() => editorText(page)).toMatch(/^\{\{button/)
  await expect(components(page)).toHaveCount(1)

  await page.goto('/?UneAutrePageVide/edit&newpage=1')
  await editorReady(page)
  await openBuilder(page)
  await pickFromPalette(page, 'Section')
  await expect.poll(() => editorText(page)).toMatch(/^\{\{section/)
  await expect
    .poll(() => editorText(page), { message: 'both halves of it' })
    .toMatch(/\{\{end elem="section"\}\}/)

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/** Choosing what to insert happens in the palette, not in a dropdown: every component is on screen at once, under the heading of the group it belongs to, and searchable. */
test('the rail opens on a palette of every component', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  await openBuilder(page)

  await expect(page.locator(PALETTE).first()).toBeVisible()
  const total = await page.locator(COMPONENT).count()
  expect(
    total,
    'a wiki ships far more than a handful of components',
  ).toBeGreaterThan(20)
  await expect(
    page.locator(`${PANEL} .actions-builder-panel__palette-group`).first(),
  ).toBeVisible()
  await expect(
    page
      .locator(`${COMPONENT} .actions-builder-panel__component-label`)
      .first(),
  ).not.toBeEmpty()

  await page.locator(`${PANEL} input[type="text"]`).first().fill('syndication')
  await expect
    .poll(async () => page.locator(COMPONENT).count())
    .toBeLessThan(total)
  await page
    .locator(`${PANEL} input[type="text"]`)
    .first()
    .fill('no component is called this')
  await expect(page.locator(COMPONENT)).toHaveCount(0)
  await expect(
    page.locator(`${PANEL} .actions-builder-panel__empty`),
  ).toBeVisible()
})

test('picking from the palette opens its settings', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  await openBuilder(page)

  const firstLabel = await page
    .locator(`${COMPONENT} .actions-builder-panel__component-label`)
    .first()
    .textContent()
  await page.locator(COMPONENT).first().click()

  await expect(page.locator(PALETTE)).toHaveCount(0)
  await expect(page.locator(SETTINGS)).toBeVisible()
  await expect(page.locator(`${PANEL} .yw-rail__title`)).toHaveText(
    firstLabel.trim(),
  )
  await expect(
    page.locator(`${PANEL} select[data-yw-action-select]`),
  ).toHaveCount(0)

  await expect(page.locator(BACK)).toHaveCount(0)
  await expect
    .poll(() => editorText(page), { message: 'the page holds it now' })
    .toMatch(/\{\{/)
})

/** A component is edited by clicking it. */
test('clicking a component opens its settings', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    '{{button text="Hello" link="https://yeswiki.net"}}\n\nplain text',
  )

  await expect(openedComponent(page)).toHaveCount(0)
  await components(page).first().click()

  await expect(page.locator(PANEL)).toBeVisible()
  await expect(
    page.locator(PALETTE),
    'it edits that component, it does not offer new ones',
  ).toHaveCount(0)
  await expect(page.locator(`${PANEL} input[value="Hello"]`)).toBeVisible()
  await expect(openedComponent(page)).toHaveCount(1)
  await expect(page.locator(BACK)).toHaveCount(0)
})

/** Leaving a component is how a change to it is abandoned: parameters reach the document only when the user asks for it, so the rail closing takes them with it. */
test('clicking away from a component closes the rail, changing nothing', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const text =
    '{{button text="Hello" link="https://yeswiki.net"}}\n\nplain text'
  await openEditorWith(page, text)

  await components(page).first().click()
  await page
    .locator(`${PANEL} input[value="Hello"]`)
    .fill('Edited but never asked for')
  await clickText(page, 'plain text')

  await expect(page.locator(PANEL)).toBeHidden()
  await expect(
    openedComponent(page),
    'and the component is no longer marked',
  ).toHaveCount(0)
  expect(
    (await editorText(page)).trim(),
    'nothing was written on the way out',
  ).toBe(text)
})

test('clicking from one component to the next swaps the rail onto it', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    [
      '{{button text="Hello" link="https://yeswiki.net"}}',
      '',
      '{{button text="Farewell" link="https://yeswiki.net"}}',
    ].join('\n'),
  )

  await components(page).first().click()
  await expect(page.locator(`${PANEL} input[value="Hello"]`)).toBeVisible()

  await components(page).nth(1).click()

  await expect(page.locator(`${PANEL} input[value="Farewell"]`)).toBeVisible()
  await expect(page.locator(`${PANEL} input[value="Hello"]`)).toHaveCount(0)
})

/** The toolbar button only ever adds, and what it adds goes after the block the caret is in -- not at the end of the page, which is where "somewhere else" would put it. */
test('the toolbar button adds a component after the block the caret is in', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(page, ['first line', '', 'last line'].join('\n'))
  await clickText(page, 'first line')

  await openBuilder(page)
  await expect(
    page.locator(PALETTE).first(),
    'the button offers components, it does not edit',
  ).toBeVisible()
  await expect(
    openedComponent(page),
    'and it is on none of them yet',
  ).toHaveCount(0)

  await pickFromPalette(page, 'Bouton')

  await expect.poll(() => editorText(page)).toMatch(/\{\{button/)
  const lines = (await editorText(page))
    .split('\n')
    .filter((line) => line.trim() !== '')
  expect(lines[0], 'the block the caret was in is untouched').toBe('first line')
  expect(lines[1], 'the new one is written after it').toMatch(/^\{\{button/)
  expect(lines[2], 'and the rest of the page follows it').toBe('last line')
})

/** A wrapping component is written as two tags, and the closing one is not a component of its own: its only parameter is the name of what it closes. */
test('a closing tag opens the component it closes', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    [
      '{{section bgcolor="#ff0000"}}',
      '',
      '{{section bgcolor="#00ff00"}}',
      '',
      'du texte',
      '',
      '{{end elem="section"}}',
      '',
      '{{end elem="section"}}',
    ].join('\n'),
  )

  const bgcolor = page.locator(`${PANEL} .yw-form-group.color input`).first()

  await components(page).nth(2).click()
  await expect(bgcolor).toHaveValue('#00ff00')
  const inner = await openedComponent(page).boundingBox()

  await components(page).nth(3).click()
  await expect(bgcolor).toHaveValue('#ff0000')
  const outer = await openedComponent(page).boundingBox()
  expect(outer.y, 'the outer section is the one above').toBeLessThan(inner.y)
})

test('updating from a closing tag rewrites the opening one', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    [
      '{{section bgcolor="#ff0000"}}',
      '',
      'du texte',
      '',
      '{{end elem="section"}}',
    ].join('\n'),
  )

  await components(page).nth(1).click()
  await page
    .locator(`${PANEL} .yw-form-group.range input:visible`)
    .first()
    .fill('42')

  await expect
    .poll(async () => (await editorText(page)).split('\n')[0])
    .toContain('minheight="42"')
  const lines = (await editorText(page))
    .split('\n')
    .filter((line) => line.trim() !== '')
  expect(lines, 'and rewrites nothing else').toHaveLength(3)
  expect(lines[2], 'the closing tag is left alone').toBe(
    '{{end elem="section"}}',
  )
})

test('a bazar entry form gets the same rail', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=2')
  await page.waitForSelector('.vditor-wysiwyg', { timeout: 15000 })

  await expect(page.locator('.yw-designer__canvas')).toHaveCount(1)
  await expect(page.locator(PANEL)).toHaveCount(1)
  await expect(page.locator(`form#formulaire ${PANEL}`)).toHaveCount(0)
})
