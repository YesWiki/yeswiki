<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Kernel\Service\InstanceEntryPoints;
use YesWiki\Kernel\Service\ProgramVersion;

/** Turn a wiki installed with its own full copy of YesWiki into a farm Instance of this Program tree. */
class ConvertToInstanceCommand extends Command implements RunsOutsideAnInstance
{
    private const KEPT = ['yeswiki.config.php', '.htaccess', ...InstanceEntryPoints::DATA_FOLDERS];

    private const OWN_FOLDERS = ['extensions' => 'custom/extensions', 'themes' => 'custom/themes'];

    protected ?ContainerInterface $services;

    public function __construct(?ContainerInterface $services = null)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure()
    {
        $this
            ->setName('core:convert-to-instance')
            ->setDescription('Turn a wiki installed with its own copy of YesWiki into an instance sharing this program.')
            ->setHelp("Run it from the shared YesWiki program. In <path>, it:\n" .
                "- keeps yeswiki.config.php, .htaccess, files/, custom/, private/ and whatever this program does not ship\n" .
                "- moves the extensions and themes this program does not ship into custom/extensions/ and custom/themes/\n" .
                "- moves every other file this program also has into a backup folder beside the wiki\n" .
                "- empties cache/ and writes index.php and yeswicli pointing at this program\n\n" .
                "Then run <path>/yeswicli migrate, check the wiki, and delete the backup folder.\n")
            ->addArgument('path', InputArgument::REQUIRED, 'Folder of the installed wiki, relative or absolute path')
            ->addOption('backup', null, InputOption::VALUE_REQUIRED, 'Where to move the replaced files (default: <path>.full-install-<date>)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would happen and change nothing')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $this->absolute(rtrim((string)$input->getArgument('path'), '/'));
        $refusal = $this->refusal($path);
        if ($refusal !== null) {
            $output->writeln("<error>$refusal</error>");

            return Command::FAILURE;
        }
        $path = (string)realpath($path);

        $backup = $input->getOption('backup');
        $backup = is_string($backup) && $backup !== ''
            ? $this->absolute(rtrim($backup, '/'))
            : $path . '.full-install-' . date('Ymd-His');
        if (file_exists($backup)) {
            $output->writeln("<error>$backup already exists, choose another --backup folder.</error>");

            return Command::FAILURE;
        }

        $plan = $this->plan($path, $backup);
        $dryRun = (bool)$input->getOption('dry-run');
        foreach ($plan as [$from, $to]) {
            $output->writeln(($dryRun ? 'would move ' : 'move ') . "$from -> $to");
        }
        foreach ($this->unknownEntries($path) as $entry) {
            $output->writeln("keep $path/$entry (not part of YesWiki)");
        }
        $output->writeln(($dryRun ? 'would empty ' : 'empty ') . "$path/cache/");
        $output->writeln(($dryRun ? 'would write ' : 'write ') . "$path/index.php and $path/yeswicli -> " . YESWIKI_PROGRAM_DIR);
        if ($dryRun) {
            return Command::SUCCESS;
        }

        if (!@mkdir($backup, 0755, true)) {
            $output->writeln("<error>Could not create $backup</error>");

            return Command::FAILURE;
        }
        foreach ($plan as [$from, $to]) {
            if (!is_dir(\dirname($to))) {
                @mkdir(\dirname($to), 0755, true);
            }
            if (!@rename($from, $to)) {
                $output->writeln("<error>Could not move $from to $to; stopped there, the files already moved are in $backup.</error>");

                return Command::FAILURE;
            }
        }
        $this->empty($path . '/cache');

        $error = InstanceEntryPoints::write($path, YESWIKI_PROGRAM_DIR);
        if ($error !== null) {
            $output->writeln("<error>$error; the replaced files are in $backup.</error>");

            return Command::FAILURE;
        }

        $output->writeln("<info>$path is now an instance of " . YESWIKI_PROGRAM_DIR . '</info>');
        $output->writeln('Next steps:');
        $output->writeln(" - run <info>$path/yeswicli migrate</info>");
        $output->writeln(' - nginx: the vhost must send every request to index.php (`try_files $uri /index.php$is_args$args;`)');
        $output->writeln(" - if you ran this as root: <info>$path/yeswicli core:fix-ownership</info>");
        $output->writeln(" - check the wiki in a browser, then delete $backup");

        return Command::SUCCESS;
    }

