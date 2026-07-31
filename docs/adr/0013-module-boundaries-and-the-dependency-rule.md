# The codebase is six modules, and a service never depends on a controller

Wave two found 80 latent dependency cycles and a service locator used at 158 sites. Neither was the disease. The cause was a layering inversion: 20 of the 24 classes in `src/controllers/` declared no routes at all. `AuthController`, `EntryController`, `TabsController` and seventeen others were services filed under a name that made depending on them look normal, and 24 services, Bazar fields and `Wiki` itself duly did. `AuthController` alone had 11 non-controller dependents, including `Mailer`, `PageManager`, `EntryManager`, `AclService` and `Guard`. Constructor injection could not be introduced anywhere without the cycles becoming real.

So the code is laid out as six PSR-4 modules — **`Kernel`** plus the features **`Content`**, **`Identity`**, **`Render`**, **`Search`** and **`Admin`** — and four rules are enforced by `tests/src/ArchitectureTest.php` rather than by review:

- `Kernel` is infrastructure: it may be depended upon and may depend on no feature module.
- No service depends on a controller. Ever, in any direction, across any module.
- Every route is declared in `<Module>/Controller/` or `<Module>/Api/`, because route discovery scans those directories.
- Every `/api/*` route lives in `<Module>/Api/<Resource>ApiController`, so the resource an endpoint serves is readable from the filename.

A module is a boundary, not a folder. The point is that a breach shows up as an import in a diff, instead of as one more class in a directory that already held 63 of them.

## The rule is about module edges, not about the locator

`getService()` is still the normal idiom for actions, handlers, fields and migrations — 66 call sites, and they are not violations. Those classes are resolved by a registry rather than constructed by the container, so they have no constructor to inject into. The rule this ADR records constrains which module may reference which, and that is a question about `use` statements, not about how an object got hold of a collaborator.

Conflating the two would have made the architecture test unwritable, because the honest locator uses and the dishonest ones are indistinguishable at the call site and completely distinguishable at the import.

## The known-violations list works like the PHPStan baseline

`KNOWN_VIOLATIONS` records what was already broken when the boundaries were drawn, so a *new* breach fails immediately while the recorded ones are burned down. Entries may be removed, never added, and a test asserts the list contains no stale entries — a violation that gets fixed cannot quietly stay on the list and mask a later regression.

Eight entries remain. Four are `Mailer` reaching into `Content`, `Identity` and `Render`, which is the standout: a class in `Kernel` that needs an entry, a user, an authentication service and a template engine is arguably not `Kernel` code at all. The others are `DbService`'s SQL-dump generator that never moved to `Admin` with the rest of the backup code, `MigrationService` reaching for `TripleStore`, `Performer` rendering its own output, and `TemplateHelperService` reaching into `EntryController`.

## Considered Options

- **One flat `YesWiki\Core\*`** — rejected: that was the starting position, with `Core\Service` alone holding 63 unrelated classes. There was nowhere for a boundary violation to be visible.
- **A single `Api` module** holding every controller — rejected: it reads well until you notice it cuts across every other boundary, so `/api/forms` would live outside `Content` while everything it calls lives inside. Findability is guaranteed by the architecture test instead of by physical co-location.
- **Symfony lazy services, or PSR-11 `ServiceLocator` injection**, to break the cycles without moving anything — rejected. Both delete `Wiki` quickly while leaving `Mailer` depending on `AuthController`. The cycles were a symptom; the 24 service→controller edges were the cause, and a mechanism that hides a cycle is not a mechanism that removes one.
- **Fixing the inversions before reclassifying** — rejected as an ordering. Moving the 20 route-less controllers into services under honest names changes no behaviour, and doing it first separated real inversions from mere mislabelling before any judgement call had to be made.

## Consequences

Route discovery is directory-driven, which makes it silently destructible: during ticket 05, moving `ApiController` into a module removed all 67 `/api/*` routes at once. Nothing errored — the endpoint simply returned an empty body, and one unrelated test was the only thing that noticed. `testEveryRouteLivesInAControllerDirectory` exists because of that afternoon, and it asserts a minimum route count as well as their location, because "zero routes, all correctly placed" passes every other check.

Adding a module means adding it to `MODULES`, to `composer.json`'s PSR-4 map, and to nothing else — a test asserts those two agree, so a module that autoloads but is unchecked, or is checked but does not autoload, fails rather than half-working.
