# Suppression du budget de temps par passage — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** un passage de file non bloquant (`drain()`) traite tous les messages présents à son début, sans prévoir de
durée. Il ne s'arrête que sur l'hibernation, le plafond mémoire ou la limite de PHP quand elle n'a pas pu être levée.

**Architecture :** `QueueBudget` et `QueueBudgetFactory` deviennent `QueuePassLimits` (plafond mémoire, kill limit,
échéance PHP facultative) et `QueuePassLimitsFactory`. `QueueBudgetContext` disparaît. Le transport sait réclamer
« jusqu'à tel id », ce qui borne le passage aux messages présents à son début. Le contrôle `queue-no-budget` devient
`queue-php-limit`.

**Tech Stack :** PHP 8.4, Symfony Messenger (Worker), PHPUnit 12, PHPStan 2, SQLite pour les tests de file.

**Spec :** `docs/superpowers/specs/2026-11-06-queue-sans-budget-design.md`

## Global Constraints

- Commits sur `feat/messenger`. Jamais de `git push`. Aucune référence à l'agent dans les messages (ni
  `Co-Authored-By`, ni `Claude-Session`).
- Style des messages : `type(queue): …`, comme l'historique de la branche.
- Le mode `worker` (`QueueConsumer::consume()`) et les messages épinglés (`handleById()`) ne changent pas de
  comportement.
- Marge avant la limite de PHP non levée : `2.0 s + 0.1 × max_execution_time` (constantes `MARGIN_SECONDS` et
  `MARGIN_RATIO` actuelles).
- Plafond mémoire : `0.8 × memory_limit` ; `PHP_INT_MAX` quand `memory_limit` vaut -1.
- `KILL_LIMIT_NO_PHP_LIMIT = 3600` et `RunsForItsSender::HELD_FOR_THE_SENDER_SECONDS = 300` gardent leurs valeurs.
- Nouveau motif d'arrêt : `QueuePassReport::PHP_LIMIT = 'php-limit'`. Libellés : fr « limite de temps de PHP
  atteinte », en « PHP time limit reached ».
- Contrôle `queue-php-limit` : orange (`degraded()`). Il se déclenche quand au moins 1 des 5 derniers passages non
  bloquants (hors `locked` et `paused`) s'est arrêté sur `php-limit`.
- Toute la suite : `composer test` ; analyse : `composer analyse`. Les deux doivent finir sans erreur nouvelle.

## Review Focus

1. **Tâche planifiée échue pendant le passage :** `tick()` met en file les tâches échues **au début** du passage.
   Le repère (cutoff) se lit donc **après** `tick()`, sinon ces tâches attendraient la requête suivante. Test :
   `testATaskTheTickDispatchesIsRunInTheSamePass` (tâche 3).
2. **File vide au départ :** `lastId()` renvoie 0. Le passage s'arrête sur `EMPTY` sans erreur et sans boucle.
   Test : `testLastIdIsZeroForAnEmptyQueue` (tâche 1).
3. **Message différé déjà présent :** il devient disponible pendant le passage et doit être pris (son id est sous
   le repère). Test : `testADelayedMessageThatComesDueDuringThePassIsTaken` (tâche 3).
4. **Message « dédupliqué » renvoyé pendant le passage :** `send()` peut réécrire une ligne existante au lieu d'en
   insérer une. Il garde alors son id et peut être repris dans le même passage. C'est acceptable, et un test doit
   le documenter : `testADeduplicatedMessageResentDuringThePassKeepsItsPlace` (tâche 1). On vérifie seulement qu'il
   n'y a pas de boucle infinie (un seul traitement de plus au maximum).
5. **Visiteur en attente sous une limite levée :** la levée a lieu avant le premier message, même avant la
   réponse. Les claims sont alors jugés sur 1 h, pas sur les 30 s de départ. Test :
   `testAPassBeforeTheResponseAlsoLiftsTheLimit` (tâche 3).

---

### Task 1 : le transport réclame « jusqu'à tel id »

**Files :**
- Modify : `src/Kernel/Service/Queue/QueueTransport.php` (`get()` l. 153, `peekNextType()` l. 242, `claim()` l. 500,
  `transactionalClaim()` l. 538)
