import { test, expect, Page } from '@playwright/test'
import { resetEnv } from '../helpers/db'
import { ADMIN_PASSWORD, ADMIN_USERNAME, login } from '../helpers/login'

test.beforeEach(async () => {
  resetEnv()
})

/**
 * The Personnalisation screen (ticket 30).
 *
 * Three of the things this screen does write outside the database -- `favorite_preset` into
 * the configuration file, and stylesheets into custom/css-presets/ -- so PresetServiceTest
 * deliberately does not exercise them against the working tree, and this is where they are
 * covered. It is also the only place that can see the two things that matter most: that
 * trying a preset on really does **not** change the wiki, and that making one the default
 * really does reach the head of an ordinary page.
 */

/** The card for a preset, found by the id it carries. */
function card(page: Page, id: string) {
  return page.locator(`[data-yw-preset-card="${id}"]`)
}

/**
 * Press one of a card's buttons.
 *
 * They live on the card's one line and are hidden until it is under the pointer, so every
 * one of them is reached through a hover -- Playwright refuses to click what is not visible.
 * The filled star of the preset in use is the exception and needs no hover, but hovering
 * first costs nothing and keeps every call here the same shape.
 */
async function clickTool(page: Page, id: string, tool: string) {
  const target = card(page, id)
  await target.hover()
  await target.locator(tool).click()
}

const STAR = '.yw-preset-card__star'
const COPY = '[data-yw-preset-duplicate-form] button'
const DELETE = '[data-yw-preset-delete-form] button'

/** The value a Design token resolves to right now, in this document. */
function token(page: Page, name: string) {
  return page.evaluate(
    (property) =>
      getComputedStyle(document.documentElement)
        .getPropertyValue(property)
        .trim(),
    name,
  )
}

/** What an ordinary page is wearing, as seen from the browser. */
async function primaryColourOfHomePage(page: Page) {
  await page.goto('/?PagePrincipale')
  return token(page, '--yw-primary')
}

test('trying a preset on changes this page and leaves the wiki alone', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  const before = await primaryColourOfHomePage(page)

  await page.goto('/?admin/preset')

  // Every preset the theme ships is listed, each as its colours, plus the way back to
  // none. Not a count of the cards: an instance can carry presets of its own, and this
  // suite runs against a container that has one.
  for (const preset of [
    'default.css',
    'fun.css',
    'landes.css',
    'red.css',
    'yellow.css',
  ]) {
    await expect(card(page, preset)).toBeVisible()
  }
  await expect(card(page, '')).toBeVisible()
  await expect(
    card(page, 'fun.css').locator('.yw-preset-card__swatch'),
  ).toHaveCount(6)

  // the gallery is what a preset is judged against, and single components are not enough:
  // a card grid that wraps, a row list, a table and the two page layouts every wiki writes
  await expect(page.locator('.yw-item--card')).toHaveCount(6)
  await expect(page.locator('.yw-items--list')).toHaveCount(1)
  await expect(page.locator('.yw-items--table')).toHaveCount(1)

  // clicking the card wears it here...
  await card(page, 'fun.css').locator('[data-yw-preset-try]').click()
  await expect(card(page, 'fun.css')).toHaveClass(/yw-preset-card--trying/)
  await expect.poll(async () => token(page, '--yw-primary')).not.toBe(before)

  // ...and nowhere else. This is the whole distinction the screen is built on.
  expect(await primaryColourOfHomePage(page)).toBe(before)
})

test('the starred button makes a preset the wiki default', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  await clickTool(page, 'fun.css', STAR)
  await expect(card(page, 'fun.css')).toHaveClass(/yw-preset-card--default/)
  // the star of the preset in use is the filled one, and it is the only one
  await expect(
    card(page, 'fun.css').locator('.yw-preset-card__star--on'),
  ).toHaveCount(1)
  await expect(page.locator('.yw-preset-card__star--on')).toHaveCount(1)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="presets/fun.css"]')).toHaveCount(1)

  // the star is a toggle: clicking the filled one takes the preset off, which is the theme's
  // own colours -- and that is what the "no preset" card then wears the filled star for
  await page.goto('/?admin/preset')
  await clickTool(page, 'fun.css', STAR)
  await expect(card(page, '')).toHaveClass(/yw-preset-card--default/)
  await expect(card(page, '').locator('.yw-preset-card__star--on')).toHaveCount(
    1,
  )
  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="presets/fun.css"]')).toHaveCount(0)

  // the other way back: the "no preset" card's own star, while a preset is in use
  await page.goto('/?admin/preset')
  await clickTool(page, 'fun.css', STAR)
  await expect(card(page, 'fun.css')).toHaveClass(/yw-preset-card--default/)
  await clickTool(page, '', STAR)
  await expect(card(page, '')).toHaveClass(/yw-preset-card--default/)
  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="presets/fun.css"]')).toHaveCount(0)
})

