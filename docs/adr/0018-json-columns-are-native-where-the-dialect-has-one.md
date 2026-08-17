# JSON columns are native where the dialect has one

Every Content's body is JSON (ADR-0011, ticket 09), stored in a `LONGTEXT` / `TEXT` column. Ticket 18 moved full-text search into its own index table, which removed the reason the column type was constrained: nothing full-text reads `body` any more. What is left is the **field-path** half — `SearchManager` and every bazar filter reach into `body` by path — and that is the half a native column helps.

We measured it rather than argued it. `./yeswicli content:body-bench` seeds two identical corpora, one `TEXT` and one native, and runs the query shapes `SearchManager` and `BazarListService` really emit. 200,000 entries across three forms, seven fields each, on the compose stack's MySQL 8 and PostgreSQL 16. Median of five runs, milliseconds:

| query                               | MySQL `LONGTEXT` | MySQL `JSON` | PostgreSQL `TEXT` | PostgreSQL `JSONB` |
| ----------------------------------- | ---------------- | ------------ | ----------------- | ------------------ |
| filter on `form_id`                 | 1226             | 633 (−48%)   | 407               | 51 (−88%)          |
| filter on one field                 | 1173             | 648 (−45%)   | 401               | 47 (−88%)          |
| filter on a checkbox (LIKE)         | 1263             | 695 (−45%)   | 392               | 51 (−87%)          |
| two filters                         | 1389             | 669 (−52%)   | 388               | 48 (−88%)          |
| project 7 fields, ordered, LIMIT 20 | 1454             | 730 (−50%)   | 1947              | 78 (−96%)          |

**MySQL halves.** The column stops being parsed as text into a JSON document once per row per expression, and starts being read out of the compact binary form it is already in.

**PostgreSQL is not in the same order of magnitude**, and the reason is in our own code. `PostgreSqlDialect::jsonExtract()` has to emit

```sql
CASE WHEN body ~ '^\s*\{' THEN (body::jsonb #>> ARRAY['bf_ville']) ELSE NULL END
```

— a regex match **and** a text-to-`jsonb` cast, per row, _per extracted field_, because a `TEXT` column may hold something that is not JSON at all. That is why the projection query is the worst case by far: seven fields means seven casts of the whole document on every row, and it is the query an entry list actually runs. A `jsonb` column cannot hold a non-document, so the guard and the cast both disappear and the expression becomes `body #>> ARRAY['bf_ville']`.

## Why not "just index the hot paths" instead

That was the competing outcome, and it is the cheaper ticket, so we measured it too — a stored generated column plus a normal index on MySQL, an expression index on PostgreSQL:

|                              | MySQL `LONGTEXT` | MySQL `JSON` | PostgreSQL `TEXT` | PostgreSQL `JSONB` |
| ---------------------------- | ---------------- | ------------ | ----------------- | ------------------ |
| filter on `form_id`, indexed | 665              | 521          | 123               | 118                |
| every other query            | unchanged        | unchanged    | unchanged         | unchanged          |

An index does close most of the gap — **for the one path it indexes**. Every other query is exactly where it was, because the planner still has to interpret the column for every field that has no index of its own. And there is no set of paths to pick: the filtered field is whatever a webmaster put in their form, so the hot paths are per-wiki and unknowable from core. Indexing is a real optimisation and stays available on either column type; it is not a substitute for the column type.

## Cost

- **Migration.** 2.3s per 100k rows on MySQL, 1.0s on PostgreSQL — so roughly 23s and 10s at a million Contents. It is a full table rebuild and the wiki is down for it, but it is seconds, not the hours the "installs the maintainer does not control" worry assumed.
- **Storage.** PostgreSQL breaks even (145 MB → 147 MB, +1%). MySQL grows: 103 MB → 119 MB (+16%), because binary JSON stores each document's key names and offsets rather than the text's shared repetition. We are trading disk for CPU on MySQL and getting both on PostgreSQL.
- **SQLite is unchanged.** There is no JSON column type — `json` there is `TEXT` with JSON1 functions over it — and the control corpus confirms it: TEXT against TEXT came out within ±2% on every query. SQLite keeps `TEXT` and keeps `json_valid()` guarding every extraction.

So: **`SqlDialect::jsonColumnType()`** — `JSON` on MySQL, `JSONB` on PostgreSQL, `TEXT` on SQLite — and `jsonExtract()` drops the guard on the two dialects that no longer need it.