- Modify : `src/Kernel/Queue/QueuePassReceiver.php`
- Test : `tests/src/Kernel/QueueTransportTest.php`

**Interfaces :**
- Produces :
  - `QueueTransport::lastId(): int` — `MAX(id)` des lignes de `queue_name`, quel que soit leur état ; 0 si aucune.
  - `QueueTransport::getUpTo(int $lastId): list<Envelope>` — comme `get()`, en n'acceptant que `id <= $lastId`.
    Peut lever `UndecodableMessageException`.
  - `QueueTransport::peekNextType(?int $upToId = null): ?string`.
  - `QueuePassReceiver::__construct(QueueTransport, \Closure $guard, QueuePassObserver, ?int $onlyId = null, ?int $upToId = null)`.
    Avec `$upToId`, `get()` appelle `getUpTo()` au lieu de `get()`.

- [ ] **Step 1 : écrire les tests qui échouent** dans `QueueTransportTest`
  - `testLastIdIsZeroForAnEmptyQueue` : `assertSame(0, $transport->lastId())`.
  - `testGetUpToTakesNothingSentAfterTheCutoff` : envoyer m0 et m1, `$cut = lastId()`, envoyer m2. Deux appels à
    `getUpTo($cut)` rendent m0 puis m1 ; le troisième rend `[]`. Ensuite `get()` rend m2.
  - `testPeekNextTypeHonoursTheCutoff` : un seul message envoyé après `$cut` donne
    `assertNull(peekNextType($cut))` et `assertNotNull(peekNextType())`.
  - `testADeduplicatedMessageResentDuringThePassKeepsItsPlace` : envoyer un message `Deduplicated` (s'inspirer des
    tests de déduplication existants du fichier), prendre `$cut`, renvoyer le même. `getUpTo($cut)` le rend une
    seule fois ; l'appel suivant rend `[]`.

