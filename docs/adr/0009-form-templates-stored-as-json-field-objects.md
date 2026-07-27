# Form templates are stored as JSON arrays of named-attribute field objects

The positional `texte***bf_titre***Nom***…` syntax was the storage format for form
templates (`bn_template`) since the earliest bazar days. Ticket 26 (2026-07-27,
user directive "the old syntax should disappear, we want the prepared json only")
replaced it: a form's template is now a **native JSON array of field objects**
inside the form page's body — `[{"type": "texte", "name": "bf_titre",
"label": "…"}]` — no string-in-string encoding, no positional slots.

Attribute keys are derived **by reflection from the `FIELD_*` constants of the
PHP class handling each type** (`FieldFactory::getAttributeIndexToKeyMap()`,
most-derived declaration wins by name then by index), so the codec cannot drift
from what the field constructors actually consume. Positions with no named
constant round-trip as numeric string keys, keeping unknown/extension field
types lossless.

## Considered Options

- **Keep positional storage, hide it behind a nicer editor** — rejected: the
  format itself was the maintenance hazard (16 anonymous slots, per-type
  meaning shifts, mapping tables duplicated in JS).
- **Invent a hand-written key schema** — rejected in favor of reflection from
  `FIELD_*` constants: one source of truth, no parallel mapping to maintain.
- **Rewrite field constructors to consume named keys directly** — deferred: the
  positional array remains the internal wire format between `parseTemplate()`
  and the constructors; only the storage/API boundary changed. This keeps the
  45 field classes untouched and the change reversible at one seam.

## Consequences

The legacy `***` syntax is **read-only forever** (old page revisions); every
write path re-encodes to JSON. A named key absent from the handling class's
constant map has no positional slot and is silently dropped on write. The
migration `20260727000000_ConvertFormTemplatesToJson` also repaired a latent
SQLite defect: the installation seeds relied on MySQL-only backslash escapes,
which SQLite stored literally, silently breaking every seeded form there —
seeds are now backslash-free native-array bodies, portable across drivers.
