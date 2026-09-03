# Menus are Content, and configuration names them

The navbar and the quick access bar used to be wiki pages, `PageMenuHaut` and `PageRapideHaut`.
Ticket 30 moved them into the `layout_navbar` and `layout_quick_menu` config keys and gave them
an admin screen, which fixed a real problem: a menu that is a page can be deleted, renamed or
vandalised like any other page, and parsing markdown to find out what the navigation is was never
going to be reliable.

It cost three things. A config array has no revisions, so nothing records who changed the
navigation or restores it. It has no ACL of its own, so "who may edit the menu" is whoever can
reach `/admin/layout`. And it cannot be reused: `{{nav}}` in a page body has no way to draw the
site's own menu, so a wiki that wants its main navigation in a sidebar writes it out a second
time by hand.

Meanwhile the same concept had three renderers that disagreed. `templates/layout/navbar.twig`
draws two levels and no icons. `templates/layout/quick-menu.twig` draws icons and one level.
`NavAction` concatenates strings, draws one level, and supports an `icons` parameter that the
action palette never offered. An icon works in one placement, a dropdown in another, and the
active link is decided by comparing tags in one and built URLs in the other.

We decided a menu is **Content of its own type**, and configuration says **which** menu the chrome
draws rather than **what is in it**. A `menu` row holds a tree exactly two levels deep whose nodes
carry a label, a link and an icon. `layout_navbar` and `layout_quick_menu` hold a tag, beside new
flags saying whether that placement draws icons, labels and dropdowns. One renderer serves all
three placements, and `{{nav menu="..."}}` is how a page asks for one.

## Considered Options

- **Leave the menus in config and teach `{{nav}}` to read them.** Rejected. It answers only the
  reuse complaint, and it answers it by making config the place where a wiki's navigation content
  lives permanently. Versioning and per-menu ACL stay impossible, and a wiki still cannot have a
  third menu without a third config key.
- **One `list` type with a `kind` key in the body.** Rejected. `pages.type` is an indexed column
  since ticket 27, and `PageManager::tagsOfType()` already answers "every row of this kind" from
  the index. A kind inside the JSON body means every picker that offers lists to a field has to
  load and decode every list to filter menus out, and a picker that forgets becomes a menu offered
  as the options of a form field.
- **Reserved tags with no config key.** Rejected. Two more permanently unavailable tags, and a
  farm or a theme loses the ability to point an instance at a different menu. The config key is
  one string and it is where the other layout decisions already are.
- **Keep `links` and `titles` working beside `menu`.** Rejected deliberately, knowing the cost. Two
  ways to write a nav means the palette teaches one and wikis keep the other alive forever, and the
  parallel comma-separated arrays are the shape that made the icons parameter unreachable in the
  first place. A migration rewrites page bodies; a call that lives in a custom template or an
  extension renders nothing and has to be rebuilt by hand.
- **A menu list that says how it wants to be drawn.** Rejected. It reads well until one menu is
  drawn as icons in the bar and as labels in a page, which is the whole point of making it
  reusable. The flags belong to the placement, so they sit with the placement.

## Consequences

- `PageType` gains `menu`. `EnumField` and every palette picker that asks for lists keeps asking
  `tagsOfType('list')` and never sees a menu, with no new rule to remember.
- A menu is versioned, revertable and carries its own ACL. Menu lists take the wiki's default write
  ACL; the two that configuration names as chrome are forced to `@admins`, so a contributor can
  own a section's tab bar without being able to rewrite the site's navigation.
- Every renderer draws two levels, so nothing is truncated anywhere and the quick access bar gains
  dropdowns it never had. With `showdropdown` off, only the top level draws.
- An entry the visitor may not read is never drawn, in any placement, with no setting. An entry
  pointing at a page that does not exist yet draws as a wanted-page link for whoever may create it
  and is hidden from everyone else, reusing `LinkRenderer`'s existing treatment.
- The display flags are Layout settings, so they belong in `ThemeManager::layoutIdentity()`.
  Leaving them out means a boosted htmx navigation swaps a page under stale chrome.
- The migration folds identical nav calls into one menu each: core's twenty-one become roughly
  five. This changes behaviour on purpose. Six admin pages that carried six independent copies of
  one nav afterwards share one menu, so fixing it once fixes all six, and editing it for one page
  edits it for all of them.
- `{{nav}}` keeps `class` and its `data-*` passthrough. It loses `links`, `titles`, `icons` and
  `hideifnoaccess`.
- The account button and the health badge stay chrome appended around the render, not menu nodes.
  They are stateful controls rather than navigation, and `layout_account_button` already toggles one.
