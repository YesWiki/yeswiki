# A list template declares itself in its own header

A form's own screen (ticket 63) offers a switch between the ways its Content can be listed. The
first version hard-coded five displays in `FormController`, and knew which two needed a field
role (`map` a geolocation, `calendar` a start date) from a second hard-coded list in
`EntryListAction`. Neither list could see a template a webmaster dropped into `custom/`, and the
labels lived in a third place, the language catalogues, under keys named after the screen rather
than the template. We decided a list template **describes itself**: a `{# presentation … #}`
comment at the top of the Twig file carries its `label`, its `category`, its `icon` and the
field roles it `requires`, and `PresentationCatalog` reads the two template folders, shipped and
`custom/`, to know what exists.

## Considered Options

- **A PHP registry the template is added to.** Rejected. It is what we had, three times over,
  and it is the one thing a webmaster cannot do: a template is a file they can write, a PHP
  constant is not. The palette already learned this with `custom/templates/bazar/*.twig`,
  which it scans rather than lists.
- **A sidecar manifest (`card.json` next to `card.twig`).** Rejected. Two files per template
  doubles what can go missing, and a header inside the template cannot be separated from it.
  The cost is a small parser over the first two kilobytes of the file; the header is a plain
  Twig comment, so a template with one renders exactly as before on any YesWiki.
- **Metadata as Twig variables (`{% set presentation = {…} %}`).** Rejected. Reading it means
  compiling and executing the template, for something that has to be answered for every
  template on every form screen.
- **Naming features (`images`, `map`, `calendar`) instead of field roles.** Rejected. Field
  roles (ticket 11) are already the vocabulary a form uses to say what its fields are for, and
  `requires: geolocation` is the same test the missing-role warning runs. One vocabulary,
  answered by `FieldRoleResolver`, rather than a second one mapped onto the first.

## Consequences

- `PresentationCatalog` is the one place that knows which list templates exist. The form
  screen's switcher, the missing-role warning in `EntryListAction`, and any future palette
  listing all ask it.
- A template without a header still works: it is listed under its file name, in the `list`
  category, with no requirements. Adding a header is how it earns a name and a place.
- The switcher shows one icon per category (`card`, `list`, `table`, `map`, `calendar`) and lists
  that category's templates on hover, custom ones marked as such. A category with nothing the
  form can draw is not shown at all.
- The shared shapes stay the authority of `PresentationRenderer::PRESENTATIONS`: the catalog
  reads their headers but does not decide which names reach `templates/presentations/`, because
  that list is a path boundary as much as a feature list.
- The legacy templates outside the two folders (`agenda`, `gogocarto`, `gogomap`) keep their
  requirements in the catalog, as the one exception, until they move or die.
