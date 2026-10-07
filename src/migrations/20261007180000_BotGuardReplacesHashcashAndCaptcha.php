<?php

use YesWiki\Core\YesWikiMigration;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Entity\ConfigurationFile;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;

/** Removes the hashcash and captcha settings and secret. */
class BotGuardReplacesHashcashAndCaptcha extends YesWikiMigration
{
    public const RETIRED_KEYS = ['use_hashcash', 'use_captcha', 'captcha_words'];

    public function run()
    {
        try {
            $storage = $this->getService(Storage::class);
            if ($storage->exists('cache/hashcash.key')) {
                $storage->delete('cache/hashcash.key');
            }
        } catch (Throwable) {
        }

        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class)->getConfiguration($file);
        $configuration->load();
        $retired = self::retire($configuration);
        if ($retired === []) {
            return;
        }
        if (!$configuration->write()) {
            $this->say(implode(', ', $retired) . " no longer do anything and could not be removed from {$file}: delete them by hand.");

            return;
        }
        $this->say(implode(', ', $retired) . ' removed from the configuration: BotGuard protects every form now, and `altcha` turns its proof of work off.');
    }

    /**
     * Removes the retired settings and names those it removed.
     *
     * @return list<string>
     */
    public static function retire(ConfigurationFile $configuration): array
    {
        $present = array_values(array_filter(self::RETIRED_KEYS, fn (string $key): bool => isset($configuration[$key])));
        foreach ($present as $key) {
            unset($configuration[$key]);
        }

        return $present;
    }
}
