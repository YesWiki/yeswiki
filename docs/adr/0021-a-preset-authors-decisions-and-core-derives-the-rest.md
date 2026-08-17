# A Preset authors decisions; core derives the rest, and measures are multipliers

ADR-0020 gave a Preset 49 tokens and a rule: nothing is computed, a Preset states every value
explicitly. In use that rail was 82 fields — 31 colours authored twice plus 20 lengths typed
by hand — and it had three failures nobody had predicted. Eighteen of the tokens were never a
decision (a hover colour is always the primary nudged toward the ink; the panel behind a
success message is always that green washed into the page), so the rail asked 18 questions
whose answer it already knew. The eleven-step spacing ramp asked for eleven lengths whose
middle six differed by amounts nobody could name, before any of them could be seen. And the
things a webmaster actually asks for — the colour of the top bar, of the footer, of a heading,
how big a heading is, how thick a line is, how heavy a shadow is — could not be set at all;
the top bar in particular was a _theme style_, a second stylesheet picked in a different
screen, whose entire content was three rules repainting it.

We decided that a Preset authors **decisions only**; that core **derives** everything that is
a function of a decision, with `color-mix(in oklab, …)` against the page's own surface and
text so a derived value is correct in both Colour schemes for free; that a **measure is a
multiplier, never a typed length** — three spacing steps **on two axes each** in `rem` (which
_is_ the wiki's base type size) and four unitless scales, all chosen with a slider and no text
box; and that the **chrome and the headings are the Preset's**, not the theme's, with a colour
and a size **per heading level**. The set is 56 authored tokens; the dark scheme restates 22
colours instead of 31.

## Considered Options

- **Keep ADR-0020's "nothing is computed"** — rejected, and this ADR reverses that clause for
  18 properties. Its argument was that a hover colour quietly inherited from somebody else's
  brand is invisible until it is wrong. That argument holds against _inheritance between
  presets_, which is still refused; it does not hold against a value computed from this
  preset's own primary, which cannot belong to anybody else. The observed failure ran the
  other way: the hand-authored derived values were the ones that came out wrong in dark mode,
  because a webmaster had matched `--yw-success-surface` to a light surface and there was
  nothing to keep it in step when the surface flipped.
- **One number per spacing step, or one per step per axis** — one was rejected. Text is wider
  than it is tall: the blank that reads as comfortable beside a word is not the blank that
  reads as comfortable above a line of them, so a single number gives you either squashed
  buttons or loose paragraphs and never both right. Each step is `-y` and `-x`, and every rule
  in core is axis-explicit. That was checkable rather than assumed: all 890 uses of a spacing
  token were a `margin`, a `padding` or a `gap`, so the split is exact — there is no third
  case needing a neutral value, and none is offered.
- **Showing both Colour schemes at once, or only the one in force** — both-at-once was tried
  and rejected. The preview can only ever show one scheme, because the document is in one at a
  time; the other column was therefore a control with no visible effect while you dragged it.
  The editor now shows the half matching the page, and the wiki's own light/dark toggle swaps
  both together — so you edit the dark colours while looking at the dark page. This also fixed
  a real bug: `preview()` writes tokens inline on `<html>`, which beats the scheme blocks, so
  the toggle had silently stopped working on this screen whenever the editor was open. It is
  watched with a `MutationObserver` on `data-theme`, since setting an attribute fires no event
  and `prefers-color-scheme` is not what the toggle changes.
- **Three spacing steps rather than the eleven-step ramp, or one density multiplier over it** —
  the ramp scaled by one number was rejected as a control that cannot express "roomier
  sections, same tight buttons". Three steps replaced 948 call sites, mapped by meaning:
  inside a control (old 1–3), inside a component (4–6), between components (7–11). The cost is
  accepted and is real — `6rem` gaps become `2rem`, `1rem` gaps become `0.75rem`, so page
  rhythm visibly changes on upgrade.
- **`color-contrast()` for `--yw-on-primary`** — rejected, not shipped in browsers. It stays
  authored, and it is the one colour whose correct value a machine could genuinely pick.
- **Composite shadow tokens (`--yw-shadow-sm/md/lg`) rewriting all 60 shadow declarations** —
  rejected. The 60 include insets, glows and text-shadows with hand-tuned geometries; three
  presets would flatten distinctions that are doing a job. Instead `--yw-shadow-strength`
  scales the _alpha_ of `--yw-shadow-color`, which all 60 already consume, so one slider
  reaches every shadow in the wiki and `0` is genuinely flat.
- **Keeping `colored-navbar.css` beside the new navbar tokens** — rejected: two ways to colour
  the bar that can disagree. It is deleted, and the migration gives a wiki that had chosen it
  `--yw-navbar-bg: <its primary>` so the bar does not silently turn white on upgrade. Its
  design flaw is what the tokens fix: it could not restyle the bar's _border_, because that
  was matched to a page background the bar was no longer sitting on.
- **Leaving the preset list on the page beside the gallery** — rejected. The list was a
  230–300px column and the editor a rail over the same screen, so the gallery — the thing a
  preset is judged on — was measured at 502px of a 1600px window, laid out like nothing the
  wiki will ever render, and the two controls for it were in different places. Both moved
  into the one right-hand drawer as two screens of it, and the canvas full-bleeds out of the
  wiki's centred container so the gallery gets the window. The drawer opens on the list;
  shutting it hands the width back.
- **Stacking the light and dark field of a colour, one above the other** — rejected. It made
  every colour two rows tall, so twenty colours were forty rows to scroll and the pair being
  compared never fit on screen with the gallery it was repainting — which is the only reason
  to author the two schemes together at all. They are two columns of one row now.
- **Scoring contrast, and what to score** — each colour that is _ink_ carries a live WCAG 2.1
  ratio against the colour it actually sits on, graded AAA / AA / AA-large / fail, computed in
  the browser so it moves as you drag. The pairing is declared in `PresetService::TOKENS`
  (`contrast`), not guessed in the template: only that table knows `--yw-navbar-text` sits on
  the bar and not on the page. **Per scheme**, because ink that clears AA on a white page can
  fail on the near-black one — the commonest way a hand-authored dark set goes wrong, and
  exactly what one averaged badge would hide. The four status colours are deliberately
  **unscored**: what is read is the derived `--yw-*-text`, so grading the authored colour
  would report a failure on a warning yellow nothing ever draws text in.
- **A read-only text field beside each slider, to preserve unrepresentable values** — rejected.
  It is what made 20 measures into 20 things to type. A hand-written `1.05rem` or `clamp()`
  now snaps to the nearest slider position, which is a real loss and the price of the control
  being a control.

- **Two heading colours and one ramp multiplier, or one colour and one size per level** —
  the pair was rejected. `--yw-heading` / `--yw-heading-sub` plus `--yw-heading-scale` could
  say "bigger titles" but not "my h2 sits too close to my h1", and no level could take a
  colour of its own. Six of each is the largest group in the rail, and that is the trade
  accepted: a heading ramp is the one thing on a wiki people really do set level by level.
  Sizes are `rem`, not `em` — `em` compounds, which is what made the same heading come out
  one size in a page body and another inside a panel.
- **A colour picker that offers the palette: copy the value, or point at the token** —
  copying was rejected. `--yw-heading-1: var(--yw-primary)` is a relationship the file keeps,
  so "my headings are the brand colour" survives the brand moving; a copied hex is twelve
  literals (six levels × two schemes) to re-edit by hand every time it does. Measured first:
  a reference resolves, the derived tier resolves _through_ it, and it follows a later
  redefinition, so `var(--yw-primary)` in the dark block picks the dark primary by itself.
  **A loop, however, computes to black in silence** — no warning, no fallback, nothing a rule
  can notice — so `PresetService::cycleIn()` refuses to save one and names the loop. The
  palette is **curated** (13 of the 22 authored colours): the headings and the chrome are the
  things that point at these rather than the other way round, and all twenty-two would be a
  wall rather than a palette. One shared popover, painted from the live values on open, since
  a copy per field would be forty-four sets of the same swatches to keep in step.
- **Holding the shipped presets to AA** — rejected for four of the five. `fun`'s pale cyan and
  `yellow`'s bright yellow cannot be AA-legible as body-size ink on white at any lightness, so
  the only way to pass is to stop being that colour, which is a decision about YesWiki's
  palettes rather than one a test gets to make. Core and `default` **are** held to it, and were
  fixed: `--yw-secondary` sat at 3.69 and `--yw-heading-sub` at 2.76 on white, so the wiki's own
  h4–h6 failed the check the screen asks webmasters to pass. `--yw-tertiary` lost its contrast
  pair rather than being darkened — it is only ever a fill (a section background, a calendar
  event, a border), so scoring it as ink reported a failure about something that never happens.

- **Laying the rail out as one column, or two fields to a line** — one column was fifty
  controls tall, so the gallery it repaints was never on screen with the control being
  dragged. Tokens now declare a `row` and share a line where the pair is one you compare: a
  colour and the ink on it, a surface and the card on it, success beside danger, and each
  heading's colour beside its own size. The contrast score moved onto the label line, which
  took another twenty rows out. Labels stay long (`Titre 2 — couleur`, `— taille`) rather
  than being shortened to `Taille`: six sliders that announce identically is a worse trade
  than a slightly wider label.

- **One ink per fill, or one pair of inks for all of them** — the pair won. A Preset used to
  author `--yw-on-primary` (the ink on the brand) and `--yw-text-on-dark` (the ink on a ground
  the preset does not choose: a photo, an entry's own colour, a section background an author
  typed), and every other fill just took `--yw-text-inverse` — which flips with the **scheme**
  rather than with the colour underneath it, so light mode put white text on the amber warning
  button. Both are replaced by `--yw-ink-on-light` and `--yw-ink-on-dark`, scheme-independent
  because a light ground is light in both schemes, and core picks the more legible of the two
  per fill. The contrast badge on a fill scores it against the ink it will actually get
  (`contrast => 'auto-ink'`), not a named partner.
- **One ink pair, asked once.** `--yw-text` and `--yw-text-inverse` were authored, and
  authored per scheme — four values expressing one decision, with four chances to disagree.
  They are derived now: the page's ink is whichever of `--yw-ink-on-light` /
  `--yw-ink-on-dark` suits the scheme in force, and the inverse is the other. The same pair
  then answers every fill (`INK_FOR`) and every ground a page author chose. A Preset sets two
  text colours, once, and never per scheme.
- **A `{{section bgcolor="…"}}` gets an ink core chose**, where core can tell: a hex literal is
  measured against the pair, and `var(--yw-primary)` reuses the ink already resolved for that
  fill. Anything else — a `color-mix()`, a keyword, a gradient — is left to the author's
  `class="white"`/`"black"`, which is also what an explicit class continues to override.
  Guessing wrong here is unreadable text on somebody's cover image, so it is not guessed.
- **Google's catalogue is vendored, not queried.** `src/assets/google-fonts.json` is 1951
  family names, 29KB, no metadata — so it cannot go stale in any way that matters and it
  costs no network call to draw a screen. It does two jobs: the font picker filters against
  it (a closed chip list, several families at a time, because a body face and a heading face
  are chosen together), and `installFont()` validates against it. That validation replaced a
  shape regex, which `Opne Sans` passes — a well-formed name Google answers with nothing, so
  the webmaster got a blank failure after a round trip instead of "no such font" before one.
  The list is fetched by the browser on first use of the box rather than inlined: 29KB in the
  head of a screen that is mostly about colours, for something most visits never touch.
- **The picker previews what it offers**, which is the one thing a list of names cannot tell
  you. The suggestion list is capped at 12, and the shown families are fetched in a single
  `css2` request so each name renders in its own face. That request goes from the **admin's**
  browser to Google — the only place in YesWiki where that happens, and a webmaster
  deliberately browsing Google's catalogue rather than a reader being handed to it. It fails
  soundlessly by design: where Google is unreachable the names simply stay in the wiki's own
  font, and the picker, the chips and the download all still work, because the catalogue is
  vendored and the fetching is the server's job.
- **Where that choice is made** — measured before deciding. `oklch(from …)` and
  `contrast-color()` both work in the browsers this release supports, and both only ever
  answer black or white; feeding the switch into a `color-mix` percentage to pick between two
  _authored_ colours fails, because the luminance is not available as a number outside a
  relative-colour context. So the seven `--yw-on-*` are a third tier, **resolved**: computed
  by `PresetService::resolve()`, written into the file by `save()`, and recomputed live by the
  rail while you drag. They are the one computed value that can go stale — hand-edit a
  preset's `--yw-primary` without saving from the screen and its ink keeps the old answer.

- **A fourth kind of control: `choice`** — a fixed set of CSS keywords, for heading casing
  and alignment. It is the only kind where a Preset writes CSS _grammar_ rather than a colour
  or a measure, so what the select offers has to BE what the property takes; a value outside
  the set is a rule the browser silently drops. Alignment stores `start`/`end` rather than
  `left`/`right` — identical in every language YesWiki ships, correct in a right-to-left one.
- **Webfonts are installable from the screen, from Google or from another YesWiki.** Its own
  POST rather than part of a save: fetching a font is a network round trip, and a slow font
  server must not be able to stop a preset being written. It is an **API call**, not a page
  POST: as a page POST it redirected back to `/admin/preset`, which put the drawer back on the
  list with every unsaved edit gone — and "the font I want is not in this list" is a thought
  you have _while_ designing a preset, so the one moment anybody reaches for it was the moment
  losing the screen cost most. The form still carries a plain `action` for a browser with no
  JS; the difference is only what the answer costs. The second source exists because
  a YesWiki already serves its fonts from `custom/fonts/<family>/` under names it wrote
  itself — so an instance that cannot reach Google at all can still copy one across. The
  address is checked before a connection is opened: http(s) with a host name, never a bare IP
  or a `file://`, because it is a URL _this server_ fetches on an admin's say-so.

- **How a webfont is fetched, and from where.** The old fetcher asked Google's `css` endpoint
  four times over, spoofing IE 3.01, Firefox 3.6 and a modern Firefox to be served `eot`,
  `woff`, `woff2` and `ttf` — and that endpoint answers with the regular face only, so a wiki
  using Nunito had **no bold at all** and every bold heading was a shape the browser smeared
  on its own. It now asks `css2` once with an ordinary browser User-Agent, on an
  `ital,wght@0,400;0,700;1,400;1,700` axis: woff2 only, real weights, both slopes. Verified
  against the live API — a family lacking the extras still answers 200 with what it has, so
  there is nothing to fall back to.
- **A downloaded family declares itself, beside its files.** The `@font-face` rules used to
  exist in exactly one place — inside a Preset, written when that preset was saved — so a font
  could be fully downloaded, offered in the select, chosen, and still be a name no browser had
  ever heard of: `font-family` changed and every word carried on rendering in the fallback. No
  error, no warning, nothing to see, and identical from the webmaster's side to the choice not
  registering. They are now written to `custom/fonts/<family>/faces.css` as the font is
  fetched, and `/api/presets/fonts.css` serves every installed family to the admin screen.
  Keeping them beside the files also means `unicode-range` and the real weight of each file
  are Google's own answer recorded once, rather than something to be guessed back out of a
  file name — and that saving a preset no longer re-downloads a family it already has.
- **A curated family that is not downloaded yet is previewed from Google.** The list names
  sixteen; a wiki has fetched however many it has used. The file properly arrives on save, so
  choosing one of the others moved nothing — the same silence again. The admin's browser asks
  Google for it, exactly as the picker does when drawing its suggestions. Nothing on a public
  page changes and no reader is handed to Google. The guard is the **face registry**, not
  `document.fonts.check()`: with no rule for a family, `check` resolves it to a system font
  and answers _true_, which is precisely the case being tested for.
- **Copying a look between wikis: a Preset API, not a file listing.** `/api/presets/fonts`
  answers with a descriptor per file — family, style, weight, `unicode-range`, absolute URL —
  read out of the preset's own `@font-face` blocks. **The preset file is the manifest**: those
  blocks were written when the font was fetched and already say all of it, so nothing is
  stored twice and nothing can disagree with what the wiki renders with. Reusing the file
  manager instead was considered and rejected: its bytes live outside the web root and are
  served through PHP per request, and a bare `.woff2` upload carries no weight or slope — the
  two things that make a webfont worth having. Fonts stay in `custom/fonts/`, served
  statically.

## Consequences

- **Appearance changes on upgrade, by design and more than ADR-0020's did.** Spacing is
  remapped at 948 call sites, headings get explicit `rem` sizes where they previously took the
  browser's `em` defaults, and every status panel is recomputed. As ADR-0020 recorded, there is
  no visual regression testing: an unintended layout break and an intended rhythm shift are
  indistinguishable to CI.
- A preset file that still declares a derived property is **not an error** — the declaration
  simply wins, as any hand-written CSS in a preset does. The migration removes them, but a
  webmaster who wants to pin one may.
- `--yw-heading-scale` needs bare `h1`–`h6` `font-size` rules, which core did not have. Any
  component that sizes its own headings still wins on specificity, exactly as it did over the
  browser's defaults — but a theme that relied on inheriting UA sizes will now inherit ours.
- The derived tier depends on `color-mix()` and on custom properties substituting lazily. Both
  are baseline; neither has a fallback, so a browser without `color-mix()` gets no status
  panels rather than approximate ones.
- A field can now hold `var(--yw-…)`, so anything reading a preset's values must cope with a
  value that is not a literal. The picker resolves it through the probe (which is why its
  swatch shows the referenced colour rather than black), and the contrast badge scores the
  resolved pair. `missingIn()` already treated any non-empty value as present.
- The contrast badges' own colours are hard-coded literals rather than tokens. They report on
  a preset that is repainting the page live, so a "fail" drawn in `--yw-danger` would be
  recoloured by the very values it is warning about, and could be invisible exactly when it
  matters.
- `--yw-rail-width` moved from `.yw-designer__sidebar` to `:root`: a screen whose drawer is
  open by default has to push its own canvas clear of it, and the canvas is the drawer's
  sibling, so it could not read a value declared on the drawer.
- **`--yw-border-width: 0` and `--yw-shadow-strength: 0` are supported settings**, which means
  107 borders and 60 shadows can be switched off from one rail. Anything that used a hairline
  for layout rather than for looks will move; nothing found in review does.
