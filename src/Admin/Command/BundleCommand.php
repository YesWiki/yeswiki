<?php

namespace YesWiki\Admin\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Admin\Entity\PackageExt;
use YesWiki\Admin\Entity\Repository;
use YesWiki\Admin\Service\AutoUpdateService;
use YesWiki\Admin\Service\PackageInstaller;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Command\RunsOutsideAnInstance;
use YesWiki\Kernel\Service\LanguageService;
use YesWiki\Kernel\Service\ProgramVersion;

/** Installs extensions and themes into a bare program tree from the repository, with no wiki behind it: what an image build runs (replicas 05). */
class BundleCommand extends Command implements RunsOutsideAnInstance
{
    public const ENV_REPOSITORY = 'YESWIKI_REPOSITORY';

    public function __construct(protected ?ContainerInterface $services = null)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('core:bundle')
            ->setDescription('Install extensions and themes into this program tree, with no wiki: what an image build runs.')
            ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The extensions and themes, by name')
            ->addOption('repository', null, InputOption::VALUE_REQUIRED, 'Where packages come from (or ' . self::ENV_REPOSITORY . '), without the release line');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $files = new LocalFiles();
        if ($files->realPath(YESWIKI_INSTANCE_DIR) !== $files->realPath(YESWIKI_PROGRAM_DIR)) {
            $output->writeln('<error>core:bundle installs into the program tree: run it there, not from a wiki</error>');

            return Command::FAILURE;
        }
        self::withoutAWiki();
        $repository = new Repository($this->address($input));
        if (!$repository->load()) {
            $output->writeln('<error>' . _t('AU_REPO_ERROR') . ' ' . $this->address($input) . '</error>');

            return Command::FAILURE;
        }

        $failed = false;
        $installer = new PackageInstaller();
        foreach ((array)$input->getArgument('packages') as $name) {
            $name = (string)$name;
            $package = $repository->getPackage($name);
            if (!$package instanceof PackageExt) {
                $output->writeln("<error>{$name} is no extension or theme of this repository</error>");
                $failed = true;

                continue;
            }
            $output->writeln("Installing {$name} {$package->release}");
            $messages = $installer->install($package, $name);
            foreach ($messages as $message) {
                $output->writeln("{$message['status']} | {$message['text']}");
            }
            $failed = $failed || $messages->hasError();
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /** Loads what the package classes read from a booted wiki, the constants and the translations, as core:install does. */
    private static function withoutAWiki(): void
    {
        require_once YESWIKI_PROGRAM_DIR . '/src/constants.php';
        require_once YESWIKI_PROGRAM_DIR . '/src/Kernel/Service/LanguageService.php';
        if (defined('YW_CHARSET')) {
            return;
        }
        $_SERVER['HTTP_HOST'] ??= 'localhost';
        $_SERVER['REQUEST_URI'] ??= '/';
        $_SERVER['SCRIPT_NAME'] ??= '/index.php';
        LanguageService::getInstance()->initialize();
    }

    /** The repository of this program's release line: the option, the environment, then the default one. */
    private function address(InputInterface $input): string
    {
        $base = (string)($input->getOption('repository') ?: getenv(self::ENV_REPOSITORY) ?: AutoUpdateService::DEFAULT_REPO);
        $line = (new ProgramVersion())->releaseLine() ?: AutoUpdateService::DEFAULT_VERS;

        return rtrim($base, '/') . '/' . strtolower($line);
    }
}
