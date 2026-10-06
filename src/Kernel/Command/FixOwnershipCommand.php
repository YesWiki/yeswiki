<?php

namespace YesWiki\Kernel\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Kernel\Service\CliOwnership;
use YesWiki\Kernel\Service\ConfigurationFileProvider;

/** Gives back to the wiki's owner what a command run as root left behind in cache/, custom/, files/ and private/. */
class FixOwnershipCommand extends Command implements RunsOutsideAnInstance
{
    public function __construct(protected ?ContainerInterface $services = null)
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName(CliOwnership::FIX_COMMAND)
            ->setDescription("Give back to the wiki's owner the files someone else created in it (run as root).")
            ->setHelp("The owner is whoever owns yeswiki.config.php, which the web server wrote at install time.\n"
                . 'Every file and folder in ' . implode('/, ', CliOwnership::DATA) . "/ that belongs to anyone else goes back to that user and group.\n"
                . 'Symbolic links are left alone.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ownership = new CliOwnership(new LocalFiles(), YESWIKI_INSTANCE_DIR, ConfigurationFileProvider::getConfigFileFromEnv());
        if ($ownership->owner() === null) {
            $output->writeln('<error>No yeswiki.config.php here, so no owner to give anything back to.</error>');

            return Command::FAILURE;
        }
        $misowned = $ownership->misowned();
        if ($misowned === []) {
            $output->writeln('Everything in the wiki already belongs to its owner.');

            return Command::SUCCESS;
        }
        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            $output->writeln('<error>Only root can give files back to another user. ' . count($misowned) . ' path(s) to fix, starting with ' . $misowned[0] . '.</error>');

            return Command::FAILURE;
        }
        [$given, $refused] = $ownership->fix();
        $output->writeln("{$given} path(s) given back to the wiki's owner.");
        foreach ($refused as $path) {
            $output->writeln("<error>Could not change the owner of {$path}.</error>");
        }

        return $refused === [] ? Command::SUCCESS : Command::FAILURE;
    }
}
