<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\DateField;
use YesWiki\Content\Field\EmailField;
use YesWiki\Content\Field\EnumField;
use YesWiki\Content\Field\FileField;
use YesWiki\Content\Field\LinkField;
use YesWiki\Content\Field\TagsField;
use YesWiki\Content\Field\TextareaField;
use YesWiki\Content\Field\TextField;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\StringUtilService;

/** Reports malformed values in the entries of a form, and drops them on demand. */
class EntryChecker
{
    public const REQUIRED_EMPTY = 'required_empty';
    public const UNKNOWN_OPTION = 'unknown_option';
    public const INVALID_EMAIL = 'invalid_email';
    public const BROKEN_ENTRY = 'broken_entry';
    public const MISSING_FILE = 'missing_file';
    public const UNREACHABLE_URL = 'unreachable_url';
    public const UNFETCHED_URL = 'unfetched_url';
    public const INVALID_DATE = 'invalid_date';
    public const INVALID_URL = 'invalid_url';
    public const ORPHAN_FIELD = 'orphan_field';

    public const PROBLEMS = [
        self::REQUIRED_EMPTY,
        self::UNKNOWN_OPTION,
        self::BROKEN_ENTRY,
        self::MISSING_FILE,
        self::UNREACHABLE_URL,
        self::UNFETCHED_URL,
        self::INVALID_EMAIL,
        self::INVALID_DATE,
        self::INVALID_URL,
        self::ORPHAN_FIELD,
    ];

    /** Keys the storage layer owns, which no form field declares and no report should call orphaned. */
    private const RESERVED_KEYS = [
        'tag', 'form_id', 'created_at', 'updated_at', 'status', 'url',
        'antispam', 'sendmail', 'user', 'owner', 'html_data', 'semantic',
        'valider', 'MAX_FILE_SIZE', 'read-only', 'title',
        'mot_de_passe_wikini', 'mot_de_passe_repete_wikini',
    ];

    public const NO_OPTIONS = 'no_options';

    public const UNCHECKED_REASONS = [
        self::NO_OPTIONS,
    ];

    private const MAX_PICKABLE_OPTIONS = 200;

    private const DERIVED_SUFFIXES = [
        '_url', '_data', '_allday', '_hour', '_minutes', '_fromForm',
        '-previous', '_confirmNewName', '_force_label',
    ];

    protected EntryManager $entryManager;
    protected FormManager $formManager;
    protected PageManager $pageManager;
    protected HibernationService $hibernationService;
    protected UrlReachability $urlReachability;
    protected ConditionsChecker $conditionsChecker;

    /** @var array<string, bool>|null */
    protected ?array $entryTags = null;
    /** @var array<string, array<string, mixed>> */
    protected array $probedUrls = [];
    protected string $textReplacement = '';
    /** @var array<string, string> */
    protected array $forcedValues = [];

    public function __construct(
        EntryManager $entryManager,
        FormManager $formManager,
        PageManager $pageManager,
        HibernationService $hibernationService,
        UrlReachability $urlReachability,
        ConditionsChecker $conditionsChecker
    ) {
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->pageManager = $pageManager;
        $this->hibernationService = $hibernationService;
        $this->urlReachability = $urlReachability;
        $this->conditionsChecker = $conditionsChecker;
    }

    /**
     * Group every problem found in the entries of a form by problem code.
     *
     * @param array<string, string> $forcedValues
     *
     * @return array{entriesCount: int, problems: array<string, list<array<string, mixed>>>, unchecked: array<string, list<array<string, mixed>>>}
     */
    public function check(string $formId, string $textReplacement = '', array $forcedValues = []): array
    {
        $this->textReplacement = $textReplacement;
        $this->forcedValues = $forcedValues;
        $form = $this->formManager->getOne($formId);
        if (empty($form) || !is_array($form['prepared'] ?? null)) {
            return ['entriesCount' => 0, 'problems' => [], 'unchecked' => []];
        }

        $fields = array_filter($form['prepared'], function ($field) {
            return $field instanceof BazarField && !empty($field->getPropertyName());
        });
        $knownProperties = [];
        foreach ($fields as $field) {
            $knownProperties[$field->getPropertyName()] = true;
        }

        $entries = $this->entryManager->search(['formsIds' => [$formId]]);
        $this->probedUrls = $this->urlReachability->probe($this->remoteFileValues($fields, $entries));
        $problems = array_fill_keys(self::PROBLEMS, []);

        $hasConditions = $this->conditionsChecker->hasConditions($form);

        foreach ($entries as $entry) {
            $hidden = $hasConditions ? $this->conditionsChecker->hiddenPropertyNames($form, $entry) : [];
            foreach ($fields as $field) {
                if (in_array($field->getPropertyName(), $hidden, true)) {
                    continue;
                }
                foreach ($this->checkField($field, $entry) as $problem) {
                    $problems[$problem['code']][] = $problem;
                }
            }
            foreach ($this->checkOrphans($entry, $knownProperties) as $problem) {
                $problems[$problem['code']][] = $problem;
            }
        }

        return [
            'entriesCount' => count($entries),
            'problems' => array_filter($problems),
            'unchecked' => $this->uncheckedFields($fields),
        ];
    }