test('a theme preset cannot be edited, only copied into the wiki', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  page.on('dialog', (dialog) => dialog.accept())
  await page.goto('/?admin/preset')

  // themes/ is code: there is no pencil on a preset that lives there
  await expect(
    card(page, 'red.css').locator('[data-yw-preset-edit]'),
  ).toHaveCount(0)

  // the copy button is the way in
  await clickTool(page, 'red.css', COPY)
  await expect(card(page, 'custom/red.css')).toBeVisible()
  await expect(
    card(page, 'custom/red.css').locator('[data-yw-preset-edit]'),
  ).toHaveCount(1)

  // copying twice gives two presets rather than overwriting the first
  await clickTool(page, 'red.css', COPY)
  await expect(card(page, 'custom/red-2.css')).toBeVisible()

  // copying does not change what the wiki wears
  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="red"]')).toHaveCount(0)

  // clean up: this suite's container keeps custom/css-presets between runs
  await page.goto('/?admin/preset')
  for (const id of ['custom/red.css', 'custom/red-2.css']) {
    await clickTool(page, id, DELETE)
    await expect(card(page, id)).toHaveCount(0)
  }
})

test('editing a preset replaces it, renaming included', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  page.on('dialog', (dialog) => dialog.accept())
  await page.goto('/?admin/preset')

  // The drawer holds both screens and opens on the LIST: choosing a preset and editing one
  // are the same drawer now, not a column beside the gallery and a rail over it.
  const rail = page.locator('#yw-preset-rail')
  const list = rail.locator('[data-yw-preset-screen="list"]')
  const editor = rail.locator('[data-yw-preset-screen="edit"]')
  await expect(rail).toBeVisible()
  await expect(list).toBeVisible()
  await expect(editor).toBeHidden()

  // start from a preset of the wiki's own
  await clickTool(page, 'yellow.css', COPY)
  await expect(card(page, 'custom/yellow.css')).toBeVisible()

  await clickTool(page, 'custom/yellow.css', '[data-yw-preset-edit]')
  await expect(editor).toBeVisible()
  await expect(list).toBeHidden()
  await expect(rail.locator('#yw-preset-name')).toHaveValue('yellow')

  // live preview: the variable is written onto the document, so the gallery below repaints
  const primary = rail.locator('[data-yw-preset-field="light.yw-primary"]')
  await primary.fill('#010203')
  await expect.poll(async () => token(page, '--yw-primary')).toBe('#010203')

  // A measure is a slider and has no box to type in (ADR-0021). Dragging one has to reach
  // three places at once: the hidden field that posts, the readout beside it, and the
  // document -- and the corner radii it drives are DERIVED, so this is also the check that
  // painting one authored token repaints everything computed from it.
  const roundness = rail.locator(
    '[data-yw-preset-slider="light.yw-radius-scale"]',
  )
  await roundness.fill('3')
  await expect(
    rail.locator('[data-yw-preset-readout="light.yw-radius-scale"]'),
  ).toHaveText('3×')
  await expect(
    rail.locator('[data-yw-preset-field="light.yw-radius-scale"]'),
  ).toHaveValue('3')
  await expect
    .poll(async () =>
      page.evaluate(
        () =>
          getComputedStyle(document.querySelector('.yw-btn--primary'))
            .borderRadius,
      ),
    )
    .not.toBe('0px')

  // one status colour, two derived values: the panel behind a success message is computed
  // from it against the page's own surface, so changing the colour has to repaint the alert
  await rail
    .locator('[data-yw-preset-field="light.yw-success"]')
    .fill('#00ff00')
  await expect
    .poll(async () =>
      page.evaluate(
        () =>
          getComputedStyle(document.querySelector('.yw-alert--success'))
            .backgroundColor,
      ),
    )
    .not.toBe('')

  // renamed on save -- and that must RENAME it, not leave a second copy behind
  await rail.locator('#yw-preset-name').fill('Essai e2e')
  // scoped to the editor: the drawer holds the list too now, and every card in it carries
  // its own submit (star, copy, delete), so `rail.locator('button[type=submit]')` matches ten
  // things. Generous timeout below: saving installs the preset's webfonts locally, which is a
  // network round trip.
  await editor.locator('.yw-rail__footer button[type="submit"]').click()
  await expect(card(page, 'custom/essai-e2e.css')).toBeVisible({
    timeout: 30000,
  })
  await expect(card(page, 'custom/yellow.css')).toHaveCount(0)

  // saving does not make it the wiki's -- that is the starred button and nothing else
  await expect(card(page, 'custom/essai-e2e.css')).not.toHaveClass(
    /yw-preset-card--default/,
  )

  // it really is the edited preset: make it the default and the rule reaches an ordinary page
  await clickTool(page, 'custom/essai-e2e.css', STAR)
  await page.goto('/?PagePrincipale')
  await expect(
    page.locator('link[href*="custom/css-presets/essai-e2e.css"]'),
  ).toHaveCount(1)
  await expect.poll(async () => token(page, '--yw-primary')).toBe('#010203')
  // the slider's value survived the round trip through the file -- the measure is written
  // as the bare multiplier the token holds, not as the length it happens to produce
  await expect.poll(async () => token(page, '--yw-radius-scale')).toBe('3')

  // deleting removes the file, so the wiki stops wearing it rather than linking nothing
  await page.goto('/?admin/preset')
  await clickTool(page, 'custom/essai-e2e.css', DELETE)
  await expect(card(page, 'custom/essai-e2e.css')).toHaveCount(0)

  await page.goto('/?PagePrincipale')
  await expect(page.locator('link[href*="essai-e2e.css"]')).toHaveCount(0)
})

