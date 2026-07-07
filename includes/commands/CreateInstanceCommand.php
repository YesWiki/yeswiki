<?php

namespace YesWiki\Core\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Wiki;

/**
 * Provision a new farm instance folder: an index.php pointing at this YesWiki source tree,
 * plus the instance data folders. Everything else (config, database) is created by the web
 * installer on first visit - see includes/bootstrap_paths.php for the data folder layout.
 */
class CreateInstanceCommand extends Command
{
    protected $wiki;

    public function __construct(Wiki &$wiki)
    {
        parent::__construct();
        $this->wiki = $wiki;
    }

    protected function configure()
    {
        $this
            ->setName('core:create-instance')
            ->setDescription('Create a new wiki instance folder sharing this YesWiki as source.')
            ->setHelp("Creates <path> (relative to the current directory, or absolute) containing:\n" .
                "- index.php loading YesWiki from this source tree\n" .
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
        // resolve relative paths against the current (master) instance dir
        if (!preg_match('~^(/|[A-Za-z]:[\\\\/])~', $path)) {
            $path = YESWIKI_INSTANCE_DIR . '/' . $path;
        }

        if (file_exists($path . '/index.php')) {
            $output->writeln("<error>$path/index.php already exists, not touching it.</error>");

            return Command::FAILURE;
        }
        if (realpath($path) === realpath(YESWIKI_SOURCE_DIR)) {
            $output->writeln('<error>Refusing to create an instance inside the YesWiki source folder itself.</error>');

            return Command::FAILURE;
        }

        if (!is_dir($path) && !@mkdir($path, 0755, true)) {
            $output->writeln("<error>Could not create folder $path</error>");

            return Command::FAILURE;
        }

        $sourceDir = var_export(YESWIKI_SOURCE_DIR, true);
        $indexContent = <<<PHP
            <?php

            define('YESWIKI_SOURCE_DIR', $sourceDir);
            putenv('YESWIKI_CONFIG_FILE=' . __DIR__ . '/yeswiki.config.php');
            require YESWIKI_SOURCE_DIR . '/index.php';

            PHP;

        if (file_put_contents($path . '/index.php', $indexContent) === false) {
            $output->writeln("<error>Could not write $path/index.php</error>");

            return Command::FAILURE;
        }

        // pre-create the data folders (bootstrap_paths.php would do it on first request, but
        // doing it now surfaces permission problems immediately and makes the layout visible)
        foreach (['cache', 'custom', 'files', 'private'] as $dataFolder) {
            if (!is_dir($path . '/' . $dataFolder)) {
                @mkdir($path . '/' . $dataFolder, 0755, true);
            }
        }
        if (!is_file($path . '/private/.htaccess') && is_file(YESWIKI_SOURCE_DIR . '/private/.htaccess')) {
            @copy(YESWIKI_SOURCE_DIR . '/private/.htaccess', $path . '/private/.htaccess');
        }

        $output->writeln("<info>Instance created in $path</info>");
        $output->writeln('Next steps:');
        $output->writeln(" - point a vhost docroot at $path, with the rewrite fallback to index.php");
        $output->writeln('   (nginx: `try_files $uri /index.php$is_args$args;` - apache: standard YesWiki rewrite)');
        $output->writeln(' - deny web access to private/ (nginx setups; apache is covered by private/.htaccess)');
        $output->writeln(' - visit the site: the installer will create yeswiki.config.php and the database');

        return Command::SUCCESS;
    }
}
