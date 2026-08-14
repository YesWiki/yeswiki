import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { attachConsole, watchConsole } from '../helpers/console'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'
import { editorReady, editorText, openEditorWith } from '../helpers/editor'
import { components, toolbarButton } from '../helpers/wysiwyg'

test.beforeEach(async () => {
  resetEnv()
})

/**
 * Pointing a list at a form.
 *
 * Which form a list shows is the first thing it has to be told, and for a while it could
 * not be told at all: the palette used to draw that select itself, for whatever group the
 * action belonged to (`needFormField` in `docs/actions/entrylist.yaml`), and components
 * declared in PHP have no group to inherit it from. It is a setting now -- one whose type
 * is `form-list` -- which is also what lets a Presentation ask for it only when a form is
 * what it lists, and not when the source is a feed.
 */

const PANEL = '#actions-builder-panel'
const SETTINGS = `${PANEL} .action-parameters-container`

/** The form picker, wherever in the panel the component put it. */
const formPicker = (page: Page) =>
  page.locator(`${PANEL} .yw-form-group.form-list`)

/** The forms it currently names -- it takes several, so this is a list. */
const pickedForms = async (page: Page) =>
  (await formPicker(page).locator('.vs__selected').allInnerTexts()).map((t) =>
    t.trim(),
  )

/** Add one to the selection, by the name a webmaster knows it by. */
const pickForm = async (page: Page, label: string) => {
  await formPicker(page).locator('.vs__search').click()
  await page.locator('.vs__dropdown-option', { hasText: label }).first().click()
}

/** One of the selects made of the chosen form's fields. */
const fieldSelect = (page: Page) =>
  page.locator(`${PANEL} .yw-form-group.form-field select`).first()

