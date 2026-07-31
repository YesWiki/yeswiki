# Core asks a form which field plays a role, instead of reading user data by literal name

Core reached into user data by hardcoded field name. The iCal export and the calendar read `bf_date_debut_evenement` and `bf_date_fin_evenement`; the map read `bf_latitude`. Those strings are not core's to know — they are whatever a webmaster typed when they built their form. The features therefore worked only for people who had copied the shipped French forms, and failed **silently** for everyone else: an empty calendar, an empty export, and nothing anywhere explaining why.

A **field role** inverts that. The role is core's question — "which field holds the start date?" — and the answer belongs to the form. This is ticket 27's `entry_title_template` generalised: a form property naming which field plays which role, resolved through `FieldRoleResolver` and never by literal name.

## A role usually needs no configuration

The obvious design is a required role map per form, filled in by the webmaster or by a migration. We rejected making it required, because the answer is already unambiguous almost everywhere: a `listedatedeb` field *is* the start date, an `image` field *is* the image. Those are distinct field types, not naming conventions.

So a role resolves in two steps: the form's explicit `field_roles` map if it has one, otherwise the first field of a compatible type. Existing forms need **no migration and no webmaster action** — including every wiki seeded from the shipped Agenda form, whose fields keep their French names and keep working. The explicit map only has to say something when a form is genuinely ambiguous (two image fields) or when the webmaster wants a different answer than the default.

An explicit mapping to an incompatible field falls back to the type default rather than being honoured. A role pointing at a field that cannot play it is a misconfiguration, not an instruction, and returning something the caller cannot use would just move the silent failure somewhere harder to find.

## The designer refuses what storage merely drops

Part two gives a webmaster somewhere to answer, and the two ends of that answer are held to different standards on purpose.

`FieldRole::normalizeMap()` **drops** what it cannot use — an unknown role, a blank field name, both ends of an event on one field. It runs on the way into storage, where there is nobody to tell: a body can arrive from the API, an import, a hand-edited page, and refusing the whole write over one unusable entry would lose the usable ones with it.

The designer **refuses** the same input, with a message naming the field and the role. A webmaster who picks an impossible mapping and is silently handed the type default back learns nothing, and will pick it again. Same rule, two audiences.

The selects offer only fields of a compatible type, so the common mistake is unreachable rather than merely reported; and the empty option — "automatic, from the field type" — is the default and is never an error. A form whose webmaster never opens this section keeps working exactly as before, which is the whole point of resolving from the field type first.

## Considered Options

- **Keep the literal names, document them as required** — rejected: that is the current behaviour with the failure written down rather than fixed, and it makes a French string part of core's API forever.
- **A required role map, filled by migration** — rejected: it makes every existing form carry configuration that only restates its own field types, and it makes adding a role a data migration rather than a code change.
- **Rename user field names to canonical ones** — rejected outright. Field names are user data; core changing them to suit itself is exactly the coupling this decision removes, and ticket 11 says so explicitly: no data migration, because the point is that core stops caring what a field is called.

## Consequences

Adding a role is a code change (`FieldRole::DEFAULT_TYPES`) plus the call sites that ask for it, and a label key the designer can show — a role with no label would render an unnamed select.

A feature needing a role a form cannot answer says so: an agenda with no `start_date` and a map with no `geolocation` render a warning naming the missing role instead of an empty list, which reads as "no entries". Only when *no* listed form can answer it — several forms listed together, one of which has no dates, is not a misconfiguration, and those entries simply do not appear.

The roles named here (`start_date`, `end_date`, `image`, `email`, `description`, `geolocation`) come from what core actually hardcoded. Latitude and longitude are deliberately **one** `geolocation` role rather than two: since the map field was reworked, one field holds both, and two roles would model a shape that no longer exists.