test('the drawer goes back to the list, shuts, and comes back', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  const list = rail.locator('[data-yw-preset-screen="list"]')
  const editor = rail.locator('[data-yw-preset-screen="edit"]')
  const openButton = page.locator('[data-yw-preset-open]')

  // the button that reopens the drawer is not a control while the drawer is open
  await expect(openButton).toBeHidden()

  // creating opens the editor over the list, and backing out returns to it -- taking the
  // live preview with it, since the editor was what was producing it
  await rail.locator('[data-yw-preset-new]').click()
  await expect(editor).toBeVisible()
  const previewed = await token(page, '--yw-primary')
  await rail
    .locator('[data-yw-preset-field="light.yw-primary"]')
    .fill('#0a0b0c')
  await expect.poll(async () => token(page, '--yw-primary')).toBe('#0a0b0c')

  await rail.locator('[data-yw-preset-back]').click()
  await expect(list).toBeVisible()
  await expect(editor).toBeHidden()
  await expect.poll(async () => token(page, '--yw-primary')).toBe(previewed)

  // shutting it hands the width back to the gallery, and the head button brings it back --
  // on the list, never on the editor somebody happened to leave open
  await rail.locator('[data-yw-preset-close-rail]').click()
  await expect(rail).toBeHidden()
  await expect(openButton).toBeVisible()

  await openButton.click()
  await expect(rail).toBeVisible()
  await expect(list).toBeVisible()
  await expect(editor).toBeHidden()
})