- [ ] **Step 2 : lancer et constater l'échec**
  Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result tests/src/Kernel/QueueTransportTest.php --filter 'LastId|GetUpTo|Cutoff|Deduplicated.*Pass'`
  Expected : FAIL, `Call to undefined method …::lastId()`.

- [ ] **Step 3 : implémenter.** `claim(?int $id = null, ?int $upToId = null)` passe `$upToId` à `peekNextType()` et
  à `transactionalClaim()`. Celle-ci ajoute `AND id <= ?` à la requête sans id quand `$upToId !== null`. Puis
  ajouter `lastId()`, `getUpTo()` et le paramètre du receiver.

- [ ] **Step 4 : lancer `QueueTransportTest` en entier**, puis `tests/src/Kernel/QueueConsumerTest.php`. Expected : PASS.

- [ ] **Step 5 : commit** `feat(queue): a pass can claim only the messages that were there when it began`

---

### Task 2 : `QueuePassLimits` et sa factory remplacent le budget

**Files :**
- Create (via `git mv`) : `src/Kernel/Queue/QueuePassLimits.php` depuis `QueueBudget.php`
- Create (via `git mv`) : `src/Kernel/Service/Queue/QueuePassLimitsFactory.php` depuis `QueueBudgetFactory.php`
- Create (via `git mv`) : `tests/src/Kernel/QueuePassLimitsTest.php` depuis `QueueBudgetTest.php`
- Modify : tous les fichiers qui importent `QueueBudget` ou `QueueBudgetFactory`, d'après
  `grep -rln 'QueueBudget\b\|QueueBudgetFactory' src tests phpstan` : `YesWikiKernel.php` l. 186,
  `QueueTransport.php` l. 45-47, `QueueConsumer.php`, `QueueRecorder.php`, `QueueRequestTrigger.php`,
  `QueueApiController.php`, `QueueConsumeCommand.php`, `JobsApiController.php`, `QueueHealthChecks.php`,
  `JobsOverview.php`, `QueueTestKit.php` et les tests. Vérifier aussi `phpstan/baseline.neon`.

Dans cette tâche, les appels à `forMode()` et `secondsPerPass()` restent en place, appuyés sur la nouvelle classe,
pour que tout reste vert. La tâche 3 retire `forMode()` ; la tâche 4 retire `secondsPerPass()`.

**Interfaces :**
- Produces :
  ```php
  final class QueuePassLimits {
      public function __construct(
          public readonly int $memoryCeilingBytes,
          public readonly int $killLimitSeconds,
          public readonly ?float $phpStopsAt = null, // microtime(true) où le passage cesse de prendre ; null = pas de limite qui tienne
      );
      public function memoryExhausted(): bool;
      public function phpLimitReached(): bool;            // $phpStopsAt !== null && microtime(true) >= $phpStopsAt
      public function lifted(int $killLimitSeconds): self; // même plafond mémoire, ce kill limit, phpStopsAt = null
  }
  class QueuePassLimitsFactory {
      // constructeur identique à QueueBudgetFactory (ClockInterface $phpClock, ?callable $ini, ?float $requestStartedAt, ?callable $setTimeLimit)
      public const KILL_LIMIT_NO_PHP_LIMIT = 3600;
      public function forPass(): QueuePassLimits;     // plafond mémoire ; kill limit = ce qui reste à PHP (3600 sans limite) ; phpStopsAt = now + max(0, reste − marge), null sans limite
      public function untilEmpty(): QueuePassLimits;  // comme forPass(), mais memoryCeilingBytes = PHP_INT_MAX
      public function liftPhpLimit(): ?int;           // inchangé
      public static function bytes(string $size): int; // inchangé
  }
  ```
  `remainingSeconds()`, `canAfford()`, `deadline`, `forHand()` et `capped()` disparaissent.
  `forMode(QueueConsumerMode, bool)` et `secondsPerPass()` restent temporairement : `forMode` rend `forPass()`
  quand le mode s'arrête de lui-même, et sinon `new QueuePassLimits(memoryCeiling, 3600)`.

- [ ] **Step 1 : réécrire `QueuePassLimitsTest`** à partir de `QueueBudgetTest`.
  - Garder : plafond mémoire à 80 %, notation `128M`, pas de plafond pour -1, `memoryExhausted`, kill limit = ce
    qui reste à PHP, 1 h sans limite PHP, jamais moins d'1 s, `liftPhpLimit` (levée acceptée ou refusée), `bytes`,
    limites illisibles = pas de limite, `untilEmpty`.
  - Supprimer : les tests de plafond 3 s / 20 s / 50 s, `remainingSeconds`/`canAfford`, ceux du contexte
    (`testTheContext*`, `testSliceSeconds*`) et `testSecondsPerPass*`.
  - Ajouter :
    - `testPhpStopsAtIsTheLimitLessTheMargin` : limite 30 s, requête commencée il y a 4 s. Attendu :
      `assertEqualsWithDelta(microtime(true) + 30 - 4 - (2 + 3), $limits->phpStopsAt, 0.5)`.
    - `testNoPhpLimitMeansNoPhpStop` : `max_execution_time = 0` donne `assertNull(forPass()->phpStopsAt)`.
    - `testAPageThatUsedItsWholeLimitStopsAtOnce` : `assertTrue(forPass()->phpLimitReached())`.
    - `testLiftedDropsThePhpStopAndKeepsTheMemoryCeiling`.

- [ ] **Step 2 : lancer et constater l'échec**
  Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result tests/src/Kernel/QueuePassLimitsTest.php`
  Expected : FAIL, classe introuvable.

- [ ] **Step 3 : faire les `git mv`, implémenter, puis remplacer les noms dans tous les fichiers listés.**
  Dans `QueueRecorder::passStarted()`, le paramètre devient `?QueuePassLimits $limits`. La ligne `budget_ms` reste
  jusqu'à la tâche 4, alimentée par `null`.

- [ ] **Step 4 : lancer** `composer test` puis `composer analyse`. Expected : PASS, aucune erreur nouvelle.
  Les tests de consommateur qui supposaient une échéance (`testDrainStopsWhenTheBudgetIsSpent` et ses voisins)
  échoueront ici. Les marquer `markTestSkipped('removed in task 3')` dans cette tâche plutôt que de les réécrire.

- [ ] **Step 5 : commit** `refactor(queue): a pass has limits, not a budget: memory, kill limit and PHP's own stop`