The dialect ends up with three methods where it had one, because "this column is JSON" is now a fact the SQL depends on and a call site has to state which kind of column it is reading:

- `jsonExtract($column, $path)` — the column is declared `jsonColumnType()`. Two are: `pages.body` and `pages.metadata`.
- `jsonExtractText($column, $path)` — JSON living in a text column, so the read is guarded. Core has none left; see below for why it is kept anyway.
- `jsonAsText($column)` — a JSON column for the string operators that have no JSON equivalent. `jsonb` has no `LIKE` at all, so an administrator's free-text filter over a whole body needs the cast stated.

Getting the first two the wrong way round is a type error on PostgreSQL rather than a wrong answer, which is the right way round for a mistake to fail.

## What this commits us to

A native column **validates on write**. That is mostly a gain — a malformed body becomes an error at the moment it is written rather than a row that silently reads as `NULL` through every extraction — but it makes two paths stricter, and both are the implementation ticket's problem to handle: restoring an archive taken from a wiki predating ticket 09 (whose bodies are wiki text, not JSON), and any extension writing `pages` directly.

`jsonb` also **does not preserve key order and collapses duplicate keys**. Nothing in core is exposed to that: `PageManager` already compares bodies through `PageBody::equals()`, decoded and key-order-blind, precisely so that a re-encode which only moved keys around does not invent a revision. Anything that compared body strings byte-for-byte would break, and nothing does.

## `metadata` too — measured after the fact, because the first answer here was wrong

This ADR originally left `metadata` as `TEXT` on the grounds that nothing filters on it by path. That is false. `AclService::updateRequestWithACL()` is `jsonExtractText('metadata', '$.acls.read')`, and it is pasted into `PageManager::getAll()` and `SearchManager` — so it runs on **every listing query in the wiki**, and the predicate repeats that expression once per needed ACL entry for the "granted" test, once more each for "denied", and again for the null checks. An anonymous visitor evaluates it about four times per row; a logged-in administrator in a few groups, more like a dozen.

Measured with the same rig (`content:body-bench`), on the same 200k corpus, with a query that touches `body` not at all so what it reports is the ACL predicate alone:

|                     | MySQL `TEXT` | MySQL `JSON` | PostgreSQL `TEXT` | PostgreSQL `JSONB` |
| ------------------- | ------------ | ------------ | ----------------- | ------------------ |
| ACL predicate alone | 788          | 687 (−13%)   | 147               | 57 (−62%)          |

The shape is the same as `body`'s and the size is not, for a reason worth writing down: **PostgreSQL's `::jsonb` cast costs in proportion to the document it casts**, and a `metadata` document is a few dozen bytes where a `body` is a kilobyte or more. So the ACL predicate is cheap in absolute terms even at four evaluations per row — but it is still paying a cast per evaluation, and dropping it is a 62% saving on PostgreSQL.

So `metadata` is native too. The decision that settles it is not the 62%, though — it is that **both columns are on the same table**. Converting them separately means two full rebuilds of `pages`, two table locks and two windows of downtime, for one decision. That cost is paid by every wiki in the world and bought nothing, so the two convert in the same ALTER pass.

MySQL's −13% would not have justified this on its own. It did not have to: once `body` is being rebuilt anyway, `metadata` is free to bring along.

`metadata` stays **nullable**, and that is load-bearing: 181 of 3413 rows on a real install carry no metadata at all. `JSON_VALID(NULL)` is NULL rather than 0 and `NULL IS NOT JSON OBJECT` is false, so a NULL is not a violation in either dialect's spelling — verified against both servers, with 53 NULL rows converting untouched.

## Considered options

- **Keep `TEXT` everywhere and close the question** — rejected by the PostgreSQL numbers. An 8× to 25× difference on the query every entry list runs is not a micro-optimisation, and at the scale this rewrite targets (hundreds of thousands to millions of Contents) it is the difference between a list that renders and one that times out.
- **Keep `TEXT`, add generated/indexed columns for the hot paths** — rejected as _the_ answer, kept as a complement. Measured above: it fixes one path and leaves the rest, and core cannot know which paths a wiki's forms will make hot.
- **Native JSON on MySQL only** — rejected. PostgreSQL is where the gain is largest; it would be an odd dialect to leave out.
