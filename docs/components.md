# Components — what the editor's palette offers

A **Component** is something a webmaster can insert into page content: a card in the
palette, with a label, an icon, a set of settings and a `{{tag}}` it writes. It is _not_ the
same thing as an Action — see [ADR-0017](adr/0017-the-palette-offers-components-not-actions.md)
and the Component / Presentation / Source / Item entries in [CONTEXT.md](../CONTEXT.md).

This replaces `docs/actions/*.yaml`, which is deleted. Nothing is declared in a data file
any more.

## Declaring one

Implement `ProvidesComponents` and return a list. An action declares its own; anything no
single action owns declares through a provider of the same interface. Discovery is by DI
tag, exactly like `RegisteredAction` — there is no registry to enrol in and no file to list.

```php
class SectionAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    public function components(): array
    {
        return [
            Component::for('section')
                ->category(Category::Writing)
                ->label(_t('AB_templates_section_label'))
                ->icon('layout-rows')
                ->wraps(_t('AB_templates_section_example'))
                ->settings(
                    Setting::color('bgcolor')->label(_t('…'))->withIcon('palette')->third(),
                    Setting::choice('pattern', self::patternOptions())->label(_t('…')),
                ),
        ];
    }
}
```

`components()` is an **instance** method, so a provider can inject what it needs: which
forms exist, which custom templates the wiki has, who is asking. A palette is not the same
on two wikis.

## Component

|                                                     |                                                                                                       |
| --------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `Component::for($id)`                               | the id in the palette and in a rail's state. Also the tag it writes, unless `writes()` says otherwise |
| `->writes($tag, …)`                                 | the tags it can write. The **first** is the one it writes; the others are ones it also answers to     |
| `->pin($name, $value)`                              | a parameter it always writes, and which identifies it on the way back                                 |
| `->category(Category::…)`                           | which section of the palette. Declaration order in `Category` is palette order                        |
| `->label()` `->icon()` `->description()` `->hint()` | what the card and the rail say                                                                        |
| `->wraps($example)`                                 | two halves with editable content between them, and what to put there                                  |
| `->addOnly()`                                       | insertable but not re-openable (its parameters decide how many halves to write)                       |
| `->notOffered()`                                    | editable but not listed — reached by the cursor landing in one                                        |
| `->adminOnly()`                                     | offered only to those who can administer the wiki                                                     |
| `->settings(…)` `->group(…)`                        | its own settings, and shared blocks                                                                   |

## Setting

One named constructor per input the browser has (`Setting::text`, `choice`, `checkbox`,
`color`, `image`, `slider`, `form`, `formField`, `fieldMapping`, `page`, `geo`, …), so a
type that does not exist is a call that does not compile rather than a silently hidden
field.

Modifiers: `label` `hint` `default` `suggests` `required` `multiple` `min` `max`
`withIcon` `showIf` `onlyFor` `exceptFor` `checkedValues` `extraFields` `subSettings`
`notWritten` `decidesTag` `documentedAt` `third` `half` `full` `writesTo` `title`.

Three are worth knowing:

- **`form()` is how a component is pointed at a form.** Every setting made of a form's
  fields — `formField`, `fieldMapping` and the rest — is a field _of_ the form this one
  names, and picking one is what makes the rail fetch it. A component that offers the
  first and not the second offers selects with nothing in them, which is what the palette
  did when `needFormField` (a property of the whole `entrylist` YAML group) had nowhere
  left to live. `FormPickerTest` is the drift check.
- **`default()` is load-bearing.** A setting sitting at its default is _omitted_ from the
  tag, which is what keeps a component from being written out with thirty parameters
  restating what it would have done anyway.
- **`writesTo('class')`** makes a setting one token of a shared, space-separated parameter.
  A section's shape, alignment and tone are three choices that arrive as one string; the
  rail joins them on the way out and takes them apart on the way in.

## Recognition