---

### Task 3 : le consommateur va jusqu'au bout de ce qui était là

**Files :**
- Modify : `src/Kernel/Service/Queue/QueueConsumer.php` (`drain()` l. 80-145, `consume()` l. 152, `reasonToStop()` l. 358-393)
- Modify : `src/Kernel/Queue/QueuePassReport.php`
- Modify : `src/Kernel/Service/Queue/QueuePassLimitsFactory.php` (retirer `forMode()`)
- Modify : `src/Kernel/Service/Queue/QueueRequestTrigger.php` l. 136-145, `src/Kernel/Api/QueueApiController.php`
  l. 36, `src/Kernel/Command/QueueConsumeCommand.php` l. 98-103
- Delete : `src/Kernel/Service/Queue/QueueBudgetContext.php`
- Modify : `src/Admin/Service/RemoteBackupService.php` (constructeur l. 40, l. 133)
- Modify : `src/lang/yeswiki_fr.php`, `src/lang/yeswiki_en.php` (`QUEUE_STOP_*`)
- Test : `tests/src/Kernel/QueueConsumerTest.php`, `tests/src/Kernel/QueueTestKit.php`,
  `tests/src/Admin/RemoteBackupServiceTest.php`, `tests/src/Admin/RemoteBackupQueueTest.php`

**Interfaces :**
- Consumes : tâche 1 (`lastId`, `getUpTo`, `peekNextType(?int)`, `QueuePassReceiver(..., ?int $upToId)`) ; tâche 2
  (`QueuePassLimits`, `forPass()`, `untilEmpty()`, `liftPhpLimit()`, `lifted()`).
- Produces :
  - `QueueConsumer::drain(QueueConsumerMode $mode, QueuePassLimits $limits, bool $responseAlreadySent = true, QueuePassOrigin $origin = QueuePassOrigin::Consumer, ?QueueConsumerMode $fallbackFor = null): QueuePassReport`.
  - `QueuePassReport::PHP_LIMIT = 'php-limit'`. `BUDGET` et `NO_BUDGET` sont retirés.
  - `QueuePassLimitsFactory` n'a plus de `forMode()`.

Ordre à respecter dans le corps de `drain()` :

```
claimAs(mode, limits.killLimitSeconds) → reapKilled → closeDeadPasses → tick() → $cutoff = transport.lastId()
```

`reasonToStop(&$limits, $observer, $cutoff, $liftTheLimit)`, dans cet ordre :

1. hibernation → `PAUSED` ;
2. `memoryExhausted()` → `MEMORY` ;
3. `peekNextType($cutoff) === null` → `EMPTY` ;
4. premier message : `$liftTheLimit()`, toujours, visiteur en attente ou non. Si `liftPhpLimit()` rend un entier,
   faire `claimAs($mode, $limit)` puis `$limits = $limits->lifted($limit)` ;
5. `phpLimitReached()` → `PHP_LIMIT` ;
6. sinon `null`.

Le paramètre `$visitorWaits`, `$averages` et `UNKNOWN_DURATION_SECONDS` disparaissent, ainsi que
`$this->budgetContext` et sa dépendance au constructeur.

Les appelants deviennent :
- `QueueRequestTrigger::drain()` : `forPass()` ;
- `QueueApiController` : `forPass()` ;
- `QueueConsumeCommand --drain` : `forPass()` ;
- `consume()` : `new QueuePassLimits($memoryLimitBytes, QueuePassLimitsFactory::KILL_LIMIT_NO_PHP_LIMIT)`.

Dans `RemoteBackupService`, `$slice = self::SLICE_SECONDS` et la dépendance à `QueueBudgetContext` disparaît.

Libellés : supprimer `QUEUE_STOP_BUDGET` et `QUEUE_STOP_NO_BUDGET`. Ajouter `QUEUE_STOP_PHP_LIMIT` (fr « limite de
temps de PHP atteinte », en « PHP time limit reached »).

