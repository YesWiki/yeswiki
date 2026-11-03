# Le Journal, seule source de vérité du Scheduler — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** le Scheduler décide de ce qui est dû en lisant le Journal (canal `queue`) et la déclaration des tâches, et seulement ça. Le checkpoint en triples, les lignes `__schedule` et le générateur réécrit disparaissent.

**Architecture :** trois nouvelles actions du canal `queue` (`queue.task.declared`, `queue.task.dispatched`, `queue.task.finished`), avec le `task_id` en `target`, sont écrites par `QueueRecorder`. Une classe en lecture seule, `ScheduleHistory`, en tire l'histoire de chaque tâche. `QueueScheduleTicker` calcule ce qui est dû à partir de cette histoire et des triggers Symfony, sous un verrou de fichier. Il devient aussi le seul endroit qui liste les tâches, pour la page des jobs et les Health checks. Le plan construit d'abord le neuf à côté de l'ancien, bascule les lecteurs, puis supprime l'ancien.

**Tech Stack :** PHP 8.3, symfony/messenger et symfony/scheduler 7.4, PHPUnit 12, SQLite, MySQL et PostgreSQL (`SqlDialect`).

**Spec :** `docs/superpowers/specs/2026-11-03-scheduler-journal-source-of-truth-design.md`

## Global Constraints

- Aucun état à côté du Journal : pas de colonne, pas de triple, pas de cache persistant de « prochaine échéance ». Un mémo en mémoire de processus est permis.
- Toutes les heures écrites ou comparées sont en UTC, au format `Y-m-d H:i:s`, lues sur l'horloge injectée (`ClockInterface`, `DatabaseClock` en production).
- Les lignes de tâche s'écrivent comme les autres lignes du canal `queue` : en SQL direct par `QueueRecorder::insert()`, jamais par `Journal::store()`.
- Les commentaires ajoutés tiennent en trois lignes en tête de fichier et en une ligne par fonction (commit c0e8483c5).
- Messages de commit au format du dépôt (`type(scope): phrase`), **sans** ligne `Co-Authored-By` ni aucune référence à l'agent (consigne de l'utilisateur). On ne pousse jamais.
- Lancer une classe de tests : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr tests/src/Kernel/<Classe>Test.php`. La suite entière : `composer test`.
- `binary/program/` est une copie de build non suivie : n'y touchez pas.

## Review Focus

1. **Le premier appel fige un trigger `every`.** `PeriodicalTrigger` fixe son `from` au premier `getNextRunDate()` qu'on lui fait. Si `periodOf()` ou un autre calcul l'appelle avant l'ancrage, toutes les tâches `every` deviennent dues à chaque tick. Le test associé est `testAnIntervalCountsFromTheAnchorWhateverAskedFirst` (Tâche 3).
2. **Une purge sous MySQL.** MySQL refuse un `DELETE` dont la sous-requête lit la même table sans table dérivée. Ce test tourne sur SQLite, et la passe `testOnARealServer` existante couvre MySQL et PostgreSQL quand ils sont configurés : `testThePurgeSparesTheLastRowOfEachTaskOnARealServer` (Tâche 1).
3. **Deux ticks en même temps sur une échéance** ne doivent envoyer qu'un message et écrire qu'une ligne d'envoi : `testOnlyOneProcessTicksAtATime` (réécrit, Tâche 3).
4. **Une tâche qui disparaît puis revient** (digests désactivés, puis réactivés) tourne une seule fois, tout de suite si une échéance est passée, et n'écrit pas de seconde ligne d'apparition : `testATaskThatComesBackRunsOnceIfItMissedASlot` (Tâche 3).
5. **Un wiki sans aucune tâche déclarée** (kit de test, wiki cassé) ne doit pas lancer une passe à chaque requête : `dueAt()` y vaut `null`, comme le checkpoint actuel, et `hasWork()` le traite comme aujourd'hui. Le test `testNoTaskDeclaredMeansNothingIsEverDue` (Tâche 3) vérifie que `tick()` renvoie 0 et n'écrit rien.

---

### Task 1 : le Journal sait garder la dernière ligne de chaque tâche

**Files :**
- Modify : `src/Kernel/Queue/QueueJournalEntry.php`
- Modify : `src/Kernel/Service/JournalSchema.php`
- Modify : `src/Kernel/Database/SqlDialect.php`, `MySqlDialect.php`, `PostgreSqlDialect.php` et `SqliteDialect.php` (`journalDdl`, `journalQueueIndexDdl`)
- Modify : `src/Kernel/Service/Journal.php` (`prune()`)
- Test : `tests/src/Kernel/JournalQueueIndexTest.php`, `tests/src/Kernel/JournalTest.php`

**Interfaces :**
- Produces : `QueueJournalEntry::TASK_DECLARED = 'queue.task.declared'`, `TASK_DISPATCHED = 'queue.task.dispatched'`, `TASK_FINISHED = 'queue.task.finished'`, `QueueJournalEntry::TASK_ACTIONS` (liste des trois).
- Produces : un index `(channel, action, target)` sur le Journal, nommé `idx_channel_action_target` (préfixé du nom de la table hors MySQL, comme `idx_channel_target_at`).

- [ ] **Step 1 : écrire les tests qui échouent**

`JournalQueueIndexTest::testTheJournalHasTheTaskIndex` : après `JournalSchema::create()`, puis après `ensureQueueIndex()` sur un Journal créé sans cet index, `schema()->indexExists('journal', <nom>)` est vrai. Un second `ensureQueueIndex()` ne lève rien.

`JournalTest::testThePurgeSparesTheLastRowOfEachTask` : insérez dans le canal `queue`, tous datés de 30 jours (`at` = `last_at`) :
- pour `task-a` : deux `queue.task.dispatched` (ids 1 et 2), deux `queue.task.finished`, un `queue.task.declared` ;
- pour `task-b` : un `queue.task.dispatched` ;
- un `queue.run.handled` et un `queue.pass`.

Après `prune()` (rétention de 7 jours), il reste exactement : le dernier `dispatched` et le dernier `finished` de `task-a`, son `declared`, et le `dispatched` de `task-b`. Le `queue.run.handled` et le `queue.pass` sont partis. Une ligne `queue.task.dispatched` de 2 jours n'est pas touchée.

Ajoutez le même cas à la passe sur serveur réel de `JournalTest` si elle existe. Sinon, appelez la même méthode depuis un test `#[DataProvider]` qui reprend les paramètres de `QueueScheduleTickerTest::testOnARealServer`.

