<?php

namespace YesWiki\Files\Service;

use Symfony\Component\Process\Process;

/** How much of a disk's free space this wiki may still use, under the owner's ZFS user quota. */
class DiskSpace
{
    public const SYSTEM_PATH = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';

    /** The disk's free bytes at $path capped by the quota there, null when neither will say. */
    public function capped(?float $disk, string $path): ?float
    {
        $quota = $this->quotaHeadroom($path);
        if ($quota === null) {
            return $disk;
        }

        return $disk === null ? (float)$quota : min($disk, (float)$quota);
    }

    /** Bytes left under the user quota of the ZFS filesystem holding $path, null when there is none. */
    public function quotaHeadroom(string $path): ?int
    {
        $user = $this->user();
        $mount = $this->run(['df', '--output=source,fstype', $path]);
        if (empty($user) || $mount === null) {
            return null;
        }
        $lines = preg_split('/\R/', trim($mount)) ?: [];
        $fields = preg_split('/\s+/', trim((string)end($lines))) ?: [];
        if (count($fields) !== 2 || $fields[1] !== 'zfs') {
            return null;
        }
        $values = $this->run(['zfs', 'get', '-Hp', '-o', 'value', "userquota@$user,userused@$user", $fields[0]]);
        if ($values === null) {
            return null;
        }
        $values = preg_split('/\R/', trim($values)) ?: [];
        if (count($values) !== 2 || !ctype_digit($values[0]) || !ctype_digit($values[1]) || (int)$values[0] === 0) {
            return null;
        }

        return max(0, (int)$values[0] - (int)$values[1]);
    }

    protected function user(): ?string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $entry = posix_getpwuid(posix_geteuid());
            if (is_array($entry) && !empty($entry['name'])) {
                return $entry['name'];
            }
        }
        $name = trim((string)$this->run(['id', '-un']));

        return $name === '' ? null : $name;
    }

    /** @param list<string> $command */
    protected function run(array $command): ?string
    {
        try {
            $process = new Process($command, null, ['PATH' => self::SYSTEM_PATH]);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