- [ ] **Step 1 : réécrire les tests du consommateur.**
  - Supprimer : `testDrainStopsWhenTheBudgetIsSpent`, `testNothingAffordableIsNoBudgetNotAFailure`,
    `testAMessageLongerThanTheBudget*` (x2), `testTheBudgetIsKnownDuringThePassOnly`,
    `testTheBudgetIsForgottenWhenThePassBreaks`, `testAFirstMessageWhileAVisitorWaitsLiftsNothing`, ainsi que le
    helper `recordAverage` s'il n'a plus d'appelant.
  - Ajouter :
    - `testAPassRunsEveryMessageEvenWhenTheyAreSlow` : `behaviour` avec `usleep(250000)`, 3 messages,
      `drain(Request, forPass())` avant réponse. Attendu : `handled === 3`, `stopReason === EMPTY`.
    - `testAMessageSentDuringThePassWaitsForTheNext` : le handler de m0 envoie m-late. Attendu :
      `handled === ['m0']`, `pendingCount() === 1`. Un second `drain()` le prend.
    - `testATaskTheTickDispatchesIsRunInTheSamePass` : une tâche planifiée échue (s'inspirer de
      `testAWorkerTicksTheScheduleWhenTheQueueIsEmpty`) est traitée par le même `drain()`.
    - `testADelayedMessageThatComesDueDuringThePassIsTaken` : m0 normal ; m1 avec `DelayStamp(1000)`. Le handler de
      m0 fait `$this->clock->sleep(2)`. Attendu : m0 et m1 traités.
    - `testAPassBeforeTheResponseAlsoLiftsTheLimit` : comme `testTheFirstMessageAfterTheResponseIsClaimedWithTheLimitLifted`,
      mais `responseAlreadySent: false`. Attendu : `$lifts === 1`, claims à `KILL_LIMIT_NO_PHP_LIMIT`.
    - `testAPassStopsTakingMessagesOncePhpsLimitThatCouldNotBeLiftedIsReached` : factory avec levée refusée,
      `max_execution_time = 30`, requête commencée il y a 24.5 s (seuil 30 − 5 = 25 s). Le handler fait
      `usleep(600000)`, 3 messages. Attendu : `handled === 1`, `stopReason === PHP_LIMIT`, `pendingCount() === 2`.
    - `testAPassUnderALimitThatCouldNotBeLiftedGoesOnWhileTimeIsLeft` : même factory, requête commencée à l'instant,
      2 messages. Attendu : `handled === 2`, `EMPTY`.
  - Adapter : `testALimitTheHostImposesStaysOnTheClaim` (`forPass()`), `testTheFirstMessageAfterTheResponseIsClaimedWithTheLimitLifted`,
    les `new QueueBudget(...)` qui restent et le helper `seconds()` (à remplacer par `new QueuePassLimits(PHP_INT_MAX, 30)`).
  - Retirer `QueueBudgetContext` de `QueueTestKit`. Adapter les tests de `RemoteBackupService` qui injectaient un
    contexte : la tranche vaut 20.