- [ ] **Step 2 : lancer les tests, vérifier qu'ils échouent**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr --filter 'TaskIndex|SparesTheLastRow' tests/src/Kernel`
Expected : FAIL (l'index n'existe pas, les lignes de tâche sont purgées).

- [ ] **Step 3 : ajouter les constantes et l'index**

`journalQueueIndexDdl(string $table): array` renvoie désormais `array<string, list<string>>`, des instructions indexées par nom d'index non préfixé (`idx_channel_target_at`, `idx_channel_action_target`). `journalDdl()` crée les deux. `JournalSchema::ensureQueueIndex()` crée, pour chaque nom, l'index qui manque.

- [ ] **Step 4 : la purge épargne**

Pour le canal `queue`, `prune()` supprime les lignes plus vieilles que la rétention sauf les ids gardés : `MAX(id)` par `(action, target)` parmi `TASK_ACTIONS`. Sous MySQL, la sous-requête des ids gardés est enveloppée dans une table dérivée (`SELECT id FROM (SELECT MAX(id) AS id … GROUP BY action, target) kept`), et la même forme sert aux trois dialectes.

- [ ] **Step 5 : lancer les tests, vérifier qu'ils passent**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr tests/src/Kernel/JournalTest.php tests/src/Kernel/JournalQueueIndexTest.php`
Expected : PASS

- [ ] **Step 6 : commit**

```bash
git add src/Kernel/Queue/QueueJournalEntry.php src/Kernel/Service/JournalSchema.php src/Kernel/Database src/Kernel/Service/Journal.php tests/src/Kernel/JournalTest.php tests/src/Kernel/JournalQueueIndexTest.php
git commit -m "feat(journal): the queue channel keeps the last row of each scheduled task, however old"
```

---

### Task 2 : le Recorder écrit l'histoire des tâches, ScheduleHistory la lit

Pendant cette tâche, l'ancien chemin (`QueueTaskRows`) est encore écrit **en plus** : la suite reste verte.

**Écart assumé par rapport à la spec :** la spec met `consecutive_failures` sur la ligne `queue.run.*`, et laisse au constructeur le choix de rendre un run trouvable par tâche. Le plan ajoute plutôt une ligne `queue.task.finished`, dont le `target` est le `task_id`, à côté de la ligne `queue.run.*`, qui garde le type de message en `target` pour l'historique et les statistiques par type. C'est sur cette ligne `queue.task.finished` que portent le compteur, l'index `(channel, action, target)` et la purge qui épargne. Le dernier run d'une tâche reste ainsi trouvable par index sans lire de JSON.

**Files :**
- Modify : `src/Kernel/Service/Queue/QueueRecorder.php`
- Create : `src/Kernel/Service/Queue/ScheduleHistory.php`
- Create : `src/Kernel/Queue/TaskHistory.php`
- Test : `tests/src/Kernel/QueueRecorderTest.php`, `tests/src/Kernel/ScheduleHistoryTest.php` (nouveau)