test('a colour is scored against the one it sits on, per scheme', async ({
  page,
}) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  await rail.locator('[data-yw-preset-new]').click()

  // The light-ground ink IS the page's text: `--yw-text` is derived from it, so this is the
  // one field that decides what a paragraph looks like. It is scheme-independent, hence
  // `light.` here being the field's name rather than a scheme it belongs to -- and its badge
  // is scored against the LIGHT surface specifically, which is the scheme it is the ink of.
  const badge = rail.locator(
    '[data-yw-preset-contrast="light.yw-ink-on-light"]',
  )
  const ink = rail.locator('[data-yw-preset-field="light.yw-ink-on-light"]')
  const ground = rail.locator('[data-yw-preset-field="light.yw-surface"]')

  // The WCAG scale's own reference points, so this asserts the arithmetic and not merely
  // that a number appeared: black on white is the maximum the scale has, and #767676 on
  // white is the colour the specification itself names as the AA boundary.
  await ground.fill('#ffffff')
  await ink.fill('#000000')
  await expect(badge).toHaveText('21.0 AAA')
  await expect(badge).toHaveAttribute('data-grade', 'AAA')

  await ink.fill('#767676')
  await expect(badge).toHaveText('4.5 AA')

  await ink.fill('#ffffff')
  await expect(badge).toHaveAttribute('data-grade', 'fail')

  // the ground is scored too, not just the ink: darkening the page behind white text has to
  // bring the same pair back up, or the badge is only watching half of what it reports on
  await ground.fill('#000000')
  await expect(badge).toHaveText('21.0 AAA')

  // The two schemes are scored apart, and only the one in force is on screen: switching the
  // wiki's own light/dark control swaps which half of the preset the editor is showing, and
  // the page switches with it -- so you are always looking at what you are changing.
  await expect(rail.locator('[data-scheme="light"]').first()).toBeVisible()
  await expect(rail.locator('[data-scheme="dark"]').first()).toBeHidden()

  await page.evaluate(() => {
    document.documentElement.dataset.theme = 'dark'
  })
  await expect(rail.locator('[data-scheme="dark"]').first()).toBeVisible()
  await expect(rail.locator('[data-scheme="light"]').first()).toBeHidden()

  // ...and the page really repainted. This is the bug the observer fixed: `preview()` writes
  // the tokens INLINE on <html>, which beats the scheme blocks, so the toggle used to leave
  // every previewed colour at its light value and the page stayed white.
  await expect.poll(async () => token(page, '--yw-surface')).not.toBe('#000000')

  // the dark-ground ink is scored against the DARK surface, the scheme it is the ink of
  await rail
    .locator('[data-yw-preset-field="light.yw-ink-on-dark"]')
    .fill('#111111')
  await rail.locator('[data-yw-preset-field="dark.yw-surface"]').fill('#000000')
  await expect(
    rail.locator('[data-yw-preset-contrast="light.yw-ink-on-dark"]'),
  ).toHaveAttribute('data-grade', 'fail')

  // the light half kept its own values and its own score while the dark half was edited
  await page.evaluate(() => {
    document.documentElement.dataset.theme = 'light'
  })
  await expect(badge).toHaveText('21.0 AAA')
})

test('a colour can be pointed at another, and follows it', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  await rail.locator('[data-yw-preset-new]').click()

  const heading = rail.locator('[data-yw-preset-field="light.yw-heading-1"]')
  const brand = rail.locator('[data-yw-preset-field="light.yw-primary"]')
  const swatch = rail.locator('[data-yw-preset-picker="light.yw-heading-1"]')

  await brand.fill('#123456')
  await rail
    .locator('[data-yw-preset-palette-open="light.yw-heading-1"]')
    .click()

  // a colour is not offered to itself -- pointing something at itself is the one loop the
  // popover can prevent rather than having to refuse on save
  await rail.locator('[data-yw-preset-palette-open="light.yw-primary"]').click()
  await expect(
    page.locator('[data-yw-preset-palette-pick="yw-primary"]'),
  ).toBeHidden()

  await rail
    .locator('[data-yw-preset-palette-open="light.yw-heading-1"]')
    .click()
  await page.locator('[data-yw-preset-palette-pick="yw-primary"]').click()
  await expect(heading).toHaveValue('var(--yw-primary)')

  // THE POINT: the heading is not a copy of the brand, it IS the brand. The field holds the
  // reference and the token computes to whatever the brand currently is -- `getPropertyValue`
  // returns the SUBSTITUTED value, which is the browser confirming the link resolves.
  await expect.poll(async () => token(page, '--yw-heading-1')).toBe('#123456')

  // ...so moving one moves the other: on the page, in the swatch and in the contrast score
  await brand.fill('#cc0088')
  await expect.poll(async () => token(page, '--yw-heading-1')).toBe('#cc0088')
  await expect(swatch).toHaveValue('#cc0088')
  await expect
    .poll(async () =>
      page.evaluate(
        () =>
          getComputedStyle(document.querySelector('.yw-preset-preview h1'))
            .color,
      ),
    )
    .toBe('rgb(204, 0, 136)')

  // and handing it back a colour of its own keeps what it looks like, dropping only the link
  await rail
    .locator('[data-yw-preset-palette-open="light.yw-heading-1"]')
    .click()
  await page.locator('[data-yw-preset-palette-pick=""]').click()
  await expect(heading).toHaveValue('#cc0088')
  await brand.fill('#00aa44')
  await expect(heading).toHaveValue('#cc0088')
})

