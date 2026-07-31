# Assets are declared by a render, not accumulated by a request

A field that needs CSS and JavaScript had one way to say so: append to `$GLOBALS['css']` / `$GLOBALS['js']` and trust the page footer to flush it. That works for exactly one shape of response — a whole page, rendered once, ending in `{{linkjavascript}}`. Anything else silently lost them. The form designer's field preview is where it showed: `POST /api/forms/preview` renders the real entry-form input through `renderInputIfPermitted()`, the map input duly registers leaflet, and the four scripts go into a global that no footer will ever render. The preview arrived as markup with no leaflet behind it and never became a map.

The globals were not the only sign. Two places had already hand-rolled the missing mechanism rather than fix it: the preview endpoint recorded `strlen($GLOBALS['css'])` before rendering and returned the substring appended during it, and the text-search action saved `$GLOBALS['js']`, rendered its result entries, and restored it — deliberately discarding assets, because a search page should not inherit every matched entry's field libraries. Both wanted a scope. Neither could have one.

So assets become a **declared** property of what was rendered. A render can be wrapped in a capture scope that returns the assets registered inside it; a fragment carries them as markup; the page emits them once. `$GLOBALS['css']` and `$GLOBALS['js']` cease to exist, and with them `HeaderAction`, `FooterAction`, `LinkstyleAction`, `LinkjavascriptAction` and the header/footer split they served.

## Declared means captured, not predeclared

The stronger reading — every field class declares `assets(): array` up front, so you can ask what a map needs without rendering one — was rejected. Assets are conditional on field *data*: the map input registers leaflet-draw only when the field has geometries, and its autocomplete script only when the field configures one. And 138 of the registration sites are in Twig, where a static declaration cannot live. A predeclared list would have to be either wrong or parameterised by the very data that rendering already has in hand.

What "declared" buys is not earlier knowledge. It is that the assets **travel with the thing that needs them** instead of with the request that happened to render it.

## The registry holds entries, not markup

The old registry *was* the markup — a string of `<link>` tags, deduplicated by searching that string for a substring. Now it holds structured entries: kind, resolved path, `module`, `defer`, attributes, priority. Markup is generated once, at the single emit point.

This is what makes the rest expressible. Deduplication becomes identity on a resolved URL, which is the same rule the browser-side registry applies, so the two ends agree. Ordering becomes a property rather than a consequence of who appended first. And the old substring check had a live bug it could not not have: `!strpos(...)` treats a match at offset 0 as absent, so the first stylesheet registered always failed its own duplicate test. Harmless with inert `<link>` tags, and unrepresentable now.

## The page renders its head last

Because the head is where assets go, and the head is what a Twig template renders first, the skeleton is rendered out of order: `{% block body %}` first, collecting everything every action and every field registered, then `{% block head %}`, then concatenated. There is one emit point, in `<head>`, with scripts deferred.

The alternative — keep flushing at the end of `<body>` — was rejected because it preserves a defect rather than a behaviour. Today all page-registered CSS is flushed at `</body>`, so a bazar list's stylesheet arrives after the list has been painted unstyled. That was never a fast-first-paint optimisation; "scripts at the bottom" is pre-`defer` guidance, and a stylesheet late in the body is still render-blocking *and* forces a repaint. A deferred script in `<head>` blocks nothing and is discovered by the preload scanner during the initial parse, which is strictly earlier.

This retires the `{WIKINI_PAGE}` marker and the string split that `ThemeManager` performed on it. Themes break, deliberately: a squelette is now a Twig template with two named blocks and a `page_content` variable, and it no longer calls `{{linkstyle}}` or `{{linkjavascript}}` at all.

## A fragment is self-contained; the client decides what it already has

A captured fragment re-declares everything it needs, even when the surrounding page already has it. The server states needs; the browser states what is present. A small core registry, seeded from the DOM at load and consulted before every htmx swap, drops what is already there — the form designer's own `previewStyles` `Set` promoted from one admin page to core.

Splitting it this way keeps the server stateless about a client's history, which matters more than it first appears: the same fragment endpoint serves a page that has leaflet and a page that does not, and neither the endpoint nor a cache in front of it should have to know which.

Fragment assets swap out-of-band into `<head>` rather than inline. Inline is simpler and wrong in one specific way: a fragment's assets would share the fragment's lifetime, so deleting one map field's preview card in the designer would remove `leaflet.css` from the document and unstyle every *other* map preview still on screen — while the browser-side registry still believed it was loaded.

## One initialisation convention

Field initialisers listen for htmx's `htmx:load` and are idempotent, replacing three conventions that coexisted: `DOMContentLoaded` (27 files), a `MutationObserver` with a readiness attribute (`vditor-textarea.js`), and an `htmx:afterSwap` re-init (`admin-content-action.twig`).

Idempotency is not optional here, and not only for the obvious reason. A script that a fragment *just* loaded misses the `htmx:load` that pulled it in — the event fires on settle, while the `<script src>` is still loading. So the convention is `init(document); htmx.onLoad(init)`: sweep once on load, then handle every subsequent insertion. On a normal page load both fire, and the second must be harmless.

## Considered Options

- **Bundle each page's assets into one file** — rejected. Keyed by page tag it is simply wrong: Field ACL means two users on one page render different fields, and editing a page, changing its form or switching theme all change the set without changing the tag. Keyed by a hash of the asset list it is correct but still trades cross-page cache reuse for request count, forces separate bundles for classic scripts and ES modules, requires rewriting every relative `url()` in concatenated CSS, and needs the bundle to publish its manifest to the browser-side registry or every fragment re-requests what the bundle already contains. Under HTTP/2 the win it buys is small; individual files with versioned, far-future-cacheable paths are kept.
- **Server-side deduplication**, with the client sending its loaded-asset list per request — rejected: it grows with the page, and it makes a fragment response depend on client history.
- **A JSON asset declaration materialised by a loader**, instead of tags htmx executes — rejected: it needs its own "assets ready" event because loading becomes asynchronous relative to `htmx:load`, which is a second convention next to the one being standardised on.

## Consequences

`{{linkstyle}}` and `{{linkjavascript}}` written in a user's page body or custom squelette become unknown actions. They are rare and they are in some wikis; this needs a release note, not a compatibility shim.

Core, theme and `custom/javascripts` registration moves into a service that runs before the body renders, rather than into the layout. With the head rendered last, a registration living in the layout would be too late — and a theme can no longer break a page by omitting a call it did not know was load-bearing.

The mechanism ships before the skeleton change: fragments carrying their own assets is independently valuable and touches no squelette, and the map preview becomes a map without a theme anywhere having to change.
