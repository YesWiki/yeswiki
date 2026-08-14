# Content is one module, because a page is an entry

`Content` was 185 files and 39,274 lines — 35% of all module code, five times the next module. Every audit of this branch has named it as the architecture's biggest outstanding debt, and the obvious reading of that number is that it should be cut into `Pages`, `Bazar`, `Forms` and so on.

We measured the dependency graph before cutting anything, and the obvious reading is wrong.

## What the graph says

465 internal edges, counting every class-name reference rather than only `use` statements. That distinction is the whole reason the first measurement was worthless: two classes in the same namespace need no import, and `Content/Service` held 50 of them, so `ActivityPubService` — which takes `TripleStore` and `SemanticTransformer` in its constructor — appeared to depend on nothing at all.

Against six candidate groups:

| candidate | files | lines | → Content | Content → | verdict |
|---|---|---|---|---|---|
| Import | 18 | 4,115 | 36 | **0** | already directional |
| Contact | 9 | 1,126 | 4 | **0** | already directional |
| Social | 16 | 2,311 | 16 | 5 | cycle |
| Files (byte layer) | 3 | 771 | **0** | 13 | already directional |
| Federation | 4 | 815 | 3 | 8 | cycle |
| **the rest** | 129 | 28,642 | — | — | **308 internal edges** |

The periphery separates cleanly. The core does not, and that is not an omission.

## Why the core is one thing

ADR-0011 made a page an entry of the Pages form. Tickets 10, 11 and 13 then removed the distinction between "a bazar entry" and "a wiki page" deliberately and at length: Page, User and File are forms like any other, described by the same field templates, stored in the same rows, created by the same `ContentCreator`, named by the same field roles. Splitting `Bazar` from `Pages` would re-introduce, as a module boundary, precisely the distinction three tickets were spent deleting.

`Field/` — 33 files, 5,079 lines, the single biggest thing in the module — is not a domain either. It is the plugin point by which a form describes *any* Content. It belongs beside the model it describes.

So `Content` stays large: about 25% of the codebase in one module. **This is a decision, not a backlog item.** An honest quarter of the code in one module beats five modules that have to call each other in circles, and the line count is not evidence of a missing boundary when the graph shows no cut through it that is not arbitrary.

## What did move

- **`Import`** — the importers, the sync scheduler, the importer admin surface. Nothing in `Content` refers to them; they read external sources and write Contents.
- **`Contact`** — the contact form, its mailing lists, its digest.
- **`Federation`** — ActivityPub, WebFinger, HTTP signatures, and the follow surfaces. See below for the inversion it needed.
- **`Files`** — the *byte* layer only: `AttachedFilePaths`, `FileBrowser`, `ImageResizer`. It sits **below** `Content` and knows nothing of it, which is the direction the 13 field-type edges already wanted.
- **`TripleStore` into `Kernel`.** Not one of the candidate groups — the graph turned it up. It depends on nothing but `Kernel`, and six modules use it. A generic store living in a feature module was why `Kernel/Service/MigrationService.php -> Content\Service\TripleStore` sat on `ArchitectureTest`'s `KNOWN_VIOLATIONS` list; the move retires that entry rather than burning it down.

`FileManager` stayed in `Content` on the same reasoning as the rest of the core: it persists a *File Content*, and a File is a Content. The `Files` cycle was one class doing two jobs, and the cut runs between them, not around them.

## `Social`, and the shape of a view

`Social` is comments and reactions. **Favourites stayed in `Content`** — a user's bookmark of a Content, stored as a triple, with none of the comment or reaction machinery about it — and that scoping decision removed one of the five back-edges outright rather than engineering around it.

The other four were all the same thing wearing different clothes: *the view asking for something the entry model does not hold*. `EntryExtraFieldsService` answered `comments` and `reactions` for a template by reaching into `CommentService` and `ReactionManager`; `ShowHandler` ended by calling `renderCommentsForPage()`. A page that shows an entry's comments is not the entry depending on comments, so the direction inverts: `Content` declares what can be contributed and each module contributes.

- **`ContributesEntryFields`** — a module declares which extra field names it answers (`comments`, `comments_count`, `reactions`, `reactions_count`) and answers them. `Content` keeps `triples` and `linked_data`, which are its own.
- **`AppendsToPageView`** — a module returns HTML for the bottom of a page. The comment box is one; returning `''` is how a contributor declines.
- **Field discovery now globs every module's `Field` directory**, the way route discovery already globs `src/*/Controller`, so `ReactionsField` could move to `Social` — a field whose data is reactions belongs with the reactions, not with the entry model that merely stores it.

All three fail *silently* when the wiring is wrong, which is the argument for testing each: an untagged contributor makes `comments` read as `null` and a template renders nothing, which looks exactly like an entry with no comments; an untagged appendix removes the comment box from every page, which looks exactly like comments being turned off; and a field type in a directory nothing scans is simply not offered. Before the split each was a direct call, and a mistake was a fatal error.

## `Federation`, and why not the event bus

`Federation` is out, and inbound federation turned out to be an importer: `ActivityPubService` writing remote activities into entries is `Import`'s shape exactly, so `Federation → Content` is the direction it should have. Only the outbound half needed inverting — `EntryManager` calling `notifyFollowers()` on create, update and delete.

The obvious fix was the event bus, and it was wrong. `entry.created`, `entry.updated` and `entry.deleted` already exist and would have been less code — but they are dispatched from `EntryController`, the **UI** path. An entry written by the API, by an importer or by a migration fires none of them. That is precisely why the federation call sat in `EntryManager` in the first place, and moving federation onto those events would have silently stopped it federating everything that does not come from a form submission.

So `Content` declares `ObservesEntryChanges`, `Federation` implements it, and the container hands `EntryManager` the tagged implementations — the same DI-tag arrangement as `SuppliesItems` and `ProvidesComponents`, discovered by implementing the interface with no registry to enrol in. The call stays synchronous and in the same place, so nothing changed about *when* the outbound HTTP happens; only the direction of the dependency did.

Consolidating the two event families — dispatching `entry.*` from `EntryManager` so every write path fires them — is arguably the right fix and is deliberately not made here. It would start firing **webhooks** for imports and migrations, which is a change to what a wiki sends out, and that belongs to whoever owns that decision rather than to a module split.

Two guards moved with the call and neither is incidental: a form that has not enabled ActivityPub is not federated, and an **imported entry is not re-published** — two wikis following each other would otherwise trade the same entry forever.

`KeyPairGenerator` came out of the same pass. `FormManager` generates an RSA keypair the first time a form is enabled for ActivityPub, and asked `HttpSignatureService` for it — four lines of `openssl_*` with no protocol in them, and the last thing keeping `Content` dependent on `Federation`. It is `Kernel`'s now.

## Considered options

- **Split `Bazar` from `Pages`** — rejected above. It is the one split the line count suggests and the model forbids.
- **Sub-namespaces inside `Content`, enforced by `ArchitectureTest`** — rejected. It would keep the top-level module count at six, but route discovery globs `src/*/Controller` and `src/*/Api`, so sub-modules would need a second discovery rule; and a boundary that is enforced but invisible in the PSR-4 map is harder to see in a diff, which is ADR-0013's entire criterion.
- **Move the byte layer into `Kernel` instead of a `Files` module** — defensible, since it depends on no feature module and that is Kernel's definition. Rejected because file storage is a feature's substrate rather than the framework's, and `Kernel` earns its clarity by staying small.
