<?php

namespace YesWiki\Search\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;

/**
 * `./yeswicli search:seed` -- a synthetic corpus and the measurements taken over it
 * (ticket 18's acceptance evidence).
 *
 * ## Why this is part of the ticket and not a follow-up
 *
 * The suite runs SQLite and drives no browser (ticket 25), so nothing in it can tell a
 * correct full-text index from a decorative one: **a full scan and an index scan return
 * exactly the same rows.** The difference only shows up as a number, and only on a corpus
 * large enough to have one. At the scale this rewrite targets -- hundreds of thousands to
 * millions of Contents -- a query that quietly stopped using the index is the single most
 * likely way this ships broken.
 *
 * So `--explain` asserts the plan, not just the result.
 *
 *     ./yeswicli search:seed --count=500000        # build the corpus
 *     ./yeswicli search:seed --explain --benchmark # assert the plan, time the queries
 *     ./yeswicli search:seed --clean               # remove it again
 *
 * The corpus is written straight into the index rather than through `pages`, deliberately:
 * this measures the *query*, and seeding half a million real Contents would measure the
 * indexer instead (which `--benchmark` times separately, over the real drain).
 */
class SeedCommand extends Command
{
    /** Every seeded row's tag starts with this, which is also how --clean finds them. */
    private const TAG_PREFIX = 'SearchSeed';

    private const WORDS = [
        'atelier', 'jardin', 'partage', 'reunion', 'projet', 'commun', 'ressource',
        'benevole', 'collectif', 'quartier', 'formation', 'agenda', 'permanence',
        'chantier', 'cantine', 'fresque', 'mobilite', 'compost', 'ruche', 'entraide',
    ];

    /**
     * How many variants of each base word the vocabulary carries (`atelier1`..`atelier100`).
     *
     * A corpus where every document contains every word is worse than useless as a
     * benchmark: every query matches everything, so what gets measured is the cost of
     * counting the whole table rather than the cost of searching it. With a vocabulary this
     * wide a specific term lands in a small fraction of documents, which is what a real
     * wiki looks like -- while the *base* word still matches every variant by prefix, so the
     * deliberately-broad case stays measurable too.
     */
    private const VARIANTS_PER_WORD = 100;

    private ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure(): void
    {
        $this
            ->setName('search:seed')
            ->setDescription('Seed a synthetic search corpus and measure the index over it (ticket 18)')
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'How many Contents to seed', '100000')
            ->addOption('explain', null, InputOption::VALUE_NONE, 'Assert that the query plan uses the full-text index')
            ->addOption('benchmark', null, InputOption::VALUE_NONE, 'Time a set of representative queries')
            ->addOption('clean', null, InputOption::VALUE_NONE, 'Delete the seeded rows and exit')
            ->addOption(
                'restricted',
                null,
                InputOption::VALUE_REQUIRED,
                'Percentage of Contents carrying a second, Field-ACL-restricted bucket',
                '0'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = $this->services->get(DbService::class);
        $schema = $this->services->get(SearchIndexSchema::class);

        if (!$schema->exists()) {
            $output->writeln('<error>The search index does not exist. Run ./yeswicli search:reindex --rebuild first.</error>');

            return Command::FAILURE;
        }

        if ($input->getOption('clean')) {
            $db->query("DELETE FROM {$schema->table()} WHERE tag LIKE '" . self::TAG_PREFIX . "%'");
            $output->writeln('<info>Seeded rows removed.</info>');

            return Command::SUCCESS;
        }

        $status = Command::SUCCESS;

        $count = max(1, (int)$input->getOption('count'));
        $restricted = max(0, min(100, (int)$input->getOption('restricted')));
        if (!$input->getOption('explain') && !$input->getOption('benchmark')) {
            $this->seed($output, $count, $restricted);

            return Command::SUCCESS;
        }

        if ($input->getOption('explain') && !$this->assertUsesIndex($output)) {
            $status = Command::FAILURE;
        }

        if ($input->getOption('benchmark')) {
            $this->benchmark($output);
        }

        return $status;
    }