    private function absolute(string $path): string
    {
        return preg_match('~^(/|[A-Za-z]:[\\\\/])~', $path) ? $path : YESWIKI_INSTANCE_DIR . '/' . $path;
    }

    private function refusal(string $path): ?string
    {
        if ($path === '' || !is_dir($path)) {
            return "$path is not a folder.";
        }
        if (realpath($path) === realpath(YESWIKI_PROGRAM_DIR)) {
            return 'That is this YesWiki program itself; give the path of the wiki to convert.';
        }
        if (!is_file($path . '/yeswiki.config.php')) {
            return "No yeswiki.config.php in $path: not an installed wiki.";
        }
        if (InstanceEntryPoints::isInstance($path)) {
            return "$path is already an instance.";
        }
        if (!is_file($path . '/composer.json') || !is_dir($path . '/src')) {
            return "$path does not hold a copy of YesWiki to replace.";
        }

        $theirs = new ProgramVersion((string)file_get_contents($path . '/composer.json'));
        $ours = new ProgramVersion();
        if ($theirs->releaseLine() !== $ours->releaseLine()) {
            return sprintf(
                '%s runs the %s release line and this program is %s; upgrade the wiki first.',
                $path,
                $theirs->releaseLine() ?: 'doryphore (or older)',
                $ours->releaseLine()
            );
        }
        if ($theirs->version() !== ProgramVersion::DEV && $ours->version() !== ProgramVersion::DEV
            && version_compare($theirs->version(), $ours->version(), '>')) {
            return "$path runs YesWiki {$theirs->version()}, newer than this program ({$ours->version()}); update the program first.";
        }

        return null;
    }

    /** @return list<array{0: string, 1: string}> the moves, from => to */
    private function plan(string $path, string $backup): array
    {
        $moves = [];
        foreach (self::OWN_FOLDERS as $folder => $customFolder) {
            foreach ($this->entries($path . '/' . $folder) as $name) {
                if (!is_dir($path . '/' . $folder . '/' . $name) || file_exists(YESWIKI_PROGRAM_DIR . '/' . $folder . '/' . $name)) {
                    continue;
                }
                $target = $path . '/' . $customFolder . '/' . $name;
                if (!file_exists($target)) {
                    $moves[] = [$path . '/' . $folder . '/' . $name, $target];
                }
            }
        }
        foreach ($this->entries($path) as $entry) {
            if (!in_array($entry, self::KEPT, true) && file_exists(YESWIKI_PROGRAM_DIR . '/' . $entry)) {
                $moves[] = [$path . '/' . $entry, $backup . '/' . $entry];
            }
        }

        return $moves;
    }

    /** @return list<string> */
    private function unknownEntries(string $path): array
    {
        return array_values(array_filter(
            $this->entries($path),
            fn (string $entry): bool => !in_array($entry, self::KEPT, true) && !file_exists(YESWIKI_PROGRAM_DIR . '/' . $entry)
        ));
    }

    /** @return list<string> */
    private function entries(string $folder): array
    {
        $entries = is_dir($folder) ? scandir($folder) : false;

        return $entries === false ? [] : array_values(array_diff($entries, ['.', '..']));
    }

    private function empty(string $folder): void
    {
        foreach ($this->entries($folder) as $entry) {
            $item = $folder . '/' . $entry;
            if (is_dir($item) && !is_link($item)) {
                $this->empty($item);
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
    }
}
