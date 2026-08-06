import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { attachConsole, watchConsole } from '../helpers/console'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { replaceEditorTextNewContent } from '../helpers/editor'

test.beforeEach(async () => {
  resetEnv()
})

/**
 * The actions builder is a docked rail beside the editor, not a modal over it -- the
 * shape the form designer established (styles/form-builder.css's .yw-fb).
 *
 * It is a rail rather than an overlay in the DOM as well as visually: it is a sibling of
 * the edited form, not a child, because its selects and inputs configure what is being
 * written and would otherwise be posted along with the page.
 */

const PANEL = '#actions-builder-panel'

const PALETTE = `${PANEL} .actions-builder-panel__palette`
const COMPONENT = `${PANEL} .actions-builder-panel__component`
const BACK = `${PANEL} .actions-builder-panel__back`
/** The rail's one button: write what it holds into the document. */
const SUBMIT = `${PANEL} [data-yw-action-submit]`
/** The tint over the component the rail is on -- an ace marker, not a selection. */
const MARKER = '.ace-body .ace_marker-layer .yw-active-group'

/** Open the rail on its palette. */
const openBuilder = async (page: Page) => {
  await page.locator('.open-actions-builder-btn').first().click()
  await expect(page.locator(PANEL)).toBeVisible()
}

const openEditor = async (page: Page) => {
  await page.goto('/?PagePrincipale/edit')
  await page.waitForFunction(
    () => window['aceditor-body']?.editor !== undefined,
    null,
    { timeout: 15000 },
  )
  // the seeded home page is full of components, and the rail follows the cursor into any
  // of them. Plain text, so that what these tests open is what they opened it with.
  await replaceEditorTextNewContent(page, 'plain text, no action here')
}

const editorContent = (page: Page) =>
  page.evaluate(() => window['aceditor-body'].editor.getValue())

/**
 * Focus the editor and put the caret at a precise spot. A click alone cannot aim at a
 * column, and a `moveCursorTo` alone never focuses -- the rail follows the cursor only
 * once the editor has been used, so both halves are needed.
 */
const putCursorAt = async (page: Page, row: number, column: number) => {
  await page.locator('.ace-body').click()
  await page.evaluate(
    ([r, c]) => {
      window['aceditor-body'].editor.ace.selection.moveCursorTo(r, c)
    },
    [row, column],
  )
}

/** Narrow the palette to one component and pick it, whatever order the groups come in. */
const pickFromPalette = async (page: Page, label: string) => {
  await page.locator(`${PANEL} input[type="text"]`).first().fill(label)
  await page.locator(COMPONENT, { hasText: label }).first().click()
  await expect(page.locator(SUBMIT)).toBeVisible()
}

test('the builder is a rail down the right-hand side', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)

  // closed to begin with: an empty rail on every edit screen is a rail in the way
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

  // it is not posted with the page: the rail is outside the edited form
  await expect(page.locator(`form#ACEditor ${PANEL}`)).toHaveCount(0)

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/**
 * It slides in and out rather than blinking: a panel that appears by itself when the
 * cursor moves has to show where it came from, or it reads as the page jumping.
 */
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

/**
 * What was just placed is what the cursor is now in, and the rail shows the component the
 * cursor is in -- so it stays, on the freshly written one, ready to adjust it.
 */
test('inserting from the rail writes the action and stays on it', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  await openBuilder(page)
  await pickFromPalette(page, 'Bouton')

  await page.locator(SUBMIT).click()

  await expect.poll(() => editorContent(page)).toMatch(/\{\{button/)
  await expect(page.locator(PANEL)).toBeVisible()
  // and it edits it now: the same button updates the code instead of inserting it again
  await expect(page.locator(SUBMIT)).toHaveText(/jour/)
})

/**
 * Choosing what to insert happens in the palette, not in a dropdown: every component is
 * on screen at once, under the heading of the group it belongs to, and searchable. The
 * old menu listed the groups only, and made you choose the action again inside the modal.
 */
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
  // grouped, and every card names itself
  await expect(
    page.locator(`${PANEL} .actions-builder-panel__palette-group`).first(),
  ).toBeVisible()
  await expect(
    page
      .locator(`${COMPONENT} .actions-builder-panel__component-label`)
      .first(),
  ).not.toBeEmpty()

  // the filter narrows it, and says so when it narrows to nothing
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

test('picking from the palette opens its settings, and back returns', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  await openBuilder(page)

  const firstLabel = await page
    .locator(`${COMPONENT} .actions-builder-panel__component-label`)
    .first()
    .textContent()
  await page.locator(COMPONENT).first().click()

  // the palette is gone, and what replaced it is that component's own parameters
  await expect(page.locator(PALETTE)).toHaveCount(0)
  await expect(page.locator(SUBMIT)).toBeVisible()
  // the card named the component, so the panel is on it: no second choice to make here,
  // and the header says which one rather than which drawer it came out of
  await expect(page.locator(`${PANEL} .yw-rail__title`)).toHaveText(
    firstLabel.trim(),
  )
  await expect(
    page.locator(`${PANEL} select[data-yw-action-select]`),
  ).toHaveCount(0)

  // still choosing what to add, so the palette is behind these settings
  await page.locator(BACK).click()
  await expect(page.locator(PALETTE).first()).toBeVisible()
  await expect(
    page
      .locator(`${COMPONENT} .actions-builder-panel__component-label`)
      .first(),
  ).toHaveText(firstLabel.trim())
})

/**
 * A component is edited by putting the cursor in it. There is no button to press first:
 * the rail is a view of whatever the caret is inside of, the way a properties panel is.
 */
