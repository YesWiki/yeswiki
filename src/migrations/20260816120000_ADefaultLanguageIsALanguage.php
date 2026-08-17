<?php

use YesWiki\Admin\Service\AdministrativeLogService;
use YesWiki\Core\YesWikiMigration;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\LanguageService;

/**
 * `default_language: auto` becomes a real language, and what `auto` meant becomes settings.
 *
 * `auto` said "name no language, let the browser decide". A first visit now asks the browser
 * before it asks the default -- over the languages the wiki *offers* (`other_languages`) --
 * so the negotiation `auto` existed for is the ordinary path, and what a default is for is
 * the visitor whose language the wiki does not have. There is no answer for that visitor
 * unless the wiki names one, which is why neither screen offers `auto` any more.
 *
 * The rewrite keeps the old behaviour rather than approximating it:
 *
 *  - the default becomes `fr`, which is exactly what `auto` fell back to when a browser asked
 *    for something unavailable (LanguageService's own last resort, and this is a French
 *    project);
 *  - and every other installed language is turned on, because negotiating over all of them is
 *    what `auto` did. A wiki that had `auto` therefore keeps answering every visitor in their
 *    own language, and gains a language switcher offering the same set.
 *
 * A wiki whose `default_language` already names a language is left alone -- including one that
 * names a language this server has no translation for, which is a webmaster's business and
 * not something to silently correct.
 */
class ADefaultLanguageIsALanguage extends YesWikiMigration
{
    private const FALLBACK = 'fr';

    public function run()
    {
        $file = ConfigurationFileProvider::getConfigFileFromEnv();
        $configuration = $this->getService(ConfigurationService::class)->getConfiguration($file);
        $configuration->load();

        $current = trim((string)($configuration['default_language'] ?? ''));
        $installed = $this->getService(LanguageService::class)->installedLanguages();

        if ($current !== '' && $current !== 'auto' && $current !== 'browser') {
            return;
        }

        $default = in_array(self::FALLBACK, $installed, true)
            ? self::FALLBACK
            : (string)(reset($installed) ?: self::FALLBACK);

        // whatever the wiki already offers, plus everything else it has: `auto` negotiated
        // over the whole installed set, and that is the behaviour being preserved
        $others = array_values(array_unique(array_merge(
            array_filter((array)($configuration['other_languages'] ?? []), 'is_string'),
            $installed
        )));
        $others = array_values(array_diff($others, [$default]));

        $configuration['default_language'] = $default;
        $configuration['other_languages'] = $others;
        if (!$configuration->write()) {
            // A migration that returns is recorded as run and never comes back, so a
            // configuration file that could not be written has to stop the upgrade rather
            // than leave a wiki whose default language is a word no language is called.
            throw new RuntimeException("could not write {$file}: default_language is still '{$current}'");
        }

        $this->getService(AdministrativeLogService::class)->log(
            'migration',
            "default_language was '{$current}', which names no language, and is now '{$default}'."
            . ' The languages this wiki offers were set to ' . implode(', ', $others)
            . ' so that a visitor still gets their own language when the wiki has it --'
            . ' which is what auto did. Both are on /?GererConfig.'
        );
    }
}