A tag in a page is matched back to the Component that wrote it: take every Component
listing that tag name, keep those whose pins all match, most pins wins, and a Component with
no pins is the fallback.

```
Cards        tags: entrylist   pins: template=card
Table        tags: entrylist   pins: template=table
Entry list   tags: entrylist   pins: —              ← fallback
```

So a component pinned on something nobody declares still lands somewhere sensible. If a tag
can be written by hand, give it an unpinned Component (`notOffered()` if the palette should
not list it) or the rail will open on nothing.

## Presentations and Sources

Four Components are not declared by an action at all: `Cards`, `List`, `Table` and
`Timeline`. They are **Presentations** — one shape each, rendered from **Items** — and no
action owns one, because `Cards` is `{{entrylist template="card"}}` over a form and
`{{syndication template="card"}}` over a feed. `PresentationComponents` builds them, one per
shape `PresentationRenderer` can draw.

A **Source** is an action that supplies Items: implement `SuppliesItems` and it is offered
inside every Presentation, discovered by the `yeswiki.item_source` tag. It declares four
things — what it is called, what it must be pointed at, which of its items to take, and the
Items themselves:

```php
class SyndicationAction extends YesWikiAction implements RegisteredAction, SuppliesItems
{
    public static function sourceLabel(): string { return _t('SOURCE_SYNDICATION'); }

    public static function sourceSettings(): array
    {
        return [Setting::url('url')->label(_t('…'))->withIcon('rss')->required()];
    }

    /** ...and, apart, what narrows the list: the Presentation's own settings go between. */
    public static function sourceSelectionSettings(): array
    {
        return [Setting::number('nb')->label(_t('…'))->default(0)];
    }

    public function items(): array { /* … */ }
}
```

There are two: `entrylist` over a form and `syndication` over a feed. There was a third,
`listpages`, added to prove that a Source costs one file — and retired again once ADR-0011
had made a page an entry of the Pages form, which left it answering a question `entrylist`
already answers. Removing one turned out to cost one file too, plus a migration over stored
bodies (`RetireListpages`).

**Adding a Source is a change to that action and nothing else** — the DI tag is declared
against the interface, so implementing it is the whole of enrolling. That is the property
the split exists for, and `SourceRegistryTest` is what holds it: it walks
`src/*/Action/*.php` for classes implementing `SuppliesItems` and fails if the registry
lists anything else.

A Presentation's first setting is the source, declared `->decidesTag()`. It is the one
setting whose value is not a parameter: it names the tag the component writes, and each
Source's own settings are folded in with `showIf(['source' => tag])` so only the chosen
one's are shown. Sources keep their tags and their parameters — ADR-0017 rejected unifying
them into a single `{{list source=…}}`; what is shared is only what they render _through_.

Because a Presentation answers to several tags, an action that is a Source keeps an unpinned
Component of its own, marked `notOffered()`: the palette does not list it, but a
`{{syndication}}` written by hand — or naming a template no Presentation claims — still
opens the rail on everything it can be told.

## What is checked

`tests/src/Render/ComponentRegistryTest.php` asserts what the YAML never could:

- every Component writes at least one tag;
- every **pin names a parameter its action actually reads** — a pin that names nothing
  writes a parameter the action ignores, so the component looks inserted and does nothing;
- no label, hint or option reaches the palette as an untranslated key (`_t()` returns the
  key it was given, so a mistyped one renders as itself and waits to be noticed);
- the palette comes out in category order.

## What is not a Component

**Markup syntax** — `**bold**`, `> quote`, `{# comment #}`, and the `:::info … :::`
callouts — belongs to the editor toolbars, not the palette. A Component writes a `{{tag}}`;
markup syntax does not, and that is the whole of the rule (ADR-0017). Callouts are four
items in the ACeditor's Format dropdown and four in Vditor's toolbar, each of which wraps
what is selected, wraps the block at the cursor, or _retypes_ a callout the cursor is
already inside.
