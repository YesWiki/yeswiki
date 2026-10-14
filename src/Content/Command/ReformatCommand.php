<?php

namespace YesWiki\Content\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Entity\PageType;
use YesWiki\Content\Service\MarkdownReformatter;
use YesWiki\Content\Service\PageManager;
use YesWiki\Kernel\Service\DbService;

/** `./yeswicli content:reformat`: puts pages already in Markdown into the shape the editor reads, as a new revision. */
class ReformatCommand extends Command
{
    private const STYLESHEET = 'PageCss';

    private ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure(): void
    {
        $this
            ->setName('content:reformat')
            ->setDescription('Reformat the Markdown of every page and comment so the editor reads it, saving a new revision where it changes')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Only this page')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the pages that would change; write nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pages = $this->services->get(PageManager::class);
        $reformatter = new MarkdownReformatter();
        $dryRun = (bool)$input->getOption('dry-run');

        $changed = 0;
        foreach ($this->tags((string)$input->getOption('page')) as $tag) {
            $page = $pages->getOne($tag, null, false, true);
            if ($page === null) {
                continue;
            }
            $content = PageBody::content($page['body']);
            $reformatted = $reformatter->reformat($content);
            if ($reformatted === $content) {
                continue;
            }
            $changed++;
            $output->writeln("  {$tag}");
            if (!$dryRun) {
                $pages->save($tag, [PageBody::CONTENT => $reformatted] + $page['body'], '', true);
            }
        }

        $output->writeln($dryRun
            ? "<info>{$changed} page(s) would be reformatted. Nothing was written.</info>"
            : "<info>{$changed} page(s) reformatted, each as a new revision.</info>");

        return Command::SUCCESS;
    }

    /** @return list<string> the pages and comments to look at, or just the one asked for */
    private function tags(string $only): array
    {
        $db = $this->services->get(DbService::class);
        $sql = 'SELECT tag FROM ' . $db->prefixTable('pages') . " WHERE latest = 'Y' AND (type IS NULL OR type IN ('', ?, ?)) AND tag <> ?";
        $params = [PageType::PAGE, PageType::COMMENT, self::STYLESHEET];
        if ($only !== '') {
            $sql .= ' AND tag = ?';
            $params[] = $only;
        }

        return array_values(array_map(fn (array $row): string => (string)$row['tag'], $db->loadAll($sql . ' ORDER BY tag', $params)));
    }
}