test("a list offers this wiki's forms, and writes the one picked", async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?PagePrincipale/edit')
  await editorReady(page)

  await toolbarButton(page, 'yw-component').click()
  await expect(page.locator(PANEL)).toBeVisible()
  await page.locator(`${PANEL} input[type="text"]`).first().fill('Cartes')
  await page
    .locator(`${PANEL} .actions-builder-panel__component`)
    .first()
    .click()
  await expect(page.locator(SETTINGS)).toBeVisible()

  await formPicker(page).locator('.vs__search').click()
  const offered = page.locator('.vs__dropdown-option')
  await expect(
    offered,
    'the seeded wiki has forms, and the picker lists them',
  ).not.toHaveCount(0)
  await expect(offered.first()).not.toHaveText(/^\s*$/)

  await offered.filter({ hasText: 'Agenda' }).first().click()
  await expect
    .poll(() => editorText(page), {
      message: 'the form is written into the tag',
    })
    .toContain('{{entrylist id="2" template="card"}}')

  // ...and a list can be pointed at several at once, which `id="2,1"` always meant and no
  // single select could say: the hint under the box used to tell you to type it by hand
  await pickForm(page, 'Annuaire')
  await expect
    .poll(() => editorText(page), { message: 'both forms are written' })
    .toContain('id="1,2"')
  expect(
    await pickedForms(page),
    'and what it says is what it shows, in one order',
  ).toEqual(['Annuaire', 'Agenda'])

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/**
 * ...and the fields of that form are what every other setting is made of. A field select
 * with nothing in it is the same bug one step further on, and the only thing that fills
 * one is the form having been fetched -- which the form picker is what asks for.
 */
test('reopening a list shows its form, and the fields to map', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    '{{entrylist id="2" template="card" displayfields="title=bf_nom"}}',
  )
  await components(page).first().click()
  await expect(page.locator(SETTINGS)).toBeVisible()

  await expect
    .poll(() => pickedForms(page), {
      message: 'the rail opens on the form the tag names',
    })
    .toEqual(['Agenda'])
  await expect
    .poll(() => fieldSelect(page).locator('option').count(), {
      message: "and offers that form's fields",
    })
    .toBeGreaterThan(1)

  // the mapping read out of the tag survives a change to something else. Every slot of it
  // is a field select, and one that mounts before its form has arrived reports itself
  // empty -- which used to empty the mapping the tag had just been read for.
  await page
    .locator(`${PANEL} .yw-form-group.number input:visible`)
    .first()
    .fill('4')
  await expect
    .poll(() => editorText(page), { message: 'the mapping is still there' })
    .toContain('displayfields="title=bf_nom"')

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/**
 * A Presentation writes whichever source it is pointed at, and only a form has a form to
 * pick: the picker belongs to the `entrylist` source rather than to the card.
 */
test('picking a feed as the source takes the form picker away', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(page, '{{entrylist id="2" template="card"}}')
  await components(page).first().click()
  await expect(page.locator(SETTINGS)).toBeVisible()
  await expect(formPicker(page)).toBeVisible()

  await page
    .locator(`${PANEL} .yw-form-group.list select`)
    .first()
    .selectOption('syndication')

  await expect(formPicker(page)).toBeHidden()
  await expect
    .poll(() => editorText(page), {
      message: 'and the tag follows the source',
    })
    .toContain('{{syndication template="card"}}')

  // ...and what it offers instead is the feed's own: a summary written for a feed reader
  // is a sentence or a whole article, so a card has to be able to cut it. The action has
  // truncated on a word boundary since long before the presentations; nothing offered the
  // number, so the parameter could only be typed into the tag by hand.
  await page
    .locator(`${PANEL} .yw-form-group.number:visible input`)
    .last()
    .fill('180')
  await expect
    .poll(() => editorText(page), { message: 'the feed can be told to cut it' })
    .toContain('maxchars="180"')

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/**
 * ...and everything else a list can be told, which is the Source's half of a Presentation.
 *
 * A card list shipped able to say which form and which fields, and nothing about WHICH
 * entries: no filter, no limit, no order. Those are `{{entrylist}}` parameters and the
 * palette never offered them, so the only way to a filtered card list was to write the tag
 * by hand.
 */
test('a card list can be filtered, limited and sorted', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(page, '{{entrylist id="2" template="card"}}')
  await components(page).first().click()
  await expect(page.locator(SETTINGS)).toBeVisible()

  // how many
  await page
    .locator(`${PANEL} .yw-form-group.number:visible input`)
    .last()
    .fill('3')
  await expect.poll(() => editorText(page)).toContain('nb="3"')

  // in what order -- on screen, not behind a checkbox: the rail hides nothing now
  await expect(page.locator(`${PANEL} .advanced-params`)).toHaveCount(0)
  await page
    .locator(`${PANEL} .yw-form-group.list:visible select`)
    .filter({ has: page.locator('option[value="desc"]') })
    .first()
    .selectOption('desc')
  await expect.poll(() => editorText(page)).toContain('order="desc"')

  // ...and which ones, as conditions rather than as a string nobody remembers the syntax of
  const query = page.locator(`${PANEL} .query`)
  await expect(query).toBeVisible()
  await query.locator('.btn-add-element').click()
  const condition = query.locator('.inline-form').first()
  await condition.locator('select').first().selectOption('bf_titre')
  await condition.locator('input[type="text"]').first().fill('Bordeaux')
  await expect
    .poll(() => editorText(page), { message: 'the condition is written' })
    .toContain('query="bf_titre=Bordeaux"')

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/**
 * A condition is a row, and it has to read as one.
 *
 * The controls of a composite input were a wrapping flex row, so each was sized by whatever
 * was left on its line: the same field came out a different width on every row, nothing
 * lined up, and the remove button ended up under the last control -- where it reads as
 * "remove the value" rather than "remove this condition".
 */
test('the filter rows line up, with one remove button per row', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(
    page,
    '{{entrylist id="2" template="card" query="bf_titre=Bordeaux|bf_ville!=Lyon"}}',
  )
  await components(page).first().click()
  await expect(page.locator(SETTINGS)).toBeVisible()

  const rows = page.locator(`${PANEL} .query .inline-form`)
  await expect(rows, 'one row per condition').toHaveCount(2)

  const geometry = await rows.evaluateAll((elements) =>
    elements.map((row) => {
      const box = (el: Element) => el.getBoundingClientRect()
      const controls = [...row.children].filter(
        (child) => !child.classList.contains('btn-close-container'),
      )
      const remove = row.querySelector('.btn-close-container')!

      return {
        field: box(controls[0]),
        operator: box(controls[1]),
        values: box(controls[2]),
        remove: box(remove),
      }
    }),
  )

  for (const row of geometry) {
    expect(
      Math.round(row.field.top),
      'a field and the one beside it start on the same line',
    ).toBe(Math.round(row.operator.top))
    expect(Math.round(row.field.width), 'and are the same width').toBe(
      Math.round(row.operator.width),
    )
    expect(
      row.values.top,
      'the value is on the line under them',
    ).toBeGreaterThan(row.field.bottom)
    expect(
      row.remove.left,
      'and the button is beside the row, not under it',
    ).toBeGreaterThan(row.operator.left)
    expect(row.remove.top).toBeLessThan(row.values.top)
  }

  // ...and the rows themselves are the same shape as each other
  expect(Math.round(geometry[0].field.width)).toBe(
    Math.round(geometry[1].field.width),
  )
})

/**
 * The card's own settings, in the order they are read in: what the list is pointed at,
 * what each zone of a card shows, what the card looks like, then which entries to take.
 * Two to a row, and nothing folded away -- the "advanced parameters" box is gone, because
 * a rail that hides half of what a component can do behind a checkbox is one you have to
 * know the answer before you can search.
 */
test('the card settings are laid out in pairs, with nothing hidden', async ({
  page,
}, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(page, '{{entrylist id="2" template="card"}}')
  await components(page).first().click()
  await expect(page.locator(SETTINGS)).toBeVisible()

  // every visible control, grouped by the row it sits on
  const rows = await page.evaluate(() => {
    const byTop: Record<number, string[]> = {}
    document
      .querySelectorAll(
        '#actions-builder-panel .config-cell label, #actions-builder-panel .multi-input-container.field-mapping > * label',
      )
      .forEach((label) => {
        const box = (label as HTMLElement).getBoundingClientRect()
        if (box.height === 0) return
        const top = Math.round(box.top / 10)
        byTop[top] = byTop[top] || []
        const text = (label.textContent || '').trim()
        if (text && !byTop[top].includes(text)) byTop[top].push(text)
      })
    return Object.keys(byTop)
      .map(Number)
      .sort((a, b) => a - b)
      .map((top) => byTop[top].join(' | '))
  })

  expect(rows.slice(0, 9)).toEqual([
    'Ce qui est listé | Choisissez un formulaire',
    'Zone de titre | Zone de sous titre',
    'Zone visuelle | Zone de texte',
    "Zone flottante | Bouton d'action",
    // the seventh zone of a card, on a line of its own
    'Zone de date',
    "Colonnes | Cadrage de l'image",
    'Nombre de fiches par page | Limitation',
    'Trier sur le champ | Ordre',
    'Ordre des fiches | Barre de recherche',
  ])

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})

/** The sixth zone of a card: a button, which is off unless the list asks for one. */
test('a card can carry a button to the entry', async ({ page }, testInfo) => {
  const watcher = watchConsole(page)
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await openEditorWith(page, '{{entrylist id="2" template="card"}}')
  await components(page).first().click()
  await expect(page.locator(SETTINGS)).toBeVisible()

  // by its caption, not by position: the mapping has gained slots twice already
  const cta = page
    .locator(`${PANEL} .multi-input-container.field-mapping .yw-form-group`)
    .filter({ hasText: "Bouton d'action" })
    .locator('select')
  await expect(cta, 'no button until one is asked for').toHaveValue('')
  await cta.selectOption('edit')

  await expect
    .poll(() => editorText(page), {
      message: 'the choice rides in displayfields',
    })
    .toContain('cta=edit')

  await attachConsole(watcher, testInfo)
  expect(watcher.errors(), 'the browser reported errors').toEqual([])
})
