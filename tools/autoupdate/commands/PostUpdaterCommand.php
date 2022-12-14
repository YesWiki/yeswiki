<?php
namespace YesWiki\AutoUpdate\Commands;

use Symfony\Component\Console\Command\Command;
// use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
// use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
// use Symfony\Component\Console\Question\ChoiceQuestion;
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Controller\UserController;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class PostUpdaterCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'updater:postupdate';

    protected $wiki;

    public function __construct(Wiki &$wiki)
    {
        parent::__construct();
        $this->wiki = $wiki;
    }

    protected function configure()
    {
        $this
            // the short description shown while running "php bin/console list"
            ->setDescription('Run all migrations after new install or update of a YesWiki package.')

            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command allows you to execute postupgrade (runs the upgrade action).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ob_start();
        // little hack (bad habit..): we use the first admin user to perform updates as an admin
        $firstAdminName = $wiki->services->get(UserController::class)->getFirstAdmin();
        if (!empty($firstAdminName)) {
            $userManager = $wiki->services->get(UserManager::class);
            $authController = $wiki->services->get(AuthController::class);
            $firstAdmin = $userManager->getOneByName($firstAdminName);
            if (!empty($firstAdmin)) {
                $authController->login($firstAdmin);

                $this->wiki->Run($this->wiki->getPageTag(), 'update');
                $bufferedOutput = ob_get_contents();
                ob_end_clean();

                $bufferedOutput = strip_tags($bufferedOutput, '<br><hr><em><strong>');
                $bufferedOutput = preg_replace(
                    ['#<[bh]r ?/?>#Ui', '/<(em|strong)>/Ui', '#</ ?(em|strong)>#Ui'],
                    ["\n", "\e[1m", "\e[0m"],
                    $bufferedOutput
                );
                $output->write($bufferedOutput);

                return Command::SUCCESS;
            }
        }
        return Command::FAILURE;
    }
}