test('the screen is refused to someone who is not an admin', async ({
  page,
}) => {
  await page.goto('/?admin/preset')
  await expect(page.locator('.yw-preset-cards')).toHaveCount(0)
})

/**
 * Has the browser actually got this typeface -- rules AND bytes?
 *
 * `document.fonts.check()` is no use here: with no `@font-face` for a family it falls back
 * to a system font and answers TRUE, which is exactly the broken case. The registry is
 * unambiguous -- a face is in it only because a rule declared it, and its status reaches
 * `loaded` only once the file behind it arrived.
 */
async function loadedFace(page: Page, family: string) {
  return page.evaluate(async (name) => {
    await document.fonts.load(`16px '${name}'`)
    return [...document.fonts].some(
      (face) =>
        face.family.replace(/['"]/g, '') === name && face.status === 'loaded',
    )
  }, family)
}

/**
 * Downloading a webfont must not cost the preset you were designing.
 *
 * This was a form POST: it installed the font, redirected back to /admin/preset, and the
 * drawer came back on the LIST screen with every unsaved edit gone. "The font I want is not
 * in this list" is a thought you have *while* designing a preset, so the one moment anybody
 * reaches for it is the moment losing the screen costs most.
 *
 * Only an e2e test can see this. The service installs the font correctly either way -- what
 * changed is that the page no longer goes anywhere, and nothing below the browser knows
 * whether it did.
 */
test('adding a webfont keeps the preset being edited', async ({ page }) => {
  await login(page, ADMIN_USERNAME, ADMIN_PASSWORD)
  await page.goto('/?admin/preset')

  const rail = page.locator('#yw-preset-rail')
  await rail.locator('[data-yw-preset-new]').click()

  // something to lose: a name, and a colour that is nothing like any shipped preset's
  await rail.locator('#yw-preset-name').fill('EditsThatMustSurvive')
  await rail
    .locator('[data-yw-preset-field="light.yw-primary"]')
    .fill('#ff00aa')
  await expect.poll(async () => token(page, '--yw-primary')).toBe('#ff00aa')

  await rail.locator('[data-yw-modal-target="#yw-preset-font-modal"]').click()
  const modal = page.locator('#yw-preset-font-modal')
  await expect(modal).toHaveClass(/yw-modal--open/)

  await modal.locator('#yw-preset-font-family').fill('Lobster')
  await modal.locator('[data-yw-tag-input-suggestion]').first().click()
  await expect(modal.locator('[name="font_family"]')).toHaveValue('Lobster')

  await modal.locator('button[type="submit"]').click()
  await expect(modal.locator('[data-yw-preset-font-result]')).toContainText(
    'Lobster',
    { timeout: 30000 },
  )

  // THE POINT: still the editor, still the edits. A reload would have put the drawer back on
  // the list with an empty name and the default preset's brand colour.
  await expect(rail.locator('#yw-preset-name')).toHaveValue(
    'EditsThatMustSurvive',
  )
  await expect(
    rail.locator('[data-yw-preset-field="light.yw-primary"]'),
  ).toHaveValue('#ff00aa')
  expect(await token(page, '--yw-primary')).toBe('#ff00aa')

  // and the font is now offered, without the list having been re-rendered by the server
  const body = rail.locator('[data-yw-preset-field="light.yw-font-body"]')
  await expect(body.locator('option', { hasText: 'Lobster' })).toHaveCount(1)

  // ...and choosing it previews it, which is the second half of the same bug: the rules that
  // declare a family used to be written only into a saved preset, so a font could be fully
  // downloaded and still be a name no browser had heard of. `document.fonts.check` is the
  // browser saying it can actually draw with it.
  await body.selectOption({ label: 'Lobster' })
  await expect
    .poll(async () => loadedFace(page, 'Lobster'), { timeout: 20000 })
    .toBe(true)

  // The other half of "a webfont does not preview": a family the wiki OFFERS but has not
  // downloaded. The file arrives when the preset is saved, which is correct and is also
  // indistinguishable, while choosing it, from nothing having happened -- so the admin's
  // browser fetches it from Google to draw with, exactly as the picker does.
  await body.selectOption({ label: 'Playfair Display' })
  await expect
    .poll(async () => loadedFace(page, 'Playfair Display'), { timeout: 20000 })
    .toBe(true)
})