    /**
     * Apply the fix of every selected problem, one save per entry.
     *
     * @param list<string>          $selectedKeys
     * @param array<string, mixed>  $pickedValues
     * @param array<string, string> $forcedValues
     *
     * @return array{repaired: int, entries: int, failed: array<string, string>}
     */
    public function repair(string $formId, array $selectedKeys, array $pickedValues = [], string $textReplacement = '', array $forcedValues = []): array
    {
        if ($this->hibernationService->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        $rowsByKey = [];
        foreach ($this->check($formId, $textReplacement, $forcedValues)['problems'] as $rows) {
            foreach ($rows as $row) {
                $rowsByKey[$row['key']] = $row;
            }
        }

        $rowsByEntry = [];
        foreach ($selectedKeys as $key) {
            $row = $rowsByKey[$key] ?? null;
            if ($row === null) {
                continue;
            }
            $fix = $row['fix'] ?? $this->pickedFix($row, $pickedValues[$key] ?? null);
            if ($fix !== null) {
                $rowsByEntry[$row['entryId']][] = ['propertyName' => $row['propertyName'], 'fix' => $fix];
            }
        }

        $repaired = 0;
        $failed = [];
        foreach ($rowsByEntry as $entryId => $rows) {
            try {
                $this->applyToEntry(strval($entryId), $rows);
                $repaired += count($rows);
            } catch (\Throwable $error) {
                $failed[$entryId] = $error->getMessage();
            }
        }

        return [
            'repaired' => $repaired,
            'entries' => count($rowsByEntry) - count($failed),
            'failed' => $failed,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param mixed                $picked
     *
     * @return array<string, mixed>|null
     */
    private function pickedFix(array $row, $picked): ?array
    {
        if (!empty($row['freeText'])) {
            $text = is_scalar($picked) ? trim(strval($picked)) : '';
            if ($text === '' || ($row['freeText'] === 'url' && !StringUtilService::isWebAddress($text))) {
                return null;
            }

            return ['set' => $text];
        }
        if (empty($row['options']) || $picked === null) {
            return null;
        }

        $values = array_filter(
            array_map('strval', array_filter(is_array($picked) ? $picked : [$picked], 'is_scalar')),
            function ($value) use ($row) {
                return array_key_exists($value, $row['options']);
            }
        );
        if (empty($values)) {
            return null;
        }

        return ['set' => $row['multiple'] ? implode(',', $values) : strval(reset($values))];
    }

    /** @param list<array{propertyName: string, fix: array<string, mixed>}> $rows */
    private function applyToEntry(string $entryId, array $rows): void
    {
        $page = $this->pageManager->getOne($entryId, null, false, true);
        if (empty($page['body'])) {
            throw new \Exception(_t('BAZ_CHECKCONTENT_ENTRY_NOT_FOUND'));
        }
        $data = $this->entryManager->decode($page['body']);
        if (!is_array($data) || empty($data)) {
            throw new \Exception(_t('BAZ_CHECKCONTENT_UNREADABLE_ENTRY'));
        }

        foreach ($rows as $row) {
            if (array_key_exists('unset', $row['fix'])) {
                unset($data[$row['propertyName']]);
            } else {
                $data[$row['propertyName']] = $row['fix']['set'];
            }
        }
        $data['updated_at'] = date('Y-m-d H:i:s');

        if ($this->pageManager->save($entryId, $data, '') !== 0) {
            throw new \Exception(_t('BAZ_ERROR_EDIT_UNAUTHORIZED'));
        }
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return list<array<string, mixed>>
     */
    private function checkField(BazarField $field, array $entry): array
    {
        $value = $entry[$field->getPropertyName()] ?? null;

        if ($field->isEmpty($value)) {
            return $field->isRequired() ? [$this->missingValueProblem($entry, $field)] : [];
        }

        if ($field instanceof EmailField) {
            return $this->checkEmail($field, $entry, $value);
        }
        if ($field instanceof DateField) {
            return $this->checkDate($field, $entry, $value);
        }
        if ($field instanceof LinkField) {
            return $this->checkUrl($field, $entry, $value);
        }
        if ($field instanceof FileField) {
            return $this->checkFile($field, $entry, $value);
        }
        if ($field instanceof EnumField) {
            return $field->isEnumEntryField()
                ? $this->checkEntryReferences($field, $entry, $value)
                : $this->checkOptions($field, $entry, $value);
        }

        return [];
    }

    /**
     * @param array<int|string, BazarField> $fields
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function uncheckedFields(array $fields): array
    {
        $unchecked = array_fill_keys(self::UNCHECKED_REASONS, []);
        foreach ($fields as $field) {
            if (!($field instanceof EnumField) || !$this->hasCheckableOptions($field)) {
                continue;
            }
            if (!$field->isEnumEntryField() && empty($field->getOptions())) {
                $unchecked[self::NO_OPTIONS][] = $this->uncheckedField($field, $field->getLinkedObjectName());
            }
        }

        return array_filter($unchecked);
    }

    /**
     * @param mixed $source
     *
     * @return array<string, string>
     */
    private function uncheckedField(EnumField $field, $source): array
    {
        return [
            'propertyName' => (string)$field->getPropertyName(),
            'fieldLabel' => (string)($field->getLabel() ?: $field->getPropertyName()),
            'type' => (string)$field->getType(),
            'source' => $this->stringify($source),
        ];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function missingValueProblem(array $entry, BazarField $field): array
    {
        $problem = $this->problem(self::REQUIRED_EMPTY, $entry, $field, '', null);
        $forced = $this->forcedValues[$field->getPropertyName()] ?? null;
        $options = $this->pickableOptions($field);
        if (!empty($options)) {
            $multiple = $field instanceof EnumField && $this->holdsSeveralValues($field);
            $picked = $this->forcedOptions($forced, $options, $multiple);
            $problem['options'] = $options;
            $problem['multiple'] = $multiple;
            $problem['forced'] = $picked !== null;
            $problem['suggested'] = $picked
                ?? (array_key_exists($field->getDefault(), $options) ? strval($field->getDefault()) : '');
        } elseif ($forced !== null) {
            $problem['freeText'] = 'any';
            $problem['suggested'] = $forced;
            $problem['forced'] = true;
        } elseif ($this->textReplacement !== '' && $this->acceptsFreeText($field)) {
            $problem['freeText'] = 'any';
            $problem['suggested'] = $this->textReplacement;
        }

        return $problem;
    }

    /**
     * Keep only the forced values the list actually offers, so a value gone from the list leaves the field on its usual default rather than writing something unselectable.
     *
     * @param array<int|string, mixed> $options
     */
    private function forcedOptions(?string $forced, array $options, bool $multiple): ?string
    {
        if ($forced === null) {
            return null;
        }
        $values = array_filter(array_map('trim', explode(',', $forced)), function ($value) use ($options) {
            return $value !== '' && array_key_exists($value, $options);
        });
        if (empty($values)) {
            return null;
        }

        return $multiple ? implode(',', $values) : strval(reset($values));
    }

    /** Only a field meant to hold a sentence takes a stand-in like "NC" ; a date, a number or an address would just be given a value it cannot represent. */
    private function acceptsFreeText(BazarField $field): bool
    {
        return $field instanceof TextareaField
            || ($field instanceof TextField && $field->getType() === 'text');
    }

    /** @return array<int|string, mixed> */
    private function pickableOptions(BazarField $field): array
    {
        if (!($field instanceof EnumField) || !$this->hasCheckableOptions($field)) {
            return [];
        }
        $options = $field->getOptions();
        if (count($options) > self::MAX_PICKABLE_OPTIONS) {
            return [];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $entry
     * @param mixed                $value
     *
     * @return list<array<string, mixed>>
     */
    private function checkEmail(EmailField $field, array $entry, $value): array
    {
        if (!is_string($value) || $this->isEmailAddress($value)) {
            return [];
        }
        $normalized = strtolower(trim($value));
        $fix = ['set' => $this->isEmailAddress($normalized) ? $normalized : ''];

        return [$this->problem(self::INVALID_EMAIL, $entry, $field, $value, $fix)];
    }

    /** Judge an address on its structure alone, since FILTER_VALIDATE_EMAIL refuses the accents that a name like josé@exemple.fr legitimately carries. */
    private function isEmailAddress(string $value): bool
    {
        return filter_var((string)preg_replace('/[^\x20-\x7E]/', 'a', $value), FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @param array<string, mixed> $entry
     * @param mixed                $value
     *
     * @return list<array<string, mixed>>
     */
    private function checkDate(DateField $field, array $entry, $value): array
    {
        if (!is_string($value) || strtotime($value) !== false) {
            return [];
        }

        return [$this->problem(self::INVALID_DATE, $entry, $field, $value, ['set' => ''])];
    }

    /**
     * @param array<string, mixed> $entry
     * @param mixed                $value
     *
     * @return list<array<string, mixed>>
     */
    private function checkUrl(LinkField $field, array $entry, $value): array
    {
        if (!is_string($value) || StringUtilService::isWebAddress($value)) {
            return [];
        }

        $trimmed = trim($value);
        if (StringUtilService::isWebAddress($trimmed)) {
            return [$this->problem(self::INVALID_URL, $entry, $field, $value, ['set' => $trimmed])];
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:/*$#i', $trimmed)) {
            return [$this->problem(self::INVALID_URL, $entry, $field, $value, ['set' => ''])];
        }

        return [$this->editable(self::INVALID_URL, $entry, $field, $value, 'url', $trimmed)];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function editable(string $code, array $entry, BazarField $field, string $detail, string $kind, string $suggested): array
    {
        return array_replace(
            $this->problem($code, $entry, $field, $detail, null),
            ['freeText' => $kind, 'suggested' => $suggested]
        );
    }

    /**
     * @param array<int|string, BazarField>    $fields
     * @param array<int|string, array<string, mixed>> $entries
     *
     * @return list<string>
     */
    private function remoteFileValues(array $fields, array $entries): array
    {
        $fileFields = array_filter($fields, function ($field) {
            return $field instanceof FileField;
        });
        if (empty($fileFields)) {
            return [];
        }

        $urls = [];
        foreach ($entries as $entry) {
            foreach ($fileFields as $field) {
                $value = $entry[$field->getPropertyName()] ?? null;
                if (is_string($value) && $field->locateFile($entry, $value) === FileField::FILE_REMOTE) {
                    $urls[] = $value;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @param array<string, mixed> $entry
     * @param mixed                $value
     *
     * @return list<array<string, mixed>>
     */
    private function checkFile(FileField $field, array $entry, $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        switch ($field->locateFile($entry, $value)) {
            case FileField::FILE_MISSING:
                return [$this->problem(self::MISSING_FILE, $entry, $field, $value, ['set' => ''])];
            case FileField::FILE_REMOTE:
                return $this->checkRemoteFile($field, $entry, $value);
            default:
                return [];
        }
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return list<array<string, mixed>>
     */
    private function checkRemoteFile(FileField $field, array $entry, string $url): array
    {
        $probe = $this->probedUrls[$url] ?? null;
        if ($probe === null) {
            return [];
        }

        if (empty($probe['fetched'])) {
            return [$this->reportOnly(
                self::UNFETCHED_URL,
                $entry,
                $field,
                $url . ' — ' . _t('BAZ_CHECKCONTENT_UNFETCHED_' . strtoupper(strval($probe['reason'] ?? ''))),
                'BAZ_CHECKCONTENT_FIX_NOTHING'
            )];
        }

        $status = $probe['status'] ?? null;
        if ($status !== null && $status < 400) {
            return [];
        }

        return [$this->reportOnly(
            self::UNREACHABLE_URL,
            $entry,
            $field,
            $url . ' — ' . ($status === null ? strval($probe['error'] ?? '') : strval($status)),
            'BAZ_CHECKCONTENT_FIX_MANUAL'
        )];
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function reportOnly(string $code, array $entry, BazarField $field, string $detail, string $fixLabel): array
    {
        return array_replace(
            $this->problem($code, $entry, $field, $detail, null),
            ['fixLabel' => $fixLabel]
        );
    }

    /**
     * @param array<string, mixed> $entry
     * @param mixed                $value
     *
     * @return list<array<string, mixed>>
     */
    private function checkOptions(EnumField $field, array $entry, $value): array
    {
        if (!$this->hasCheckableOptions($field)) {
            return [];
        }
        $options = $field->getOptions();
        if (!is_string($value) || empty($options)) {
            return [];
        }

        $values = $this->splitValues($field, $value);
        $unknown = array_diff($values, array_keys($options));
        if (empty($unknown)) {
            return [];
        }

        $multiple = $this->holdsSeveralValues($field);
        $kept = array_intersect($values, array_keys($options));
        $fix = ['set' => $multiple ? implode(',', $kept) : ''];

        return [array_replace(
            $this->problem(self::UNKNOWN_OPTION, $entry, $field, implode(', ', $unknown), $fix),
            ['multiple' => $multiple]
        )];
    }

    /**
     * @param array<string, mixed> $entry
     * @param mixed                $value
     *
     * @return list<array<string, mixed>>
     */
    private function checkEntryReferences(EnumField $field, array $entry, $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        $values = $this->splitValues($field, $value);
        $broken = array_values(array_filter($values, function ($tag) {
            return !isset($this->getEntryTags()[$tag]);
        }));
        if (empty($broken)) {
            return [];
        }

        $multiple = $this->holdsSeveralValues($field);
        $kept = array_values(array_diff($values, $broken));
        $fix = ['set' => $multiple ? implode(',', $kept) : ''];

        return [array_replace(
            $this->problem(self::BROKEN_ENTRY, $entry, $field, implode(', ', $broken), $fix),
            ['multiple' => $multiple]
        )];
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, bool>  $knownProperties
     *
     * @return list<array<string, mixed>>
     */
    private function checkOrphans(array $entry, array $knownProperties): array
    {
        $problems = [];
        foreach ($entry as $key => $value) {
            $key = (string)$key;
            if (isset($knownProperties[$key])
                || in_array($key, self::RESERVED_KEYS, true)
                || $this->isDerivedKey($key, $knownProperties)) {
                continue;
            }
            $problems[] = [
                'code' => self::ORPHAN_FIELD,
                'key' => self::ORPHAN_FIELD . '::' . ($entry['tag'] ?? '') . '::' . $key,
                'entryId' => $entry['tag'] ?? '',
                'entryTitle' => $entry['title'] ?? ($entry['tag'] ?? ''),
                'propertyName' => $key,
                'fieldLabel' => $key,
                'detail' => $this->stringify($value),
                'fix' => ['unset' => true],
                'fixLabel' => 'BAZ_CHECKCONTENT_FIX_MANUAL',
                'options' => [],
                'multiple' => false,
                'freeText' => '',
                'suggested' => '',
                'forced' => false,
            ];
        }

        return $problems;
    }

    /** @param array<string, bool> $knownProperties */
    private function isDerivedKey(string $key, array $knownProperties): bool
    {
        foreach (self::DERIVED_SUFFIXES as $suffix) {
            if (str_ends_with($key, $suffix)
                && isset($knownProperties[substr($key, 0, -strlen($suffix))])) {
                return true;
            }
        }

        return str_starts_with($key, 'oldimage_') && isset($knownProperties[substr($key, 9)]);
    }

    /** @return list<string> */
    private function splitValues(EnumField $field, string $value): array
    {
        $values = $this->holdsSeveralValues($field) ? explode(',', $value) : [$value];

        return array_values(array_filter(array_map('trim', $values), function (string $value): bool {
            return $value !== '';
        }));
    }

    private function holdsSeveralValues(EnumField $field): bool
    {
        $structure = $field->getValueStructure()[$field->getPropertyName()] ?? [];

        return ($structure['_mode_'] ?? 'single') === 'multiple';
    }

    /** A tags field builds its option list out of the values already stored on entries, so checking a value against it can only ever confirm itself. */
    private function hasCheckableOptions(EnumField $field): bool
    {
        return !$field instanceof TagsField;
    }

    /**
     * @param array<string, mixed> $entry
     * @param mixed                $detail
     * @param array<string, mixed>|null $fix
     *
     * @return array<string, mixed>
     */
    private function problem(string $code, array $entry, BazarField $field, $detail, ?array $fix): array
    {
        $entryId = $entry['tag'] ?? '';

        return [
            'code' => $code,
            'key' => $code . '::' . $entryId . '::' . $field->getPropertyName(),
            'entryId' => $entryId,
            'entryTitle' => $entry['title'] ?? $entryId,
            'propertyName' => $field->getPropertyName(),
            'fieldLabel' => $field->getLabel() ?: $field->getPropertyName(),
            'detail' => $this->stringify($detail),
            'fix' => $fix,
            'fixLabel' => 'BAZ_CHECKCONTENT_FIX_MANUAL',
            'options' => [],
            'multiple' => false,
            'freeText' => '',
            'suggested' => '',
            'forced' => false,
        ];
    }

    /** @return array<string, bool> */
    private function getEntryTags(): array
    {
        if ($this->entryTags === null) {
            $this->entryTags = array_fill_keys($this->entryManager->getAllEntriesTags(), true);
        }

        return $this->entryTags;
    }

    /** @param mixed $value */
    private function stringify($value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_scalar($value)) {
            return strval($value);
        }

        return is_null($value) ? '' : strval(json_encode($value));
    }
}
