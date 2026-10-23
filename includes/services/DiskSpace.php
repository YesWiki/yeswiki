<?php

namespace YesWiki\Core\Service;

use Symfony\Component\Process\Process;

/**
 * Bytes this wiki can still write somewhere: the free space of the disk, capped by the owner's quota.
 */
class DiskSpace
{
    public const SYSTEM_PATH = '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin';

    /**
     * Free bytes at $path for this process, null when neither the disk nor a quota will say.
     */
    public function free(string $path): ?int
    {
        $disk = function_exists('disk_free_space') ? @disk_free_space($path) : false;
        $disk = $disk === false ? null : (int)$disk;
        $quota = $this->quotaHeadroom($path);
        if (is_null($quota)) {
            return $disk;
        }

        return is_null($disk) ? $quota : min($disk, $quota);
    }

    /**
     * Bytes left under the user quota of the filesystem holding $path, null when there is none.
     */
    public function quotaHeadroom(string $path): ?int
    {
        $user = $this->user();
        $mount = $this->run(['df', '--output=source,fstype', $path]);
        if (empty($user) || is_null($mount)) {
            return null;
        }
        $lines = preg_split('/\R/', trim($mount));
        $fields = preg_split('/\s+/', trim(end($lines)));
        if (count($fields) !== 2 || $fields[1] !== 'zfs') {
            return null;
        }
        $values = $this->run(['zfs', 'get', '-Hp', '-o', 'value', "userquota@$user,userused@$user", $fields[0]]);
        if (is_null($values)) {
            return null;
        }
        $values = preg_split('/\R/', trim($values));
        if (count($values) !== 2 || !ctype_digit($values[0]) || !ctype_digit($values[1]) || (int)$values[0] === 0) {
            return null;
        }

        return max(0, (int)$values[0] - (int)$values[1]);
    }

    /**
     * Human-readable size, for messages.
     */
    public static function human(int $bytes): string
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string)$bytes : number_format($value, 1)) . ' ' . $units[$unit];
    }

    protected function user(): ?string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $entry = posix_getpwuid(posix_geteuid());
            if (!empty($entry['name'])) {
                return $entry['name'];
            }
        }
        $name = $this->run(['id', '-un']);

        return empty(trim((string)$name)) ? null : trim($name);
    }

    protected function run(array $command): ?string
    {
        try {
            $process = new Process($command, null, ['PATH' => self::SYSTEM_PATH]);
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful() ? $process->getOutput() : null;
        } catch (\Throwable $throwable) {
            return null;
        }
    }
}