test('putting the cursor in a component opens its settings', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  await replaceEditorTextNewContent(
    page,
    '{{button text="Hello" link="https://yeswiki.net"}}\nplain text',
  )

  await expect(page.locator(MARKER)).toHaveCount(0)
  await putCursorAt(page, 0, 12)

  await expect(page.locator(PANEL)).toBeVisible()
  await expect(
    page.locator(PALETTE),
    'it edits that component, it does not offer new ones',
  ).toHaveCount(0)
  await expect(page.locator(`${PANEL} input[value="Hello"]`)).toBeVisible()
  // which component the panel is about is said in the text as well as in the panel
  await expect(page.locator(MARKER)).toHaveCount(1)
  // and there is nothing behind these settings to go back to: the palette is reached
  // from the toolbar, which adds rather than edits
  await expect(page.locator(BACK)).toHaveCount(0)
})

/**
 * Leaving a component is how a change to it is abandoned: parameters reach the document
 * only when the user asks for it, so the rail closing takes them with it.
 */
test('moving the cursor out of a component closes the rail, changing nothing', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  const text = '{{button text="Hello" link="https://yeswiki.net"}}\nplain text'
  await replaceEditorTextNewContent(page, text)

  await putCursorAt(page, 0, 12)
  await page
    .locator(`${PANEL} input[value="Hello"]`)
    .fill('Edited but never asked for')
  await putCursorAt(page, 1, 4)

  await expect(page.locator(PANEL)).toBeHidden()
  await expect(
    page.locator(MARKER),
    'and the text is no longer marked',
  ).toHaveCount(0)
  expect(await editorContent(page), 'nothing was written on the way out').toBe(
    text,
  )
})

test('moving from one component to the next swaps the rail onto it', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  await replaceEditorTextNewContent(
    page,
    [
      '{{button text="Hello" link="https://yeswiki.net"}}',
      '{{button text="Farewell" link="https://yeswiki.net"}}',
    ].join('\n'),
  )

  await putCursorAt(page, 0, 12)
  await expect(page.locator(`${PANEL} input[value="Hello"]`)).toBeVisible()

  await putCursorAt(page, 1, 12)

  await expect(page.locator(`${PANEL} input[value="Farewell"]`)).toBeVisible()
  await expect(page.locator(`${PANEL} input[value="Hello"]`)).toHaveCount(0)
})

/**
 * The toolbar button only ever adds. With the caret inside a component the new one cannot
 * go where the caret is -- a `{{...}}` tag written inside another one's tag corrupts both
 * -- so it goes on the line below instead.
 */
test('the toolbar button adds a component after the one the cursor is in', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  const existing = '{{button text="Hello" link="https://yeswiki.net"}}'
  await replaceEditorTextNewContent(page, existing)
  await putCursorAt(page, 0, 12)
  await expect(page.locator(PANEL)).toBeVisible()

  await page.locator('.open-actions-builder-btn').first().click()

  await expect(
    page.locator(PALETTE).first(),
    'the button offers components, it does not edit',
  ).toBeVisible()
  await expect(
    page.locator(MARKER),
    'and it is on none of them yet',
  ).toHaveCount(0)
  await pickFromPalette(page, 'Bouton')
  await page.locator(SUBMIT).click()

  await expect
    .poll(async () => (await editorContent(page)).split('\n').length)
    .toBeGreaterThan(1)
  const lines = (await editorContent(page)).split('\n')
  expect(lines[0], 'the component the cursor was in is untouched').toBe(
    existing,
  )
  expect(lines[1], 'the new one is written after it').toMatch(/^\{\{button/)
})

/**
 * A wrapping component is written as two tags, and the closing one is not a component of
 * its own: its only parameter is the name of what it closes. Landing in it therefore opens
 * the tag it closes -- the nearest one above that is still open, so that a section inside a
 * section resolves to the one it really belongs to.
 */
test('a closing tag opens the component it closes', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  await replaceEditorTextNewContent(
    page,
    [
      '{{section height="111px"}}',
      '{{section height="222px"}}',
      'du texte',
      '{{end elem="section"}}',
      '{{end elem="section"}}',
    ].join('\n'),
  )

  // the first `end` closes the inner section...
  await putCursorAt(page, 3, 5)
  await expect(page.locator(`${PANEL} input[value="222px"]`)).toBeVisible()
  // ...and the tint is on that one, several lines from where the caret is
  const inner = await page.locator(MARKER).boundingBox()

  await putCursorAt(page, 4, 5)
  await expect(page.locator(`${PANEL} input[value="111px"]`)).toBeVisible()
  const outer = await page.locator(MARKER).boundingBox()
  expect(outer.y, 'the outer section is the one above').toBeLessThan(inner.y)
})

test('updating from a closing tag rewrites the opening one', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditor(page)
  await replaceEditorTextNewContent(
    page,
    ['{{section height="111px"}}', 'du texte', '{{end elem="section"}}'].join(
      '\n',
    ),
  )

  await putCursorAt(page, 2, 5)
  await page.locator(`${PANEL} input[value="111px"]`).fill('999px')
  await page.locator(SUBMIT).click()

  await expect
    .poll(async () => (await editorContent(page)).split('\n')[0])
    .toContain('999px')
  const lines = (await editorContent(page)).split('\n')
  expect(lines, 'and rewrites nothing else').toHaveLength(3)
  expect(lines[2], 'the closing tag is left alone').toBe(
    '{{end elem="section"}}',
  )
})

test('a bazar entry form gets the same rail', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?BazaR&view=saisir&action=saisir_fiche&id=2')
  await page.waitForSelector('.aceditor-container', { timeout: 15000 })

  await expect(page.locator('.yw-designer__canvas')).toHaveCount(1)
  await expect(page.locator(PANEL)).toHaveCount(1)
  await expect(page.locator(`form#formulaire ${PANEL}`)).toHaveCount(0)
})