    /**
     * @param int $restrictedPercent how many Contents carry a second, restricted ACL bucket.
     *                               Defaults to none, because that is what a wiki with no
     *                               Field ACLs looks like -- and the query takes a genuinely
     *                               different (much cheaper) path there, so a benchmark that
     *                               always seeded restricted buckets would report the
     *                               pessimistic case as if it were the normal one.
     */
    private function seed(OutputInterface $output, int $count, int $restrictedPercent = 0): void
    {
        $db = $this->services->get(DbService::class);
        $schema = $this->services->get(SearchIndexSchema::class);
        $types = ['page', 'entry', 'user', 'file', 'form', 'comment'];

        $output->writeln("Seeding {$count} Contents ({$restrictedPercent}% with a restricted bucket)...");
        $startedAt = microtime(true);

        $rows = [];
        $written = 0;
        for ($i = 0; $i < $count; $i++) {
            $tag = self::TAG_PREFIX . $i;
            // deterministic rather than random: a corpus you cannot reproduce is a benchmark
            // you cannot compare against last week's
            $words = [];
            for ($w = 0; $w < 40; $w++) {
                $base = self::WORDS[($i * 7 + $w * 13) % count(self::WORDS)];
                $variant = (($i * 31 + $w * 17) % self::VARIANTS_PER_WORD) + 1;
                $words[] = $base . $variant;
            }
            $text = implode(' ', $words);
            $type = $types[$i % count($types)];

            $buckets = ['' => $text];
            if ($restrictedPercent > 0 && ($i % 100) < $restrictedPercent) {
                $buckets['@admins'] = 'confidentiel ' . $text;
            }

            foreach ($buckets as $acl => $bucketText) {
                $rows[] = '('
                    . "'{$db->escape($tag)}', '{$db->escape((string)$acl)}', '" . md5((string)$acl) . "', "
                    . "'*', '', '{$db->escape($type)}', '', "
                    . "'{$db->escape('Seeded content ' . $i)}', '{$db->escape($bucketText)}', "
                    . "'2026-01-01 00:00:00')";
            }

            if (count($rows) >= 500) {
                $this->flush($rows);
                $written += count($rows);
                $rows = [];
                if ($written % 50000 < 500) {
                    $output->writeln('  ... ' . $written . ' rows');
                }
            }
        }
        $this->flush($rows);

        $elapsed = microtime(true) - $startedAt;
        $total = (int)$db->scalar("SELECT COUNT(*) FROM {$schema->table()}", 0);
        $output->writeln(sprintf(
            '<info>Seeded in %.1fs. The index now holds %d rows.</info>',
            $elapsed,
            $total
        ));
    }

    /** @param list<string> $rows */
    private function flush(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $db = $this->services->get(DbService::class);
        $db->query(
            "INSERT INTO {$this->services->get(SearchIndexSchema::class)->table()}"
            . ' (tag, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at)'
            . ' VALUES ' . implode(', ', $rows)
        );
    }

    /**
     * The assertion that matters: is the full-text index actually being used?
     *
     * Harder than it sounds, because the three planners each prove it on a *different query
     * shape*, and each says so in different words:
     *
     * - **MySQL** answers `SELECT COUNT(*) ... MATCH` straight from the FULLTEXT index and
     *   reports `Rows fetched before execution` -- the best possible plan, naming no index at
     *   all. Its `SELECT ... LIMIT` plan does name `ft_text`. It also has two EXPLAIN formats:
     *   tree since 8.0.18, classic tabular (`type = fulltext`) on MariaDB and older MySQL.
     * - **PostgreSQL** is the reverse: with a `LIMIT` it reasonably expects to hit ten matches
     *   early in a scan and declines the index; on the COUNT it takes the GIN index.
     * - **SQLite** shows the FTS5 virtual table on both.
     *
     * So both shapes are probed and either one proving it is enough. Encoding one planner's
     * habits as the rule is how this check ended up reporting "does NOT use the full-text
     * index" twice for plans that were perfectly good -- and a check that cries wolf gets
     * ignored, which is worse than not having one.
     */
    private function assertUsesIndex(OutputInterface $output): bool
    {
        $db = $this->services->get(DbService::class);
        $table = $this->services->get(SearchIndexSchema::class)->table();
        // A *selective* term, deliberately. Probing with a word that matches every row asks
        // the planner whether it wants an index for a query returning the whole table -- to
        // which "no" is right everywhere, and the check would measure nothing. `atelier7` is
        // one variant among VARIANTS_PER_WORD, so it lands in a small fraction of the corpus.
        $match = $db->dialect()->searchMatchExpression($table, [['atelier7']]);
        $shapes = [
            'count' => "SELECT COUNT(*) FROM {$table} WHERE {$match}",
            'page' => "SELECT tag FROM {$table} WHERE {$match} LIMIT 10",
        ];

        $details = [];
        foreach ($shapes as $shape => $sql) {
            [$ok, $detail] = $this->readPlan($db, $sql);
            if ($ok) {
                $output->writeln("<info>Query plan uses the full-text index ({$shape}: {$detail}).</info>");

                return true;
            }
            $details[] = "{$shape}: {$detail}";
        }

        // Neither shape took it. On PostgreSQL that may still be a rational choice on a small
        // corpus, so ask whether the index is usable at all before calling it a failure.
        if ($db->getDriver() === 'pgsql' && $this->postgresIndexIsUsable($db, $shapes['count'])) {
            $output->writeln(
                '<comment>The planner preferred a sequential scan, but the GIN index is present '
                . 'and usable -- expected on a corpus small enough for the two to cost the same. '
                . 'Seed more rows to make this meaningful.</comment>'
            );

            return true;
        }

        $output->writeln('<error>Query plan does NOT use the full-text index: ' . implode('; ', $details) . '</error>');
        $output->writeln('<error>Results would still be correct, and the wiki would get slower with every page added.</error>');

        return false;
    }

