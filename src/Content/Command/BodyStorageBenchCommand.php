<?php

namespace YesWiki\Content\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Identity\Service\AclService;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;

/**
 * `./yeswicli content:body-bench` -- ticket 19's measurement: is a native JSON column worth
 * rebuilding every install's `pages` table for?
 *
 * ## What is being compared, and why it needs its own corpus
 *
 * Ticket 18's `search:seed` measures the *search index*, which is a separate table with its
 * own full-text index (ADR-0015), so it cannot answer this. What is left of the JSON-column
 * question after ticket 18 is the **field-path** half: `SearchManager` and every bazar filter
 * reach into `body` by path all day long, and today `body` is a text column that each dialect
 * has to interpret as JSON on every row it looks at:
 *
 * - **MySQL** parses the text into a JSON document per row, per expression.
 * - **PostgreSQL** does worse than parse: `SqlDialect::jsonExtract()` emits
 *   `CASE WHEN body ~ '^\s*\{' THEN body::jsonb #>> ARRAY[...] END` -- a regex match *and* a
 *   text-to-jsonb cast for every row of the scan, because a TEXT column may hold something
 *   that is not JSON at all.
 * - **SQLite** has no JSON column type, so nothing changes there whatever is declared; it is
 *   measured anyway, as the control.
 *
 * A native column removes the parse (MySQL stores a compact binary form) and, on PostgreSQL,
 * the guard and the cast with it. Whether that is worth a full table rebuild on installs the
 * maintainer does not control is a number, not an opinion -- hence this command.
 *
 *     ./yeswicli content:body-bench --count=200000    # build both corpora
 *     ./yeswicli content:body-bench --benchmark       # time the query set on each
 *     ./yeswicli content:body-bench --indexed         # ...and again with the hot path indexed
 *     ./yeswicli content:body-bench --alter           # time the migration itself
 *     ./yeswicli content:body-bench --clean           # drop the corpora
 *
 * The corpora live in their own tables and `pages` is never touched: a benchmark that
 * rewrote the real table would be a migration, and this ticket is what decides whether to
 * write one.
 */
class BodyStorageBenchCommand extends Command
{
    /** Suffixes of the two bench tables, both prefixed like every other table. */
    private const TEXT_TABLE = 'bodybench_text';
    private const JSON_TABLE = 'bodybench_json';

    /**
     * The corpus's forms. A real wiki's entries are not uniform -- an agenda entry and a
     * directory entry share almost no field names -- and that matters here: a filter on
     * `bf_date_debut` has to look inside every row's body to discover that most of them do
     * not carry one, which is exactly the work a native column makes cheaper.
     *
     * @var array<int, list<string>>
     */
    private const FORMS = [
        1 => ['bf_titre', 'bf_description', 'bf_ville', 'bf_email', 'bf_site', 'bf_categorie', 'bf_tags'],
        2 => ['bf_titre', 'bf_description', 'bf_date_debut', 'bf_date_fin', 'bf_lieu', 'bf_public', 'bf_tags'],
        3 => ['bf_titre', 'bf_description', 'bf_nom', 'bf_prenom', 'bf_structure', 'bf_competences', 'bf_tags'],
    ];

    private const WORDS = [
        'atelier', 'jardin', 'partage', 'reunion', 'projet', 'commun', 'ressource',
        'benevole', 'collectif', 'quartier', 'formation', 'agenda', 'permanence',
        'chantier', 'cantine', 'fresque', 'mobilite', 'compost', 'ruche', 'entraide',
    ];

    /** How many distinct values a filtered field takes, so a filter matches a slice. */
    private const CARDINALITY = 200;