- [ ] **Step 2 : lancer et constater l'échec**
  Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result tests/src/Kernel/QueueConsumerTest.php`
  Expected : FAIL sur les nouveaux tests (motif `php-limit` inconnu, message tardif pris).

- [ ] **Step 3 : implémenter** comme décrit ci-dessus et supprimer `QueueBudgetContext.php`.

- [ ] **Step 4 : lancer** `composer test` puis `composer analyse`. Expected : PASS. Plus aucun `markTestSkipped('removed in task 3')`.

- [ ] **Step 5 : commit** `feat(queue): a web pass runs what was waiting when it began, and stops early only on a PHP limit it could not lift`

---

### Task 4 : santé, écran et journal sans budget

**Files :**
- Modify : `src/Kernel/Service/Queue/QueueHealthChecks.php` (l. 28-29, 46, 100, 117, 326-341)
- Modify : `src/Admin/Service/JobsOverview.php` (l. 40, 199, 262, 401)
- Modify : `src/Kernel/Service/Queue/QueueRecorder.php` l. 49-55, `src/Kernel/Service/Queue/QueueStatistics.php`
  l. 293, 313 (`budgetMs`)
- Modify : `src/Kernel/Service/Queue/QueuePassLimitsFactory.php` (retirer `secondsPerPass()`)
- Modify : `templates/admin/jobs-tab-consumers.twig` l. 13, 32-33 ; `templates/admin/jobs-alerts.twig` l. 17-22
- Modify : `src/lang/yeswiki_fr.php`, `src/lang/yeswiki_en.php`
- Test : `tests/src/Kernel/QueueHealthChecksTest.php`, `tests/src/Admin/JobsControllerTest.php`,
  `tests/src/Kernel/QueueRecorderTest.php`

**Interfaces :**
- Consumes : `QueuePassReport::PHP_LIMIT` (tâche 3).
- Produces : le contrôle `queue-php-limit`, avec les clés `QUEUE_HEALTH_PHP_LIMIT`, `QUEUE_HEALTH_PHP_LIMIT_SAYS`
  et `QUEUE_HEALTH_PHP_LIMIT_FAILED` (`%{count}`, `%{passes}`).

Textes à reprendre tels quels :
- fr :
  - `QUEUE_HEALTH_PHP_LIMIT` : « Limite de temps de PHP impossible à lever »
  - `_SAYS` : « Cet hébergeur refuse que le wiki lève la limite de temps de PHP : des traitements attendent le
    passage suivant, ou sont coupés en route. Autorisez set_time_limit, ou passez au mode « worker ». »
  - `_FAILED` : « %{count} des %{passes} derniers passages se sont arrêtés sur la limite de temps de PHP »
- en :
  - « PHP's time limit cannot be lifted »
  - « This host does not let the wiki lift PHP's time limit: jobs wait for the next run, or are cut off. Allow
    set_time_limit, or switch to the "worker" mode. »
  - « %{count} of the last %{passes} runs stopped on PHP's time limit »

Les clés `QUEUE_HEALTH_FALLBACK_FAILED` et `_NEVER` perdent « (budget %{budget} s) » dans les deux langues. La clé
`'budget'` de leurs valeurs (l. 117) disparaît. Les clés `QUEUE_HEALTH_NO_BUDGET*`, `ADMIN_JOBS_BUDGET`,
`ADMIN_JOBS_BUDGET_UNLIMITED` et `ADMIN_JOBS_COL_BUDGET` sont supprimées. Avant de supprimer
`ADMIN_JOBS_BUDGET_UNLIMITED`, vérifier par `grep` qu'elle n'a pas d'autre appelant.

Dans `jobs-tab-consumers.twig`, `'no-budget'` est remplacé par `'php-limit'` dans les motifs en rouge. Dans
`JobsOverview` l. 199, `'queue-no-budget'` est remplacé par `'queue-php-limit'` avec les mêmes actions.

- [ ] **Step 1 : tests qui échouent.**
  - `QueueHealthChecksTest` : remplacer les tests `noBudget` (chercher `NO_BUDGET`) par
    `testPhpLimitTripsOnOneOfTheLastFivePasses` (passes enregistrées : 4 `empty` et 1 `php-limit`, d'où l'alerte et
    le texte « 1 des 5 »), `testPhpLimitIgnoresAnOlderStop` (le `php-limit` est en 6ᵉ position, donc pas d'alerte)
    et `testPhpLimitIsSilentForTheWorker`. Utiliser l'aide `QueueTestKit` qui enregistre une passe (l. 274).
  - `QueueRecorderTest` : la ligne d'une passe n'a plus de clé `budget_ms`, et `recentPasses()` n'a plus de
    `budgetMs`.
  - `JobsControllerTest` : la page ne contient plus « Budget ».

- [ ] **Step 2 : lancer et constater l'échec.** Run : les trois fichiers. Expected : FAIL.

- [ ] **Step 3 : implémenter.**

- [ ] **Step 4 : lancer** `composer test` puis `composer analyse`. Puis `grep -rni budget src templates`, qui ne doit
  plus rien rendre en dehors de `RunsUnbudgeted` (tâche 5). Expected : PASS.

- [ ] **Step 5 : commit** `feat(queue): queue-php-limit replaces queue-no-budget, and the jobs page shows no budget`

---

### Task 5 : les noms qui restent

**Files :**
- `git mv src/Kernel/Queue/RunsUnbudgeted.php src/Kernel/Queue/RunsForItsSender.php` ; modifier `QueueBus.php`
  l. 14, 53, 58, `CreateArchive.php`, `RestoreArchive.php` et le docblock de `QueueRequestTrigger::runsItself()`
  l. 52-57.
- `QueueConsumerMode::isBudgeted()` → `endsByItself()` (docblock : « Whether a pass in this mode stops by itself
  once the queue is empty, rather than waiting for messages »). Retirer `passCeilingSeconds()`. Appelants :
  `QueueFallback` l. 69, `QueueHealthChecks` (5 appels), `QueueStatistics` l. 308, `QueueRequestTrigger` l. 55,
  `forPassFromTheWeb()`.
- Retirer `QueueStatistics::averageDurationMs()` (plus d'appelant en `src`). Retirer ses assertions dans
  `QueueRecorderTest` l. 269, 525, 531, 794 et `JobsControllerTest` l. 427 : supprimer l'assertion seule si le test
  vérifie autre chose, et le test entier s'il n'existait que pour elle.
- Tests : `ArchitectureTest`, `GlobalsRatchetTest` et toute mention par `grep -rn 'isBudgeted\|RunsUnbudgeted\|averageDurationMs\|passCeilingSeconds' src tests phpstan`.

**Interfaces :**
- Produces : `interface RunsForItsSender { public const HELD_FOR_THE_SENDER_SECONDS = 300; }` ;
  `QueueConsumerMode::endsByItself(): bool`.

- [ ] **Step 1 : faire les renommages et suppressions.** Le comportement ne change pas : les tests existants suffisent.
- [ ] **Step 2 : lancer** `composer test` et `composer analyse`, puis le `grep` ci-dessus. Expected : PASS, grep vide.
- [ ] **Step 3 : commit** `refactor(queue): the names no longer speak of a budget`

---

### Task 6 : documentation

**Files :**
- Modify : `docs/fr/async.md` (l. 169, 311, 388, section « Temps insuffisant… » l. 597-601, « Attente ajoutée aux
  pages » l. 603-607, « Traitements trop longs pour le mode choisi » l. 529-546)
- Modify : `docs/adr/0030-background-work-goes-through-one-queue.md` (« The consumer » l. 512, « The end of a
  request » l. 674, « The fallback and the budget » l. 695, health checks l. 754, « Backups and restores » l. 788,
  developer guide l. 1003+)
- Vérifier : `docs/adr/0015-…md` l. 82, `CONTEXT.md`, `INSTALL.md` (`grep -ni budget`)

Contenu :
- async.md :
  - plus de « budget par passage » (bandeau, onglet Consommateurs, légende de la capture l. 169) ;
  - une section « Limite de temps de PHP impossible à lever (orange) » remplace « Temps insuffisant… », avec le
    texte de `_SAYS` développé ;
  - « Attente ajoutée aux pages » : le visiteur attend que les traitements présents au début du passage soient
    finis ;
  - une phrase dit qu'un traitement mis en file pendant un passage attend le suivant ;
  - « Traitements trop longs » : la limite de l'hébergeur ne coupe plus en route un traitement commencé trop tard,
    puisque le passage s'arrête avant ; seules les coupures extérieures à PHP ou le plafond d'une heure le font.
- ADR 0030 :
  - réécrire les sections citées au présent, sans budget ;
  - renommer « The fallback and the budget » en « The fallback and the limits » ;
  - ajouter en tête de « Implementation notes » une note datée du 2026-11-06 : budget retiré, parce que la plupart
    des traitements sont courts et que les traitements lents ne s'anticipent pas par une moyenne (timeout HTTP) ;
    remplacé par le repère de début de passage et l'arrêt sur une limite de PHP non levée.

- [ ] **Step 1 : modifier les fichiers.**
- [ ] **Step 2 : vérifier** `grep -rni 'budget' docs/fr docs/adr CONTEXT.md INSTALL.md`. Expected : seulement la
  note historique de l'ADR 0030.
- [ ] **Step 3 : commit** `docs(queue): no budget any more: a pass runs what was waiting, and stops on a PHP limit it could not lift`

Signaler au développeur que la capture `docs/fr/images/async-bandeau-mode.png` montre encore le budget.