**Interfaces :**
- Consumes : `QueueJournalEntry::TASK_*` (Tâche 1).
- Produces :
  - `QueueRecorder::taskDeclared(ScheduledTask $task): void` : une ligne `TASK_DECLARED`, `target` = `taskId()`, contexte `{label}`, niveau info.
  - `QueueRecorder::taskDispatched(ScheduledTask $task): void` : une ligne `TASK_DISPATCHED`, contexte `{label}`. **Nouvelle signature** : l'ancienne `(task, everySeconds, nextDue)` disparaît, et ses deux appelants (le ticker, puis le bus à la Tâche 3) passent à celle-ci. Pendant la Tâche 2 elle met encore à jour `QueueTaskRows` avec `last_dispatched_at`.
  - `QueueRecorder::taskFinished(string $taskId, string $status, ?string $error): void` : en plus de la mise à jour de `QueueTaskRows`, une ligne `TASK_FINISHED`. Son niveau suit `insertRun()` ; son contexte est `{status, message, consecutive_failures}`, où `consecutive_failures` part du contexte de la dernière ligne `TASK_FINISHED` de cette tâche : 0 pour `handled`, +1 pour `failed` ou `killed`, inchangé sinon.
  - `final class TaskHistory` (readonly) : `string $taskId`, `?string $label`, `?\DateTimeImmutable $declaredAt`, `?\DateTimeImmutable $lastDispatchedAt`, `?\DateTimeImmutable $lastFinishedAt`, `?string $lastStatus`, `?string $lastError`, `int $consecutiveFailures`, et `anchor(): ?\DateTimeImmutable` qui renvoie `lastDispatchedAt ?? declaredAt`.
  - `ScheduleHistory::all(): array<string, TaskHistory>` (par `task_id`) et `ScheduleHistory::of(string $taskId): ?TaskHistory`. Une requête : les lignes dont l'id est un `MAX(id)` par `(action, target)` parmi `TASK_ACTIONS`. Les heures sont lues en UTC. Le `label` vient de la ligne d'envoi, sinon de la ligne d'apparition.

- [ ] **Step 1 : écrire les tests qui échouent**

`ScheduleHistoryTest` (SQLite jetable, `MockClock('2026-10-23 12:00:00', 'UTC')`) :
- `testATaskNeverSeenHasNoHistory` : `of('x')` vaut null, et `all()` vaut `[]`.
- `testTheAnchorIsTheLastDispatchElseTheDeclaration` : après `taskDeclared` à 12:00, l'ancre est 12:00. Après `taskDispatched` à 12:30 puis à 13:00, l'ancre et `lastDispatchedAt` valent 13:00, et `declaredAt` vaut toujours 12:00.
- `testConsecutiveFailuresCountFromTheLastHandledRun` : la suite `failed`, `failed`, `retried`, `killed` donne 3, puis `handled` donne 0. `lastStatus` vaut `handled`, et `lastError` vaut null.
- `testTheLastRunSaysHowItEnded` : `taskFinished('x', 'failed', 'boom')` donne `lastStatus` = `failed`, `lastError` = `'boom'`, et `lastFinishedAt` égal à l'heure de l'horloge.

Dans `QueueRecorderTest`, ajoutez `testTaskRowsAreJournalRowsNamedByTheirTask` : chaque méthode écrit une ligne du canal `queue` dont l'`action` est la constante attendue et le `target` le `task_id`.

