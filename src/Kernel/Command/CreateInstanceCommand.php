<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provision a new farm Instance folder: an index.php pointing at this YesWiki Program tree, plus the instance data folders.
 */
class CreateInstanceCommand extends Command implements RunsOutsideAnInstance
{
    protected ?ContainerInterface $services;

    public function __construct(?ContainerInterface $services = null)
    {
        parent::__construct();
        $this->services = $services;
    }

    protected function configure()
    {
        $this
            ->setName('core:create-instance')
            ->setDescription('Create a new wiki instance folder sharing this YesWiki program.')
            ->setHelp("Creates <path> (relative to the current directory, or absolute) containing:\n" .
                "- index.php loading YesWiki from this Program tree\n" .
                "- yeswicli, this wiki's own console\n" .
                "- the instance data folders (cache/, custom/, files/, private/)\n\n" .
                "Point a vhost docroot at the folder (with the standard rewrite fallback\n" .
                "to index.php) and visit it: the installer creates yeswiki.config.php and\n" .
                "the database there. No symlink to the sources is needed.\n")
            ->addArgument('path', InputArgument::REQUIRED, 'Folder to create, relative or absolute path')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = rtrim($input->getArgument('path'), '/');
        if ($path === '') {
            $output->writeln('<error>Empty path.</error>');

            return Command::INVALID;
        }

        if (!preg_match('~^(/|[A-Za-z]:[\\\\/])~', $path)) {
            $path = YESWIKI_INSTANCE_DIR . '/' . $path;
        }

        if (file_exists($path . '/index.php')) {
            $output->writeln("<error>$path/index.php already exists, not touching it.</error>");

            return Command::FAILURE;
        }
        if (realpath($path) === realpath(YESWIKI_PROGRAM_DIR)) {
            $output->writeln('<error>Refusing to create an instance inside the YesWiki source folder itself.</error>');

            return Command::FAILURE;
        }

        if (!is_dir($path) && !@mkdir($path, 0755, true)) {
            $output->writeln("<error>Could not create folder $path</error>");

            return Command::FAILURE;
        }

        $path = realpath($path) ?: $path;

        $programDir = var_export(YESWIKI_PROGRAM_DIR, true);
        $indexContent = <<<PHP
            <?php

            define('YESWIKI_PROGRAM_DIR', $programDir);
            putenv('YESWIKI_CONFIG_FILE=' . __DIR__ . '/yeswiki.config.php');
            require YESWIKI_PROGRAM_DIR . '/index.php';

            PHP;

        if (file_put_contents($path . '/index.php', $indexContent) === false) {
            $output->writeln("<error>Could not write $path/index.php</error>");

            return Command::FAILURE;
        }

        foreach (['cache', 'custom', 'files', 'private'] as $dataFolder) {
            if (!is_dir($path . '/' . $dataFolder)) {
                @mkdir($path . '/' . $dataFolder, 0755, true);
            }
        }
        if (!is_file($path . '/private/.htaccess') && is_file(YESWIKI_PROGRAM_DIR . '/private/.htaccess')) {
            @copy(YESWIKI_PROGRAM_DIR . '/private/.htaccess', $path . '/private/.htaccess');
        }

        $this->writeConsoleWrapper($path);

        $output->writeln("<info>Instance created in $path</info>");
        $output->writeln('Next steps:');
        $output->writeln(" - point a vhost docroot at $path, with the rewrite fallback to index.php");
        $output->writeln('   (nginx: `try_files $uri /index.php$is_args$args;` - apache: standard YesWiki rewrite)');
        $output->writeln(' - deny web access to private/ (nginx setups; apache is covered by private/.htaccess)');
        $output->writeln(' - visit the site: the installer will create yeswiki.config.php and the database');
        $output->writeln(" - run this wiki's own commands with <info>$path/yeswicli</info>");

        return Command::SUCCESS;
    }

    /** A `yeswicli` of its own in the new folder. */
    private function writeConsoleWrapper(string $path): void
    {
        $console = YESWIKI_PROGRAM_DIR . '/src/commands/console';
        $wrapper = <<<SH
            #!/usr/bin/env bash
            cd "\$(dirname "\${BASH_SOURCE[0]}")" || exit 1
            export YESWIKI_INSTANCE_DIR="\$PWD"
            export YESWIKI_CONFIG_FILE="\$PWD/yeswiki.config.php"
            php {$console} "\$@"

            SH;

        if (file_put_contents($path . '/yeswicli', $wrapper) !== false) {
            @chmod($path . '/yeswicli', 0755);
        }
    }
}