    public function __construct(private readonly ContainerInterface $services)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('content:body-bench')
            ->setDescription('Measure field-path filtering over a TEXT body against a native JSON body (ticket 19)')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'How many Contents to seed', '200000')
            ->addOption('benchmark', null, InputOption::VALUE_NONE, 'Time the query set against both corpora')
            ->addOption('indexed', null, InputOption::VALUE_NONE, 'Also measure with the hot path indexed')
            ->addOption('alter', null, InputOption::VALUE_NONE, 'Time converting the TEXT corpus in place')
            ->addOption('clean', null, InputOption::VALUE_NONE, 'Drop both corpora and exit');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = $this->services->get(DbService::class);
        $driver = $db->getDriver();

        if ($input->getOption('clean')) {
            foreach ([self::TEXT_TABLE, self::JSON_TABLE] as $table) {
                $db->query('DROP TABLE IF EXISTS ' . $this->table($table));
            }
            $output->writeln('<info>Bench corpora dropped.</info>');

            return Command::SUCCESS;
        }

        $output->writeln("Driver: <info>{$driver}</info>, native JSON type: <info>"
            . ($this->nativeJsonType() ?? 'none -- TEXT is all this dialect has') . '</info>');

        $ran = false;
        if ($input->getOption('benchmark') || $input->getOption('indexed') || $input->getOption('alter')) {
            if ($input->getOption('benchmark')) {
                $this->benchmark($output, false);
                $ran = true;
            }
            if ($input->getOption('indexed')) {
                $this->index($output);
                $this->benchmark($output, true);
                $ran = true;
            }
            if ($input->getOption('alter')) {
                $this->timeTheAlter($output);
                $ran = true;
            }
        }

        if (!$ran) {
            $this->seed($output, max(1, (int)$input->getOption('count')));
        }

        return Command::SUCCESS;
    }

    /** Fully-qualified, trimmed -- `prefixTable()` pads with spaces. */
    private function table(string $suffix): string
    {
        return trim($this->services->get(DbService::class)->prefixTable($suffix));
    }

    /**
     * The type a native JSON body is declared as, or null where there is no such thing.
     *
     * `SqlDialect::jsonColumnType()` returns `TEXT` on SQLite because that is the truthful
     * answer for a column declaration; here the distinction has to be "is there a second
     * thing to compare against", and on SQLite there is not.
     */
    private function nativeJsonType(): ?string
    {
        $type = $this->services->get(DbService::class)->jsonColumnType();

        return $type === 'TEXT' ? null : $type;
    }

    /**
     * Reading a path out of a body -- once for a TEXT column, once for a native one.
     *
     * Both forms come from the dialect, so the benchmark measures the SQL the wiki really
     * runs and cannot drift from it. That is the whole reason `jsonExtractText()` exists as a
     * method rather than as a comment: the guarded read did not stop being needed, it stopped
     * being needed *for this column*.
     */
    private function extract(string $field, bool $native): string
    {
        $db = $this->services->get(DbService::class);

        return $native
            ? $db->jsonExtract('body', '$.' . $field)
            : $db->jsonExtractText('body', '$.' . $field);
    }

    private function seed(OutputInterface $output, int $count): void
    {
        $db = $this->services->get(DbService::class);
        $native = $this->nativeJsonType();

        foreach ([self::TEXT_TABLE => 'TEXT', self::JSON_TABLE => $native ?? 'TEXT'] as $suffix => $bodyType) {
            $table = $this->table($suffix);
            $db->query("DROP TABLE IF EXISTS {$table}");
            $db->query(
                "CREATE TABLE {$table} ("
                . $this->idColumn()
                . ' tag VARCHAR(191) NOT NULL,'
                . " body {$bodyType} NOT NULL,"
                // `metadata` is JSON too, and stayed TEXT in ticket 38 -- so it gets the same
                // two treatments, and the query set below can ask whether it should have.
                . " metadata {$bodyType} NOT NULL,"
                . " owner VARCHAR(191) NOT NULL DEFAULT '',"
                . " latest VARCHAR(1) NOT NULL DEFAULT 'N',"
                . " type VARCHAR(30) NOT NULL DEFAULT 'entry'"
                . ')'
            );
        }

        $output->writeln("Seeding {$count} Contents into each corpus...");
        $startedAt = microtime(true);

        $columns = ' (tag, body, metadata, owner, latest, type) VALUES (' . SqlParameters::placeholders(6) . ')';
        $textStatement = $db->prepare('INSERT INTO ' . $this->table(self::TEXT_TABLE) . $columns);
        $jsonStatement = $db->prepare('INSERT INTO ' . $this->table(self::JSON_TABLE) . $columns);

        // One transaction around the lot. Without it SQLite fsyncs per statement and seeding
        // becomes the slowest part of a benchmark that is not measuring writes at all.
        $db->transactional(function () use ($count, $output, $textStatement, $jsonStatement): void {
            for ($i = 0; $i < $count; $i++) {
                $row = ['BodyBench' . $i, $this->body($i), $this->metadata($i), 'BenchUser' . ($i % 50), 'Y', 'entry'];
                $textStatement->execute($row);
                $jsonStatement->execute($row);
                if ($i > 0 && $i % 50000 === 0) {
                    $output->writeln('  ... ' . $i . ' rows');
                }
            }
        });

        $output->writeln(sprintf(
            '<info>Seeded %d rows into each corpus in %.1fs.</info>',
            $count,
            microtime(true) - $startedAt
        ));
    }

    /** SQLite and PostgreSQL spell an auto-incrementing key differently from MySQL. */
    private function idColumn(): string
    {
        return match ($this->services->get(DbService::class)->getDriver()) {
            'sqlite' => 'id INTEGER PRIMARY KEY AUTOINCREMENT,',
            'pgsql' => 'id SERIAL PRIMARY KEY,',
            default => 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,',
        };
    }

    /**
     * One entry's body: deterministic, so two runs measure the same corpus, and shaped like
     * a real one -- a form's own fields plus the two every Content carries.
     */
    private function body(int $i): string
    {
        $formId = array_keys(self::FORMS)[$i % count(self::FORMS)];
        $body = [
            'form_id' => (string)$formId,
            'content_type' => 'entry',
            'created_at' => '2026-01-01 00:00:00',
            'updated_at' => '2026-01-02 00:00:00',
        ];
        foreach (self::FORMS[$formId] as $position => $field) {
            $word = self::WORDS[($i * 7 + $position * 13) % count(self::WORDS)];
            $body[$field] = match ($field) {
                // the filtered field: few enough distinct values that a filter matches a slice
                'bf_ville', 'bf_lieu', 'bf_structure' => $word . (($i * 31) % self::CARDINALITY),
                // a checkbox: the comma-joined list every multi-value filter does a LIKE over
                'bf_tags', 'bf_categorie', 'bf_public', 'bf_competences' => implode(',', [
                    $word . (($i * 3) % 20),
                    $word . (($i * 11) % 20),
                ]),
                'bf_description' => str_repeat($word . ' ', 40),
                default => $word . $i,
            };
        }

        return (string)json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    /**
     * One Content's `metadata`, which is where its ACLs live.
     *
     * A realistic mix rather than all-the-same: most Contents carry no explicit read ACL at
     * all (the wiki falls back to `default_read_acl`), and a minority are restricted. The
     * predicate takes a genuinely different path through those two cases, so a corpus that
     * seeded only one of them would measure the wrong one.
     */
    private function metadata(int $i): string
    {
        $acls = match ($i % 10) {
            0, 1 => ['read' => '@admins'],
            2 => ['read' => "%\n+"],
            default => [],
        };

        return (string)json_encode($acls === [] ? ['theme' => 'default'] : ['theme' => 'default', 'acls' => $acls]);
    }

    /**
     * The read-ACL predicate, over whichever form of `metadata` is being measured.
     *
     * Built by `AclService` itself rather than imitated here: the shape is the point. It
     * repeats the extraction expression once per needed ACL entry for the "granted" test, once
     * more each for "denied", and again for the null checks -- so an anonymous visitor
     * evaluates it about four times per row and a logged-in administrator in a few groups more
     * like a dozen. Whatever that expression costs, `metadata` pays it that many times over on
     * every listing query in the wiki.
     */
    private function aclPredicate(bool $native): string
    {
        $db = $this->services->get(DbService::class);
        $expression = $native
            ? $db->jsonExtract('metadata', '$.acls.read')
            : $db->jsonExtractText('metadata', '$.acls.read');

        $fragment = $this->services->get(AclService::class)->aclColumnPredicate($expression, 'owner');

        // interpolated rather than bound, because the timing loop runs the SQL as one string;
        // every value here is the benchmark's own, never input
        $sql = $fragment->sql;
        foreach ($fragment->params as $value) {
            $sql = (string)preg_replace('/\?/', "'" . str_replace("'", "''", (string)$value) . "'", $sql, 1);
        }

        return $sql === '' ? '1=1' : $sql;
    }

    /**
     * The query set: the shapes `SearchManager` and `BazarListService` really emit.
     *
     * @return array<string, callable(bool): string> label => builder given "native column?"
     */
    private function queries(): array
    {
        return [
            // every entry list starts here, and on a wiki with one big form it is the filter
            // that decides how much work everything after it does
            'filter on form_id' => fn (bool $n) => 'SELECT COUNT(*) FROM ' . $this->corpus($n)
                . " WHERE latest = 'Y' AND " . $this->extract('form_id', $n) . " = '2'",
            // a bazar facet: equality on one field of one form
            'filter on one field' => fn (bool $n) => 'SELECT COUNT(*) FROM ' . $this->corpus($n)
                . " WHERE latest = 'Y' AND " . $this->extract('bf_ville', $n) . " = 'jardin42'",
            // a checkbox facet: the field holds a comma-joined list, so it is a LIKE
            'filter on a checkbox' => fn (bool $n) => 'SELECT COUNT(*) FROM ' . $this->corpus($n)
                . " WHERE latest = 'Y' AND " . $this->extract('bf_tags', $n) . " LIKE '%atelier7%'",
            // two facets at once, which is what the rail's filters produce
            'two filters' => fn (bool $n) => 'SELECT COUNT(*) FROM ' . $this->corpus($n)
                . " WHERE latest = 'Y' AND " . $this->extract('form_id', $n) . " = '1'"
                . ' AND ' . $this->extract('bf_categorie', $n) . " LIKE '%partage3%'",
            // the SELECT shape: one extraction per displayed field, then order and page
            'project 7 fields, ordered' => fn (bool $n) => 'SELECT tag, '
                . implode(', ', array_map(
                    fn (string $f) => $this->extract($f, $n) . ' AS ' . str_replace('bf_', 'f_', $f),
                    self::FORMS[1]
                ))
                . ' FROM ' . $this->corpus($n)
                . " WHERE latest = 'Y' AND " . $this->extract('form_id', $n) . " = '1'"
                . ' ORDER BY ' . $this->extract('bf_titre', $n) . ' LIMIT 20',
            // metadata's turn. This one touches `body` not at all, so what it measures is the
            // read-ACL predicate and nothing else -- the question ticket 38 deferred.
            'ACL predicate alone' => fn (bool $n) => 'SELECT COUNT(*) FROM ' . $this->corpus($n)
                . " WHERE latest = 'Y' AND " . $this->aclPredicate($n),
            // ...and what a visitor actually causes: a filtered, ACL-checked, projected list.
            'entry list, ACL checked' => fn (bool $n) => 'SELECT tag, '
                . implode(', ', array_map(
                    fn (string $f) => $this->extract($f, $n) . ' AS ' . str_replace('bf_', 'f_', $f),
                    self::FORMS[1]
                ))
                . ' FROM ' . $this->corpus($n)
                . " WHERE latest = 'Y' AND " . $this->extract('form_id', $n) . " = '1'"
                . ' AND ' . $this->aclPredicate($n)
                . ' ORDER BY ' . $this->extract('bf_titre', $n) . ' LIMIT 20',
        ];
    }

    private function corpus(bool $native): string
    {
        return $this->table($native ? self::JSON_TABLE : self::TEXT_TABLE);
    }

    private function benchmark(OutputInterface $output, bool $indexed): void
    {
        $db = $this->services->get(DbService::class);
        $native = $this->nativeJsonType();

        $output->writeln('');
        $output->writeln('<comment>' . ($indexed ? 'Indexed' : 'Unindexed')
            . ' -- median of 5 runs, in milliseconds</comment>');
        $output->writeln(sprintf('%-28s %12s %12s %10s', 'query', 'TEXT', $native ?? 'TEXT (2)', 'change'));

        foreach ($this->queries() as $label => $build) {
            $text = $this->median($build(false));
            $json = $this->median($build(true));
            $change = $text > 0 ? sprintf('%+.0f%%', (($json - $text) / $text) * 100) : 'n/a';
            $output->writeln(sprintf('%-28s %12.1f %12.1f %10s', $label, $text, $json, $change));
        }

        // the plan, not just the clock: a filter that got faster because the planner found an
        // index is a different finding from one that got faster because the rows got cheaper
        $probe = ($this->queries()['filter on form_id'])(true);
        $output->writeln('');
        $output->writeln('<comment>Plan for "filter on form_id" against the '
            . ($native ?? 'TEXT') . ' corpus:</comment>');
        foreach ($db->loadAll($this->explainPrefix() . $probe) as $line) {
            $output->writeln('  ' . implode(' | ', array_map('strval', $line)));
        }
    }

    private function explainPrefix(): string
    {
        return match ($this->services->get(DbService::class)->getDriver()) {
            'pgsql' => 'EXPLAIN (ANALYZE, BUFFERS) ',
            'sqlite' => 'EXPLAIN QUERY PLAN ',
            default => 'EXPLAIN ',
        };
    }

    /**
     * Median rather than mean: one run that hit a checkpoint or a page fault should not be
     * what decides a table rebuild.
     */
    private function median(string $query): float
    {
        $db = $this->services->get(DbService::class);
        $timings = [];
        for ($run = 0; $run < 5; $run++) {
            $startedAt = microtime(true);
            $db->loadAll($query);
            $timings[] = (microtime(true) - $startedAt) * 1000;
        }
        sort($timings);

        return $timings[2];
    }

    /**
     * The other half of the question: does the gain survive *without* per-path indexes -- or
     * is the real proposal "index these few paths", which is a smaller and cheaper ticket?
     */
    private function index(OutputInterface $output): void
    {
        $db = $this->services->get(DbService::class);
        $driver = $db->getDriver();
        $text = $this->table(self::TEXT_TABLE);
        $json = $this->table(self::JSON_TABLE);

        $output->writeln('');
        $output->writeln('<comment>Indexing the hot path (form_id) on both corpora...</comment>');
        $startedAt = microtime(true);

        if ($driver === 'mysql') {
            // MySQL cannot index a JSON column, or an expression over a text one: the only
            // route is a stored generated column with a normal index on it -- which is
            // available on a TEXT body too, and that is the whole point of measuring it
            foreach ([$text, $json] as $table) {
                $db->query("ALTER TABLE {$table} ADD COLUMN form_id_gen VARCHAR(20)"
                    . " GENERATED ALWAYS AS (JSON_UNQUOTE(JSON_EXTRACT(body, '$.form_id'))) STORED");
                $db->query("CREATE INDEX idx_form_id ON {$table} (form_id_gen)");
            }
        } elseif ($driver === 'pgsql') {
            // an expression index over the text column, and the same over jsonb
            $db->query("CREATE INDEX idx_text_form_id ON {$text} "
                . "((CASE WHEN body ~ '^\\s*\\{' THEN (body::jsonb #>> ARRAY['form_id']) ELSE NULL END))");
            $db->query("CREATE INDEX idx_json_form_id ON {$json} ((body #>> ARRAY['form_id']))");
            // ...and the one a TEXT column simply cannot have
            $db->query("CREATE INDEX idx_json_gin ON {$json} USING GIN (body jsonb_path_ops)");
        } else {
            $db->query("CREATE INDEX idx_text_form_id ON {$text} "
                . "((CASE WHEN json_valid(body) THEN json_extract(body, '$.form_id') ELSE NULL END))");
            $db->query("CREATE INDEX idx_json_form_id ON {$json} "
                . "((CASE WHEN json_valid(body) THEN json_extract(body, '$.form_id') ELSE NULL END))");
        }

        if ($driver === 'pgsql') {
            $db->query("ANALYZE {$text}");
            $db->query("ANALYZE {$json}");
        }

        $output->writeln(sprintf('<info>Indexed in %.1fs.</info>', microtime(true) - $startedAt));
    }

    /**
     * The migration cost, which is the number that decides this for existing installs:
     * changing the column type is a full table rebuild, and it happens while the wiki is down.
     */
    private function timeTheAlter(OutputInterface $output): void
    {
        $db = $this->services->get(DbService::class);
        $native = $this->nativeJsonType();
        if ($native === null) {
            $output->writeln('<comment>No native JSON type on this dialect; nothing to time.</comment>');

            return;
        }

        $copy = $this->table('bodybench_alter');
        $db->query("DROP TABLE IF EXISTS {$copy}");
        $db->query("CREATE TABLE {$copy} AS SELECT * FROM " . $this->table(self::TEXT_TABLE));
        $rows = (int)$db->scalar("SELECT COUNT(*) FROM {$copy}", 0);

        $output->writeln('');
        $output->writeln("<comment>Converting {$rows} rows from TEXT to {$native} in place...</comment>");
        $startedAt = microtime(true);
        $db->query(match ($db->getDriver()) {
            'mysql' => "ALTER TABLE {$copy} MODIFY body JSON NOT NULL",
            default => "ALTER TABLE {$copy} ALTER COLUMN body TYPE JSONB USING body::jsonb",
        });
        $elapsed = microtime(true) - $startedAt;
        $db->query("DROP TABLE IF EXISTS {$copy}");

        $output->writeln(sprintf(
            '<info>%.1fs for %d rows -- %.1fs per 100k.</info>',
            $elapsed,
            $rows,
            $rows > 0 ? $elapsed / $rows * 100000 : 0
        ));
    }
}
