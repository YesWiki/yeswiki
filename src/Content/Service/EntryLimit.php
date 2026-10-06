<?php

/* A form's `max_entries`: how many entries it may hold, and what its entry form says about it. */

namespace YesWiki\Content\Service;

use YesWiki\Search\Service\SearchManager;

class EntryLimit
{
    public function __construct(private readonly SearchManager $searchManager)
    {
    }

    /** @param array<string, mixed> $form whose limit this is, 0 when it has none */
    public static function limitOf(array $form): int
    {
        return max(0, (int)($form['max_entries'] ?? 0));
    }

    /** @param array<string, mixed> $form whose entries are counted, whoever may read them */
    public function countOf(array $form): int
    {
        return count($this->searchManager->search(['formsIds' => [(string)$form['id']]]));
    }

    /** @param array<string, mixed> $form whose entry form this warning replaces once the limit is reached; null while there is room */
    public function refusal(array $form): ?string
    {
        $limit = self::limitOf($form);
        if ($limit === 0) {
            return null;
        }
        $count = $this->countOf($form);
        if ($count < $limit) {
            return null;
        }

        $message = trim((string)($form['max_entries_message'] ?? ''));

        return $message === ''
            ? _t('FORM_MAX_ENTRIES_DEFAULT_MESSAGE', ['limit' => $limit, 'nb' => $count])
            : strtr($message, ['{limit}' => (string)$limit, '{nb}' => (string)$count]);
    }

    /** @param array<string, mixed> $form whose entry form this line goes above; null without a limit or a message for it */
    public function counter(array $form): ?string
    {
        $limit = self::limitOf($form);
        $message = trim((string)($form['max_entries_count_message'] ?? ''));
        if ($limit === 0 || $message === '') {
            return null;
        }

        return strtr($message, ['{limit}' => (string)$limit, '{nb}' => (string)$this->countOf($form)]);
    }
}
