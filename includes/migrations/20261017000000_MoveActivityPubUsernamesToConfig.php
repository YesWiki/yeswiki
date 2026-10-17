<?php

use YesWiki\Bazar\Service\ActivityPubProfiles;
use YesWiki\Core\Service\ConfigurationFileProvider;
use YesWiki\Core\Service\ConfigurationService;
use YesWiki\Core\YesWikiMigration;

/**
 * Moves each form's ActivityPub username from the `nature` table to the `activitypub_profiles` configuration.
 */
class MoveActivityPubUsernamesToConfig extends YesWikiMigration
{
    public function run()
    {
        if (!$this->dbService->columnExists('nature', 'bn_activitypub_username')) {
            return;
        }

        $rows = $this->dbService->loadAll(
            'SELECT `bn_id_nature`, `bn_activitypub_username` FROM ' . $this->dbService->prefixTable('nature')
            . " WHERE `bn_activitypub_username` IS NOT NULL AND `bn_activitypub_username` <> ''"
        );

        if (!empty($rows)) {
            $configurationService = $this->getService(ConfigurationService::class);
            $config = $configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
            $config->load();
            $key = ActivityPubProfiles::CONFIG_KEY;
            $profiles = isset($config[$key]) && is_array($config[$key]) ? $config[$key] : [];
            $taken = array_filter(array_map(fn ($profile) => is_array($profile) ? ($profile['username'] ?? '') : '', $profiles));

            foreach ($rows as $row) {
                $formId = (string)$row['bn_id_nature'];
                if (!empty($profiles[$formId]['username'])) {
                    continue;
                }
                $username = $this->availableUsername($row['bn_activitypub_username'], $taken);
                $profiles[$formId] = ['username' => $username] + (is_array($profiles[$formId] ?? null) ? $profiles[$formId] : []);
                $taken[] = $username;
            }

            $config[$key] = $profiles;
            if (!$configurationService->write($config)) {
                throw new Exception('Cannot write the ActivityPub usernames to ' . ConfigurationFileProvider::getConfigFileFromEnv());
            }
        }

        $this->dbService->dropColumn('nature', 'bn_activitypub_username');
    }

    private function availableUsername(string $username, array $taken): string
    {
        $base = substr(preg_replace('/[^a-z0-9_]+/', '_', strtolower(trim($username))), 0, 26) ?: 'form';
        $candidate = $base;
        for ($suffix = 2; in_array($candidate, $taken, true); $suffix++) {
            $candidate = $base . '_' . $suffix;
        }

        return $candidate;
    }
}
