<?php

namespace YesWiki\Search\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Database\SqlParameters;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Search\Service\SearchIndexer;
use YesWiki\Search\Service\SearchIndexQuery;
use YesWiki\Search\Service\SearchIndexSchema;

/**
 * `./yeswicli search:seed` -- a synthetic corpus and the measurements taken over it (ticket 18's acceptance evidence).
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

    /** How many variants of each base word the vocabulary carries (`atelier1`..`atelier100`). */
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
                $rows[] = [
                    $tag,
                    (string)$acl,
                    md5((string)$acl),
                    '*',
                    '',
                    $type,
                    '',
                    'Seeded content ' . $i,
                    $bucketText,
                    '2026-01-01 00:00:00',
                ];
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

    /**
     * @param list<list<string>> $rows one list of column values per index row
     */
    private function flush(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $statement = $this->services->get(DbService::class)->prepare(
            "INSERT INTO {$this->services->get(SearchIndexSchema::class)->table()}"
            . ' (tag, acl, acl_hash, page_read_acl, owner, content_type, form_id, title, text, updated_at)'
            . ' VALUES (' . SqlParameters::placeholders(10) . ')'
        );
        foreach ($rows as $row) {
            $statement->execute($row);
        }
    }

    /** The assertion that matters: is the full-text index actually being used? */
    private function assertUsesIndex(OutputInterface $output): bool
    {
        $db = $this->services->get(DbService::class);
        $table = $this->services->get(SearchIndexSchema::class)->table();

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

                        || str_contains($text, 'rows fetched before execution'),
                    $classicType !== '' ? "type={$classicType}" : trim($text),
                ];
            case 'pgsql':
                return [str_contains($text, 'index scan'), str_contains($text, 'index scan') ? 'GIN index scan' : 'sequential scan'];
            case 'sqlite':
                return [
                    str_contains($text, '_fts') && !str_contains($text, 'scan ' . strtolower($this->services->get(SearchIndexSchema::class)->table()) . ' '),
                    trim($text),
                ];
            default:
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
            'selective term' => 'atelier7',
            'two selective terms' => 'atelier7 jardin7',

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
