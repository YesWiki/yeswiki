<?php

namespace YesWiki\Content\Exception;

use YesWiki\Kernel\Exception\SpeaksToTheReader;

/** Thrown when a list still gives its options to form fields, which would be left offering nothing (end-users 20). */
class ListInUseException extends \Exception implements SpeaksToTheReader
{
    /** @param list<array{formId: string, formLabel: string, fieldName: string, fieldLabel: string}> $fields */
    public function __construct(public readonly string $listId, public readonly array $fields)
    {
        parent::__construct(_t('LIST_IN_USE', [
            'list' => $listId,
            'forms' => implode(', ', array_values(array_unique(array_map(static fn (array $field): string => $field['formLabel'] ?: $field['formId'], $fields)))),
        ]));
    }
}
