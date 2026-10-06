<?php

/* Who the console runs as, against who owns the wiki: a command run by anyone else leaves files the web server cannot write. */

namespace YesWiki\Kernel\Service;

use YesWiki\Files\Service\LocalFiles;

final class CliOwnership
{
    public const ANY_USER = 'YESWIKI_CLI_ANY_USER';

    public const FIX_COMMAND = 'core:fix-ownership';

    /** What the wiki writes to, beside its configuration file. */
    public const DATA = ['cache', 'custom', 'files', 'private'];

    public function __construct(
        private readonly LocalFiles $localFiles,
        private readonly string $instanceDir,
        private readonly string $configFile,
    ) {
    }

    /** The user owning the configuration file, which the web server wrote at install time. */
    public function owner(): ?int
    {
        return $this->localFiles->ownerOf($this->configFile);
    }

    public function group(): ?int
    {
        return $this->localFiles->groupOf($this->configFile);
    }

    /** @return list<string> the paths, relative to the wiki, that its owner does not own, at most $limit of them unless $limit is 0 */
    public function misowned(int $limit = 0): array
    {
        $owner = $this->owner();
        if ($owner === null) {
            return [];
        }
        $found = [];
        $walk = function (string $relative) use (&$walk, &$found, $owner, $limit): void {
            if ($limit > 0 && count($found) >= $limit) {
                return;
            }
            $path = $this->instanceDir . '/' . $relative;
            if ($this->localFiles->isLink($path) || !$this->localFiles->exists($path)) {
                return;
            }
            if ($this->localFiles->ownerOf($path) !== $owner) {
                $found[] = $relative;
            }
            if ($this->localFiles->isDirectory($path)) {
                foreach ($this->localFiles->entriesIn($path) as $name) {
                    $walk($relative . '/' . $name);
                }
            }
        };
        foreach (self::DATA as $folder) {
            $walk($folder);
        }

        return $found;
    }

    /**
     * Why the console must not run as $user, with what to type instead; null when it may.
     *
     * @param list<string>          $argv  the console's own arguments, to repeat them in the command to type
     * @param callable(int): string $names a user id's name
     */
    public function refusal(int $user, array $argv, callable $names): ?string
    {
        $owner = $this->owner();
        if ($owner === null || $owner === $user) {
            return null;
        }
        $ownerName = $names($owner);
        $command = 'sudo -u ' . escapeshellarg($ownerName) . ' ' . $this->console() . $this->arguments($argv);
        if ($user !== 0) {
            return "This wiki belongs to {$ownerName}, but yeswicli runs as {$names($user)}: what it writes would not be {$ownerName}'s to change.\n"
                . "Run it as {$ownerName}:\n  {$command}\n";
        }
        if (($argv[1] ?? '') === self::FIX_COMMAND) {
            return null;
        }
        $message = "This wiki belongs to {$ownerName}, but yeswicli runs as root: every file it created would belong to root,\n"
            . "and the web server, running as {$ownerName}, could no longer write it.\n"
            . "Run it as {$ownerName}:\n  {$command}\n";
        $misowned = $this->misowned(6);
        if ($misowned !== []) {
            $message .= "\nSome of the wiki already belongs to someone else: " . implode(', ', array_slice($misowned, 0, 5)) . (count($misowned) > 5 ? ', ...' : '') . ".\n"
                . "Give it back to {$ownerName}, as root:\n  " . $this->console() . ' ' . self::FIX_COMMAND . "\n";
        }

        return $message;
    }

    /** @param callable(int): string $names a user id's name */
    public function warning(callable $names): ?string
    {
        $owner = $this->owner();
        $misowned = $this->misowned(6);
        if ($owner === null || $misowned === []) {
            return null;
        }

        return "Some of this wiki does not belong to {$names($owner)}, so the web server may fail to write it: "
            . implode(', ', array_slice($misowned, 0, 5)) . (count($misowned) > 5 ? ', ...' : '') . ".\n"
            . 'Give it back, as root: ' . $this->console() . ' ' . self::FIX_COMMAND . "\n";
    }

    /** @return array{0: int, 1: list<string>} how many paths went back to the owner, and those that would not */
    public function fix(): array
    {
        $owner = $this->owner();
        $group = $this->group();
        if ($owner === null || $group === null) {
            return [0, []];
        }
        $given = 0;
        $refused = [];
        foreach ($this->misowned() as $relative) {
            if ($this->localFiles->changeOwner($this->instanceDir . '/' . $relative, $owner, $group)) {
                $given++;
            } else {
                $refused[] = $relative;
            }
        }

        return [$given, $refused];
    }

    private function console(): string
    {
        $own = $this->instanceDir . '/yeswicli';

        return $this->localFiles->isFile($own) ? escapeshellarg($own) : './yeswicli';
    }

    /** @param list<string> $argv */
    private function arguments(array $argv): string
    {
        $arguments = array_slice($argv, 1);

        return $arguments === [] ? '' : ' ' . implode(' ', array_map('escapeshellarg', $arguments));
    }
}