- [ ] **Step 2 : lancer, vérifier l'échec**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr tests/src/Kernel/ScheduleHistoryTest.php tests/src/Kernel/QueueRecorderTest.php`
Expected : FAIL (la classe `ScheduleHistory` n'existe pas).

- [ ] **Step 3 : implémenter `TaskHistory`, `ScheduleHistory` et les trois méthodes du Recorder**

Mettez à jour les appelants de l'ancien `taskDispatched(task, every, nextDue)` dans `QueueScheduleTicker` et dans les tests (`QueueTransportTest` l. 651).

- [ ] **Step 4 : lancer les tests de la file, vérifier qu'ils passent**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr --filter 'Queue|ScheduleHistory|Journal' tests/src/Kernel`
Expected : PASS

- [ ] **Step 5 : commit**

```bash
git add src/Kernel/Service/Queue/QueueRecorder.php src/Kernel/Service/Queue/ScheduleHistory.php src/Kernel/Queue/TaskHistory.php src/Kernel/Service/Queue/QueueScheduleTicker.php tests/src/Kernel
git commit -m "feat(queue): each scheduled task's appearance, dispatches and runs are Journal rows named by the task"
```

---

### Task 3 : le ticker lit le Journal, et tout le monde lit le ticker

C'est la bascule. Après elle, plus rien ne **lit** `QueueCheckpoint`, `QueueTaskRows` ni `ScheduleGenerator` : leur suppression vient à la Tâche 4.

**Files :**
- Modify : `src/Kernel/Service/Queue/QueueScheduleTicker.php` (réécriture du cœur)
- Modify : `src/Kernel/Service/Queue/QueueBus.php` (ligne d'envoi)
- Modify : `src/Kernel/Service/Queue/QueueRequestTrigger.php:159`, `src/Kernel/Service/Queue/QueueFallback.php:113`, ainsi que leurs constructeurs
- Modify : `src/Admin/Service/JobsOverview.php:347`, `src/Kernel/Service/Queue/QueueHealthChecks.php` (tâches en retard, tâches en échec, `onlyDeclared`), `src/Admin/Api/JobsApiController.php:142`, `src/Admin/Message/RunMaintenanceHooksHandler.php:73`
- Modify : `src/Kernel/Service/CacheClearer.php:23` (`QueueScheduleTicker::LOCK`)
- Test : `tests/src/Kernel/QueueScheduleTickerTest.php` (réécrit), `tests/src/Kernel/QueueTestKit.php`, `QueueConsumerTest`, `QueueHealthChecksTest`, `QueueFallbackTest`, `QueueRequestTriggerTest`, `QueueBusTest`, `tests/src/Contact/SendDigestTest.php`, `tests/src/Import/SyncDataSourceTest.php`, `tests/src/Admin/MaintenanceTasksTest.php`

**Interfaces :**
- Consumes : `ScheduleHistory::all()` et `of()`, `TaskHistory::anchor()`, `QueueRecorder::taskDeclared()` et `taskDispatched()` (Tâche 2).
- Produces :
  - `QueueScheduleTicker::LOCK = 'cache/queue-schedule.lock'`.
  - Constructeur : `(iterable $providers, QueueBus $bus, ScheduleHistory $history, QueueRecorder $recorder, RuntimeLock $locks, ClockInterface $clock, HibernationService $hibernation, ?Journal $journal = null)`.
  - `tick(): int` : même contrat qu'aujourd'hui. Il renvoie le nombre de messages mis en file et lève une exception quand le Journal ne peut être lu.
  - `dueAt(): ?\DateTimeImmutable` : même sens que l'ancien `QueueCheckpoint::dueAt()`. `null` veut dire qu'une tâche déclarée n'a pas d'ancre, ou qu'aucune tâche ne tournera plus : dans les deux cas, due tout de suite. Sinon c'est le minimum, sur les tâches déclarées, de la prochaine échéance après l'ancre. Le résultat est mémorisé en mémoire, comme `dueAt`/`learntAt` aujourd'hui, et relu après `RECHECK_SECONDS`.
  - `tasks(): list<array{taskId: string, label: string, everySeconds: int, lastDispatchedAt: ?\DateTimeImmutable, nextDueAt: ?\DateTimeImmutable, lastFinishedAt: ?\DateTimeImmutable, lastStatus: ?string, lastError: ?string, consecutiveFailures: int}>` : la forme exacte de l'actuel `QueueStatistics::tasks()`. Ce sont les tâches déclarées, triées par `taskId`. Quand un fournisseur lève une exception, s'y ajoutent les tâches de l'histoire qui ne sont plus déclarées, avec `everySeconds` à 0 et `nextDueAt` à null. Une tâche jamais vue a `nextDueAt` à null.
  - `task(string $taskId): ?array` : un élément de `tasks()`.
  - `runNow(string $taskId): bool` : met le message en file par le bus, ce qui écrit la ligne d'envoi et décale donc le planning.
  - `declaredTaskIds()` reste : quand un fournisseur lève une exception, on y ajoute les ids de `ScheduleHistory::all()`.
  - `schedule()` reste.
- `QueueBus::dispatch()` : un message `ScheduledTask` sans `ReceivedStamp`, que le transport a accepté (il porte un `TransportMessageIdStamp`, qu'il ait été inséré ou absorbé), donne `QueueRecorder::taskDispatched($message)`. Un envoi qui échoue n'écrit rien. Les nouvelles tentatives et le « Relancer » ne passent pas par le bus et n'écrivent donc rien. Le bus reçoit `QueueRecorder` dans son constructeur.

**L'algorithme d'un tick** : c'est la décision qu'un implémenteur ne peut pas deviner.

```
tick():
  now ← clock ; retour 0 si le mémo dueAt est dans le futur et n'a pas expiré
  wiki en hibernation → mémo = now + RECHECK, retour 0
  prendre le verrou LOCK (RuntimeLock::acquire + tryLock) ; s'il est déjà pris → retour 0 ;
    si le fichier ne peut être créé → RuntimeException
  sous le verrou :
    [schedule, complete] ← build()          // un schedule neuf : des triggers neufs
    histories ← history->all()
    pour chaque RecurringMessage, chacun de ses messages :
      message non ScheduledTask → journal error 'queue.schedule_provider_failed', ignoré
      anchor ← histories[taskId]?->anchor()
      anchor null → recorder->taskDeclared(message) ; suivant
      next ← trigger->getNextRunDate(anchor)        // PREMIER appel fait à ce trigger
      next non null et next <= now → bus->dispatch(message) ; compté s'il porte un TransportMessageIdStamp
  relâcher le verrou (finally) ; mémo ← dueAt() recalculé sur un schedule neuf
```

`PeriodicalTrigger` fixe son `from` au premier `getNextRunDate()` : un trigger ne sert donc qu'à une ancre, et chaque calcul (`tick`, `dueAt`, `tasks`) construit son propre schedule. Pour `tasks()`, `everySeconds` vaut `getNextRunDate(next) - next`, calculé **après** l'ancrage. Une tâche sans ancre prend `periodOf()` sur un trigger neuf.

- [ ] **Step 1 : réécrire `QueueScheduleTickerTest`**

Gardez `START = '2026-10-23 12:00:00'`, la `MockClock` et le fournisseur `$this->tasks` (id ⇒ intervalle). Construisez le ticker sur `ScheduleHistory` et `QueueTestKit::locksIn()`. Supprimez les tests qui portaient sur le checkpoint : `testTheCheckpointSurvivesANewTicker`, `testTheCheckpointIsKeptInTheTriples` et `testALongLivedTickerNoticesADueTimeWrittenElsewhere` (ce dernier devient `testALongLivedTickerSeesADispatchWrittenElsewhere`). Les tests et leurs assertions :

- `testATaskSeenForTheFirstTimeIsDeclaredAndWaitsItsFirstSlot` : le premier `tick()` vaut 0, `ScheduleHistory::of('test-task')->declaredAt` vaut 12:00, rien n'attend et `dueAt()` vaut 12:30. À 12:29, `tick()` vaut 0, et une seule ligne `queue.task.declared` existe.
- `testADueTaskIsDispatchedOnceAndRecorded` : `tick()` à 12:00, puis à 12:31 il vaut 1 ; un second `tick()` vaut 0. `lastDispatchedAt` = 12:31, `nextDueAt` = **13:01** (l'intervalle part de l'envoi), `everySeconds` = 1800, et `dedup_key` = `task:test-task`.
- `testAnIntervalCountsFromTheAnchorWhateverAskedFirst` : appelez `tasks()`, puis `declaredTaskIds()`, puis `tick()` à 12:31 ; `tick()` vaut 1. Rappelez `tasks()` à 12:32 ; `nextDueAt` vaut 13:01, et un `tick()` à 12:45 vaut 0.
- `testAMissedTaskIsCaughtUpOnlyOnce` : ancre à 12:00. À 17:00, `tick()` vaut 1, puis 0, puis à 17:29 il vaut encore 0.
- `testACronTaskWaitsForItsNextSlot` : fournisseur `RecurringMessage::cron('0 7 * * *', new QueueTestTask('daily'), 'UTC')`. Le premier tick à 12:00 déclare la tâche. Le lendemain à 06:59, rien. À 07:00, 1.
- `testATaskThatComesBackRunsOnceIfItMissedASlot` : la tâche est envoyée, puis retirée de `$this->tasks` pendant 2 h, puis remise. Le tick suivant vaut 1, et le nombre de lignes `queue.task.declared` reste à 1.
- `testRunNowShiftsTheSchedule` : ancre à 12:00. `runNow('test-task')` à 12:10 vaut true et met un message en file. `nextDueAt` devient 12:40, et un `tick()` à 12:31 vaut 0.
- `testAnAbsorbedDispatchStillMovesTheAnchor` : un message de la tâche attend déjà. `runNow` à 12:10 vaut true, rien de plus n'est mis en file, et `lastDispatchedAt` vaut 12:10.
- `testOnlyOneProcessTicksAtATime` : quand un autre handle tient `LOCK`, `tick()` vaut 0 et n'écrit aucune ligne.
- `testALongLivedTickerSeesADispatchWrittenElsewhere` : le ticker A a mémorisé `dueAt` = 12:30. Le ticker B envoie à 12:31. Après `RECHECK_SECONDS`, A lit `dueAt()` = 13:01.
- `testNoTaskDeclaredMeansNothingIsEverDue` : `$this->tasks = []`. `tick()` vaut 0, et aucune ligne n'est écrite.
- `testARecurringMessageThatIsNotAScheduledTaskIsJournaledAndSkipped` : le fournisseur déclare `RecurringMessage::every('30 minutes', new QueueTestMessage('x'))`. Le tick vaut 0 et `queue.schedule_provider_failed` est journalisé.
- Gardez et adaptez : `testTheDeclaredTasksAreWhatTheProvidersDeclareNow`, `testAProviderThatThrowsLeavesTheOthersScheduledAndKeepsItsTasks` (il garde dans `tasks()` les tâches tirées de l'histoire), `testATaskDeclaredTwiceIsScheduledOnceAndStopsNothing`, `testConsecutiveFailuresAreCounted` (lus par `tasks()`), `testAHandledMessageOfATaskIsRecordedOnTheTask`, `testAHibernatedWikiTicksNothing`, `testAWokenWikiRunsEachMissedTaskOnce`, `testThePruneTaskForgetsWhatIsPastItsRetention`, `testTheKernelPrunesTheQueueEveryDay` et `testOnARealServer`.
- `testADueTickForgetsTheTasksThatLeftTheSchedule` devient `testATaskThatLeftTheScheduleIsNoLongerListed` : `tasks()` ne la montre plus, et aucune ligne n'est supprimée.
- `testATickWithNothingDueTouchesNoTable` devient `testATickWithNothingDueWritesNothing` : aucun `INSERT`, `UPDATE` ni `DELETE` dans le journal des requêtes, et au plus une lecture du Journal pendant `RECHECK_SECONDS`.

Dans `QueueBusTest`, ajoutez :
- `testAScheduledTaskSentWritesItsDispatch` : une ligne `queue.task.dispatched` par `dispatch()`, y compris absorbé.
- `testAFailedSendWritesNoDispatch` : le transport lève une exception, aucune ligne n'est écrite.
- `testAPlainMessageWritesNoDispatch`.

- [ ] **Step 2 : lancer, vérifier l'échec**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr tests/src/Kernel/QueueScheduleTickerTest.php tests/src/Kernel/QueueBusTest.php`
Expected : FAIL (mauvais constructeur, `nextDueAt` à 13:00 au lieu de 13:01, méthode `tasks()` absente).

- [ ] **Step 3 : réécrire le ticker et brancher le bus**

Suivez l'algorithme ci-dessus. Le ticker ne dépend plus de `QueueCheckpoint` ni de `ScheduleGenerator`, et `dispatch()` n'appelle plus `recorder->taskDispatched()` : c'est désormais le bus qui l'écrit. `forgetTasksExcept()` n'est plus appelé.

- [ ] **Step 4 : basculer les lecteurs**

- `QueueRequestTrigger::hasWork()` et `QueueFallback::entersNow()` lisent `QueueScheduleTicker::dueAt()` au lieu de `QueueCheckpoint::dueAt()`, avec la même logique autour (`null` = dû, ou depuis le `WATCH_STAMP`). Le ticker leur est injecté. Le cycle bus → déclencheur reste coupé par la closure existante.
- `JobsOverview` et `QueueHealthChecks` lisent `$ticker->tasks()`. `QueueHealthChecks::onlyDeclared()` disparaît, puisque `tasks()` ne liste que les tâches déclarées.
- `JobsApiController::runTask()` lit le libellé dans `$ticker->task($taskId)`.
- `RunMaintenanceHooksHandler::previousRun()` lit `ScheduleHistory::of($task->taskId())?->lastFinishedAt`.
- `CacheClearer::KEPT` référence `QueueScheduleTicker::LOCK`.

- [ ] **Step 5 : porter les tests de modules sur le vrai ticker**

`SendDigestTest`, `SyncDataSourceTest` et `MaintenanceTasksTest` simulaient les ticks avec `ScheduleGenerator` et `InMemoryCheckpoint`. Ils utilisent désormais `QueueTestKit(clock: $mockClock, schedule: $provider)` et `$kit->ticker->tick()`. Supprimez `InMemoryCheckpoint`. Leurs attentes changent sur deux points :
- un digest qui apparaît attend son prochain 7 h, son prochain lundi ou son prochain 1er ;
- une synchronisation se compte depuis son dernier envoi, plus depuis le début du planning.

Mettez à jour `QueueTestKit` : plus de `$checkpoint`. Le kit expose `ScheduleHistory $history` et construit `QueueFallback` et `QueueRequestTrigger` avec le ticker. Adaptez `QueueConsumerTest` (l. 747-755 : « not ticked » se lit sur `ScheduleHistory::all() === []`, et le cas « checkpoint illisible » devient « table du Journal absente »), `QueueHealthChecksTest`, `QueueFallbackTest` et `QueueRequestTriggerTest`.

- [ ] **Step 6 : lancer la suite de la file et des modules**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr --filter 'Queue|Schedule|Journal|SendDigest|SyncDataSource|MaintenanceTasks|Jobs|CacheClearer' tests`
Expected : PASS

- [ ] **Step 7 : commit**

```bash
git add src tests
git commit -m "refactor(queue): the Scheduler reads what is due from the Journal, and so do the jobs page and the health checks"
```

---

### Task 4 : ce qui ne sert plus disparaît

**Files :**
- Delete : `src/Kernel/Service/Queue/QueueCheckpoint.php`, `src/Kernel/Queue/ScheduleGenerator.php`, `src/Kernel/Service/Queue/QueueTaskRows.php`
- Modify : `src/Kernel/Service/Queue/QueueRecorder.php` (plus de `QueueTaskRows` ni de `taskIds()`/`forgetTasksExcept()`), `src/Kernel/Service/Queue/QueueStatistics.php` (plus de `tasks()`, `scheduledTask()`, `task()` ni de `QueueTaskRows`), `src/Kernel/Service/Queue/QueueSchema.php` (plus de `SCHEDULE_QUEUE`)
- Modify : `queueDdl()` des trois dialectes (plus de colonne `task_id` ni d'index `uniq_task_id`)
- Modify : `src/Kernel/Database/DumpRewriter.php:196` (plus de condition sur les triples)
- Modify : `src/migrations/20261023120000_TheQueueHasItsTables.php` (docblock : « its messages in one table, and its runs, passes and scheduled tasks in the Journal's `queue` channel »)
- Test : `QueueSchemaTest` (l. 44 et 82), `BackupsLeaveTheQueueAloneTest` (l. 21-24, 213, 230-249, 350-377 et 488), `QueueTransportTest` (l. 25 et 650-675), `QueueStatisticsSeenTest`

**Interfaces :**
- Consumes : tout ce que la Tâche 3 a branché.
- Produces : `QueueRecorder::__construct(DbService $db, JournalSchema $journal, ClockInterface $clock)` et `QueueStatistics::__construct(DbService $db, QueueSchema $schema, JournalSchema $journal, QueueTransport $transport, ClockInterface $clock)`.

- [ ] **Step 1 : adapter les tests au monde sans checkpoint**

- `BackupsLeaveTheQueueAloneTest` : la commande mysqldump attendue n'a plus de `--where=NOT (resource = 'queue:checkpoint')`, et les triples sont dumpés en entier. Le test de restauration vérifie que les lignes `queue.task.dispatched` vivantes survivent à une restauration, et qu'une tâche déjà passée ne repasse pas.
- `QueueSchemaTest` : la table n'a plus de `task_id`.
- `QueueTransportTest` (l. 650-675) : le test qui vérifiait que le transport ignore `__schedule` disparaît.

- [ ] **Step 2 : lancer, vérifier l'échec**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr tests/src/Kernel/BackupsLeaveTheQueueAloneTest.php tests/src/Kernel/QueueSchemaTest.php`
Expected : FAIL (le dump exclut encore le checkpoint, la colonne existe encore).

- [ ] **Step 3 : supprimer et simplifier**

Supprimez les fichiers et les références listés ci-dessus. `grep -rn "QueueCheckpoint\|QueueTaskRows\|ScheduleGenerator\|SCHEDULE_QUEUE\|__schedule\|queue:checkpoint\|task_id" src tests templates` ne doit plus rien trouver, hors contexte JSON `task_id` des lignes `queue.run.*` et hors `ScheduledTask::MAX_ID_LENGTH`. Mettez à jour le docblock de `ScheduledTask::taskId()` : l'id sert de `target` aux lignes de tâche du Journal.

- [ ] **Step 4 : lancer la suite complète**

Run : `composer test`
Expected : PASS, sans nouvel échec par rapport à `doryphore-dev`. La mémoire `dev-wiki-test-setup` décrit ce qu'il faut au wiki de dev pour que la suite soit verte.

- [ ] **Step 5 : commit**

```bash
git add -A src tests
git commit -m "refactor(queue): the checkpoint, the __schedule rows and the hand-written generator are gone"
```

---

### Task 5 : « Lancer » dit qu'il décale le planning

**Files :**
- Modify : `templates/admin/jobs-tab-tasks.twig`
- Modify : `src/Admin/Api/JobsApiController.php` (`runTask`, docblock et message)
- Modify : `src/lang/yeswiki_fr.php`, `src/lang/yeswiki_en.php`
- Modify : `docs/fr/async.md:335-338`
- Test : `tests/src/Admin/JobsApiControllerTest.php` (ou le test existant de `runTask`, à trouver avec `grep -rn "tasks/.*run\|runTask" tests`)

**Interfaces :**
- Consumes : `QueueScheduleTicker::runNow()` et `task()` (Tâche 3).
- Produces : les clés de langue `ADMIN_JOBS_RUN_TASK_HINT` et `ADMIN_JOBS_TASK_QUEUED` (nouveau texte, avec `%{next}`).

Les textes fixés (fr / en) :
- `ADMIN_JOBS_RUN_TASK_HINT` : `Lancer met la tâche en file tout de suite ; sa prochaine échéance se compte alors à partir de ce lancement.` / `Run queues the task now; its next run is then counted from this one.`
- `ADMIN_JOBS_TASK_QUEUED` : `« %{task} » est en attente : elle tournera au prochain passage. Prochaine échéance : %{next}.` / `"%{task}" is waiting: it will run at the next pass. Next run: %{next}.`

- [ ] **Step 1 : écrire le test qui échoue**

`testRunningATaskSaysWhenItIsNextDue` : la réponse de `POST api/admin/jobs/tasks/{id}/run` contient la prochaine échéance, c'est-à-dire l'heure du lancement plus l'intervalle de la tâche, formatée comme sur l'onglet *Tâches*.

- [ ] **Step 2 : lancer, vérifier l'échec**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr --filter testRunningATaskSaysWhenItIsNextDue tests`
Expected : FAIL

- [ ] **Step 3 : implémenter**

- La ligne d'aide va sous le tableau, dans `<p class="yw-text-muted">`, et le même texte sert de `title` au bouton.
- `runTask` passe `next` = `$ticker->task($taskId)['nextDueAt']`, mis en forme comme `JobsOverview` met en forme `next`.
- Son docblock devient : « Queues a run of a scheduled task now; its next run is counted from this one. »
- Dans `docs/fr/async.md`, la phrase « **Lancer** met une tâche dans la file tout de suite, sans changer son calendrier : pratique pour vérifier qu'une correction a marché. » devient : « **Lancer** met une tâche dans la file tout de suite, et sa prochaine échéance se compte alors à partir de ce lancement : pratique pour vérifier qu'une correction a marché. »

- [ ] **Step 4 : lancer, vérifier que ça passe**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr --filter 'Jobs' tests`
Expected : PASS

- [ ] **Step 5 : commit**

```bash
git add templates/admin/jobs-tab-tasks.twig src/Admin/Api/JobsApiController.php src/lang/yeswiki_fr.php src/lang/yeswiki_en.php docs/fr/async.md tests
git commit -m "feat(admin): running a task by hand says it moves the task's next run"
```

---

### Task 6 : l'ADR et le coût de la vérification de fin de requête

**Files :**
- Modify : `docs/adr/0030-background-work-goes-through-one-queue.md`
- Create : `tests/src/Kernel/ScheduleDueCostTest.php`

- [ ] **Step 1 : mesurer**

`ScheduleDueCostTest::testKnowingWhetherTheScheduleIsDueCostsTwoQueriesAtMost` utilise une connexion SQLite en `debug: true` (le journal des requêtes, comme `testATickWithNothingDueTouchesNoTable`). Le Journal compte 10 000 lignes `queue.run.handled` et une histoire pour 12 tâches, et le fournisseur déclare ces 12 tâches. Sur un ticker neuf, `dueAt()` fait au plus 2 requêtes. Ajoutez un `assertLessThan(0.05, $seconds)` sur la durée. Si l'assertion de durée échoue, arrêtez-vous et rapportez le chiffre à l'utilisateur, sans ajouter d'état persistant (anti-objectif de la spec).

- [ ] **Step 2 : lancer**

Run : `php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result --stderr tests/src/Kernel/ScheduleDueCostTest.php`
Expected : PASS

- [ ] **Step 3 : mettre à jour l'ADR-0030**

- Diagramme : le nœud `ticker` devient « QueueScheduleTicker<br/>ScheduleHistory », la table `queue` devient `default · failed`, et le ticker lit et écrit le Journal.
- Remplacez les paragraphes sur `__schedule` et le checkpoint (l. 87-88, 157-162, 280-285, 293 et 301) par :
  - le Journal comme seule source de vérité ;
  - les trois actions `queue.task.*`, avec la tâche en `target` ;
  - la purge qui épargne la dernière ligne de chaque action par tâche ;
  - l'ancre (dernier envoi, sinon apparition) ;
  - le lancement manuel qui décale le planning.
- Réécrivez la section « The Scheduler's checkpoint and ticker » (l. 480-495) en « The Scheduler's history and ticker » : verrou de fichier dans le `cache/` partagé, premier appel qui fige un trigger `every`, `dueAt()` à `null`.
- Dans la section des extensions (l. 944), une tâche récurrente doit implémenter `ScheduledTask`, sinon elle est journalisée et ignorée.

- [ ] **Step 4 : commit**

```bash
git add docs/adr/0030-background-work-goes-through-one-queue.md tests/src/Kernel/ScheduleDueCostTest.php
git commit -m "docs(adr): the Journal is the Scheduler's memory"
```
