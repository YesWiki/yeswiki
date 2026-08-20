# Worker mode, and Request state is never global

The binary (ADR-0023) runs FrankenPHP in **worker mode**: the PHP process is booted once and
serves request after request, instead of being torn down and rebuilt per request as php-fpm does.
That is the reason to reach for FrankenPHP at all, and it is the reason YesWiki cannot keep
per-request facts in globals. We decided to run the full worker, with services and container
alive across requests, and to **eliminate Request state from global scope rather than reset it**,
enforced by a ratchet that may only shrink.

## The leaks are specific, not theoretical

There are 193 `$GLOBALS[...]` sites in `src/`, and they are three different things.

**78 are `$GLOBALS['yeswikiServices']`**, the container, assigned once at `YesWikiRuntime.php:770`.
Constructor injection replaces them, which is the move wave two already made against the service
locator (ADR-0013).

**Boot state stays.** `available_languages`, `installed_languages` and `translations` have the
same value for every request in a process and are correct to keep.

**The rest is Request state, and it leaks today the moment the process outlives the request:**

- `ContactAction:72` increments `nbactionmail` behind an `isset()`, so in a worker the counter
  climbs for the life of the process instead of starting at one per page.
- `EntryListAction:1760` initialises `_BAZAR_['listindex']` only when unset, then increments. It
  feeds DOM ids, so the ids drift with process age.
- `PanelAction` pushes and pops `check_{pagetag}['panel_shape']` as a stack. It is keyed by page
  tag, so two visitors on one page share it, and a request that ends mid-stack hands the next one
  a dirty stack.
- `FormPropertiesService:86` and `SubscribeField:53` branch on `_BAZAR_['provenance'] === 'import'`.
  Left set by an import, every later request in that worker believes it is an import, which among
  other things skips subscription mail.
- `LanguageService:43` assigns `prefered_language`. On a container that survives the request, the
  first visitor's language becomes everyone's.

None of these crash. They produce wrong output for somebody who did nothing to cause it, which is
the failure class worth spending a rule on.

## Considered Options

- **Classic mode**, one PHP lifecycle per request exactly as php-fpm does today, is the safe
  option and was rejected knowingly. It would have made the binary a pure deployment change with
  no execution change, and it would have shipped a FrankenPHP that does not do the thing
  FrankenPHP is for. Opcache alone would still have been far faster than the shared hosting this
  audience is coming from, so the rejection is a choice about ambition, not about need.
- **Warm interpreter, cold app**: boot only the interpreter outside the request loop, rebuild the
  wiki inside `frankenphp_handle_request()` each time, and snapshot `$GLOBALS` after bootstrap to
  restore per iteration. Rejected. It is genuinely most of the speedup for a fraction of the risk,
  and its guard is mechanical rather than an audit. It also caps the ceiling permanently and
  leaves the globals in place as an example for the next contributor to copy.
- **Keeping the globals and resetting them between requests** is rejected. It is much cheaper and
  it makes the worker correct today. The reset routine is a list somebody has to remember to
  extend, and the day it falls behind, the symptom is cross-visitor bleed rather than an error.
- **Single-threaded workers**, one request at a time per process, relying on the OS to isolate
  concurrent visitors, is rejected because it does not work: these leaks are sequential, not
  concurrent. Request two in the same process still inherits request one.
- **Eliminating them without enforcement** is rejected as the odd one out. This project ratchets
  PHPStan (ticket 40, baseline 2,971), checks ADR-0013's dependency direction in CI, and asserts
  the API route convention in an architecture test. A rule nobody checks is a rule that was true
  once.

## Consequences

- **Request state lives in a request-scoped service and nowhere else**, and a PHPStan rule over
  `$GLOBALS` writes falls monotonically to zero, with a small declared allowlist for boot state.
  Worker safety becomes a property of the code rather than something somebody verified in August.
- **The refactor pays whether or not the binary ships.** Every one of these is a latent bug under
  any long-lived process, and the container work is wave two's own direction finished.
- **CI grows a runtime axis** beside the driver axis `tests/e2e/reset.sh` already takes, so the
  Playwright suite runs against php-fpm and the worker binary both. ADR-0015's amendment set the
  precedent: testing only what the suite happens to run is the gap that hid ticket 25's seven
  defects, and making the binary the recommended deployment while never running it in CI would
  repeat it exactly.
- **Repeated-request tests are the ones that matter.** Issuing the same request several times in
  one worker process and asserting the responses are identical is what catches `nbactionmail`
  climbing, a dirty `panel_shape`, or `provenance` stuck on import. A single-request test cannot
  see any of them.
- **Classic mode stays supported**, because php-fpm and shared hosting stay supported. Two
  execution models both have to stay correct, and the repeated-request tests are what keeps the
  second one honest.