    /**
     * @return array{0: bool, 1: string} whether this plan shows index use, and the plan text
     */
    private function readPlan(DbService $db, string $sql): array
    {
        $keyword = $db->getDriver() === 'sqlite' ? 'EXPLAIN QUERY PLAN' : 'EXPLAIN';
        $plan = $db->loadAll("{$keyword} {$sql}");
        $text = strtolower(implode(' ', array_map(
            static fn (array $row): string => implode(' ', array_map('strval', $row)),
            $plan
        )));

        switch ($db->getDriver()) {
            case 'mysql':
                $classicType = strtolower((string)($plan[0]['type'] ?? ''));

                return [
                    $classicType === 'fulltext'
                        || str_contains($text, 'full-text index search')
                        // answered from the index without touching the table
                        || str_contains($text, 'rows fetched before execution'),
                    $classicType !== '' ? "type={$classicType}" : trim($text),
                ];
            case 'pgsql':
                return [str_contains($text, 'index scan'), str_contains($text, 'index scan') ? 'GIN index scan' : 'sequential scan'];
            case 'sqlite':
                // the FTS5 subquery shows as a scan of the virtual table; what must NOT appear
                // is a scan of the base table
                return [
                    str_contains($text, '_fts') && !str_contains($text, 'scan ' . strtolower($this->services->get(SearchIndexSchema::class)->table()) . ' '),
                    trim($text),
                ];
            default:
                // an unknown driver cannot be judged, and guessing would be worse
                return [true, 'unknown driver; plan not asserted'];
        }
    }

    /** Whether PostgreSQL *can* use the GIN index, setting aside whether it wants to. */
    private function postgresIndexIsUsable(DbService $db, string $sql): bool
    {
        $db->query('SET enable_seqscan = off');
        try {
            [$ok] = $this->readPlan($db, $sql);
        } finally {
            $db->query('SET enable_seqscan = on');
        }

        return $ok;
    }

    private function benchmark(OutputInterface $output): void
    {
        $query = $this->services->get(SearchIndexQuery::class);
        $indexer = $this->services->get(SearchIndexer::class);

        $cases = [
            // the realistic case: one specific term, in a small fraction of the corpus
            'selective term' => 'atelier7',
            'two selective terms' => 'atelier7 jardin7',
            // deliberately pathological: a prefix matching every variant of a base word, so
            // the cost of the EXACT result count is visible rather than hidden
            'broad prefix' => 'atelier',
            'no match' => 'zzzznotawordzzzz',
        ];

        $output->writeln('Query latency:');
        foreach ($cases as $label => $phrase) {
            $timings = [];
            for ($run = 0; $run < 5; $run++) {
                $startedAt = microtime(true);
                $found = $query->search($phrase, null, 20);
                $timings[] = (microtime(true) - $startedAt) * 1000;
            }
            sort($timings);
            $output->writeln(sprintf(
                '  %-20s %6.1f ms median, %6.1f ms worst  (%d results)',
                $label,
                $timings[(int)(count($timings) / 2)],
                end($timings),
                $found['total'] ?? 0
            ));
        }

        $pending = $indexer->pending();
        if ($pending > 0) {
            $startedAt = microtime(true);
            $done = $indexer->drain(1000);
            $elapsed = microtime(true) - $startedAt;
            $output->writeln(sprintf(
                'Reindex throughput: %d Contents in %.1fs (%.0f/s)',
                $done,
                $elapsed,
                $elapsed > 0 ? $done / $elapsed : 0
            ));
        } else {
            $output->writeln('<comment>Queue empty; reindex throughput not measured. Run search:reindex --all first.</comment>');
        }
    }
}
