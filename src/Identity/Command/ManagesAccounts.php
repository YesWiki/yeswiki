<?php

namespace YesWiki\Identity\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use YesWiki\Identity\Entity\User;
use YesWiki\Identity\Service\GroupOperationsService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Identity\Service\UserOperationsService;

/** What the `user:*` commands share -- a trait, since the console instantiates every class it finds in a Command/ directory. */
trait ManagesAccounts
{
    private ContainerInterface $services;

    public function __construct(ContainerInterface $services)
    {
        parent::__construct();
        $this->services = $services;
    }

    private function users(): UserManager
    {
        return $this->services->get(UserManager::class);
    }

    private function accounts(): UserOperationsService
    {
        return $this->services->get(UserOperationsService::class);
    }

    private function groups(): GroupOperationsService
    {
        return $this->services->get(GroupOperationsService::class);
    }

    /** The account an argument names, or null after saying so on the error stream. */
    private function account(string $name, OutputInterface $output): ?User
    {
        $user = $this->users()->getOneByName(trim($name));
        if ($user === null) {
            $this->fail($output, "No account called \"{$name}\".");
        }

        return $user;
    }

    /** A password from the option, else from YESWIKI_USER_PASSWORD, else typed in without echo. */
    private function password(InputInterface $input, OutputInterface $output, bool $required): ?string
    {
        $given = $input->getOption('password');
        if (is_string($given) && $given !== '') {
            return $given;
        }
        $fromEnvironment = getenv('YESWIKI_USER_PASSWORD');
        if (is_string($fromEnvironment) && $fromEnvironment !== '') {
            return $fromEnvironment;
        }
        if (!$required && !$input->getOption('ask-password')) {
            return null;
        }
        if (!$input->isInteractive()) {
            $this->fail($output, 'Give the password with --password, or through YESWIKI_USER_PASSWORD to keep it out of the process list.');

            return null;
        }
        $question = new Question('Password: ');
        $question->setHidden(true)->setHiddenFallback(false);
        $typed = (new QuestionHelper())->ask($input, $output, $question);

        return is_string($typed) && $typed !== '' ? $typed : null;
    }

    /**
     * Group names from a repeatable option, trimmed, without a leading "@", empties dropped.
     *
     * @return list<string>
     */
    private function groupNames(InputInterface $input, string $option): array
    {
        $names = [];
        foreach ((array)$input->getOption($option) as $value) {
            foreach (explode(',', (string)$value) as $name) {
                $name = ltrim(trim($name), '@');
                if ($name !== '') {
                    $names[] = $name;
                }
            }
        }

        return array_values(array_unique($names));
    }

    /** Puts an account in a group, making the group when it does not exist yet. */
    private function join(string $group, string $user, OutputInterface $output): void
    {
        if ($this->groups()->groupExists($group)) {
            $this->groups()->add($group, [$user]);
            $output->writeln("<info>{$user}</info> added to group <info>{$group}</info>.");
        } else {
            $this->groups()->create($group, [$user]);
            $output->writeln("Group <info>{$group}</info> created with <info>{$user}</info> as its first member.");
        }
    }

    private function fail(OutputInterface $output, string $message): void
    {
        $stream = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $stream->writeln("<error>{$message}</error>");
    }
}
