# Core asks a form which field plays a role, instead of reading user data by literal name

Core reached into user data by hardcoded field name. The iCal export and the calendar read `bf_date_debut_evenement` and `bf_date_fin_evenement`; the map read `bf_latitude`. Those strings are not core's to know — they are whatever a webmaster typed when they built their form. The features therefore worked only for people who had copied the shipped French forms, and failed **silently** for everyone else: an empty calendar, an empty export, and nothing anywhere explaining why.

A **field role** inverts that. The role is core's question — "which field holds the start date?" — and the answer belongs to the form. This is ticket 27's `entry_title_template` generalised: a form property naming which field plays which role, resolved through `FieldRoleResolver` and never by literal name.

## A role usually needs no configuration

The obvious design is a required role map per form, filled in by the webmaster or by a migration. We rejected making it required, because the answer is already unambiguous almost everywhere: a `listedatedeb` field *is* the start date, an `image` field *is* the image. Those are distinct field types, not naming conventions.

So a role resolves in two steps: the form's explicit `field_roles` map if it has one, otherwise the first field of a compatible type. Existing forms need **no migration and no webmaster action** — including every wiki seeded from the shipped Agenda form, whose fields keep their French names and keep working. The explicit map only has to say something when a form is genuinely ambiguous (two image fields) or when the webmaster wants a different answer than the default.

An explicit mapping to an incompatible field falls back to the type default rather than being honoured. A role pointing at a field that cannot play it is a misconfiguration, not an instruction, and returning something the caller cannot use would just move the silent failure somewhere harder to find.

## Considered Options

- **Keep the literal names, document them as required** — rejected: that is the current behaviour with the failure written down rather than fixed, and it makes a French string part of core's API forever.
- **A required role map, filled by migration** — rejected: it makes every existing form carry configuration that only restates its own field types, and it makes adding a role a data migration rather than a code change.
- **Rename user field names to canonical ones** — rejected outright. Field names are user data; core changing them to suit itself is exactly the coupling this decision removes, and ticket 11 says so explicitly: no data migration, because the point is that core stops caring what a field is called.

## Consequences

Adding a role is a code change (`FieldRole::DEFAULT_TYPES`) plus the call sites that ask for it. A feature needing a role a form cannot answer should say so — `FieldRoleResolver::missingRoles()` exists to make "this form has no start date field" a sentence a UI can show, rather than an empty render.

The roles named here (`start_date`, `end_date`, `image`, `email`, `description`, `geolocation`) come from what core actually hardcoded. Latitude and longitude are deliberately **one** `geolocation` role rather than two: since the map field was reworked, one field holds both, and two roles would model a shape that no longer exists.
