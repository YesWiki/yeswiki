<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\BazarField;
use YesWiki\Bazar\Field\DateField;
use YesWiki\Bazar\Field\EmailField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Field\FileField;
use YesWiki\Bazar\Field\LinkField;
use YesWiki\Bazar\Field\TagsField;
use YesWiki\Bazar\Field\TextareaField;
use YesWiki\Bazar\Field\TextField;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\StringUtilService;
use YesWiki\Security\Controller\SecurityController;

/**
 * Reports malformed values in the entries of a form, and drops them on demand.
 */
class EntryChecker
{
    public const REQUIRED_EMPTY = 'required_empty';
    public const UNKNOWN_OPTION = 'unknown_option';
    public const INVALID_EMAIL = 'invalid_email';
    public const BROKEN_ENTRY = 'broken_entry';
    public const MISSING_FILE = 'missing_file';
    public const UNREADABLE_FILE = 'unreadable_file';
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
        self::UNREADABLE_FILE,
        self::UNREACHABLE_URL,
        self::UNFETCHED_URL,
        self::INVALID_EMAIL,
        self::INVALID_DATE,
        self::INVALID_URL,
        self::ORPHAN_FIELD,
    ];

    private const RESERVED_KEYS = [
        'id_fiche', 'id_typeannonce', 'date_creation_fiche', 'date_maj_fiche',
        'statut_fiche', 'url', '-is-external-', 'external-data', 'antispam',
        'sendmail', 'user', 'owner', 'html_data', 'semantic',
        'mot_de_passe_wikini', 'mot_de_passe_repete_wikini',
    ];

    public const REMOTE_OPTIONS = 'remote_options';
    public const NO_OPTIONS = 'no_options';

    public const UNCHECKED_REASONS = [
        self::REMOTE_OPTIONS,
        self::NO_OPTIONS,
    ];

    private const MAX_PICKABLE_OPTIONS = 200;

    private const DERIVED_SUFFIXES = [
        '_url', '_data', '_allday', '_hour', '_minutes', '_fromForm',
        '-previous', '_confirmNewName', '_force_label',
    ];

    protected $entryManager;
    protected $formManager;
    protected $pageManager;
    protected $securityController;
    protected $urlReachability;

    protected $entryTags;
    protected $probedUrls;
    protected $textReplacement;
    protected $forcedValues;
    protected $conditionsChecker;

    public function __construct(
        EntryManager $entryManager,
        FormManager $formManager,
        PageManager $pageManager,
        SecurityController $securityController,
        UrlReachability $urlReachability,
        ConditionsChecker $conditionsChecker
    ) {
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->pageManager = $pageManager;
        $this->securityController = $securityController;
        $this->urlReachability = $urlReachability;
        $this->conditionsChecker = $conditionsChecker;
        $this->entryTags = null;
        $this->probedUrls = [];
        $this->textReplacement = '';
        $this->forcedValues = [];
    }

    /**
     * Group every problem found in the entries of a form by problem code.
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
     */
    public function repair(string $formId, array $selectedKeys, array $pickedValues = [], string $textReplacement = '', array $forcedValues = []): array
    {
        if ($this->securityController->isWikiHibernated()) {
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

    private function applyToEntry(string $entryId, array $rows): void
    {
        $page = $this->pageManager->getOne($entryId, null, false, true);
        if (empty($page['body'])) {
            throw new \Exception(_t('BAZ_CHECKCONTENT_ENTRY_NOT_FOUND'));
        }
        $data = json_decode($page['body'], true);
        if (!is_array($data)) {
            throw new \Exception(_t('BAZ_CHECKCONTENT_UNREADABLE_ENTRY'));
        }

        foreach ($rows as $row) {
            if (array_key_exists('unset', $row['fix'])) {
                unset($data[$row['propertyName']]);
            } else {
                $data[$row['propertyName']] = $row['fix']['set'];
            }
        }
        $data['date_maj_fiche'] = date('Y-m-d H:i:s');

        if ($this->pageManager->save($entryId, json_encode($data), '') !== 0) {
            throw new \Exception(_t('BAZ_ERROR_EDIT_UNAUTHORIZED'));
        }
    }

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

    private function uncheckedFields(array $fields): array
    {
        $unchecked = array_fill_keys(self::UNCHECKED_REASONS, []);
        foreach ($fields as $field) {
            if (!($field instanceof EnumField) || !$this->hasCheckableOptions($field)) {
                continue;
            }
            if (!empty($field->isDistantJson)) {
                $unchecked[self::REMOTE_OPTIONS][] = $this->uncheckedField($field, $field->getPropertyName());
            } elseif (!$field->isEnumEntryField() && empty($field->getOptions())) {
                $unchecked[self::NO_OPTIONS][] = $this->uncheckedField($field, $field->getLinkedObjectName());
            }
        }

        return array_filter($unchecked);
    }

    private function uncheckedField(EnumField $field, $source): array
    {
        return [
            'propertyName' => $field->getPropertyName(),
            'fieldLabel' => $field->getLabel() ?: $field->getPropertyName(),
            'type' => $field->getType(),
            'source' => $this->stringify($source),
        ];
    }

    private function missingValueProblem(array $entry, BazarField $field): array
    {
        $problem = $this->problem(self::REQUIRED_EMPTY, $entry, $field, '', null);
        $forced = $this->forcedValues[$field->getPropertyName()] ?? null;
        $options = $this->pickableOptions($field);
        if (!empty($options)) {
            $multiple = $this->holdsSeveralValues($field);
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
     * Keep only the forced values the list actually offers, so a value gone from the list
     * leaves the field on its usual default rather than writing something unselectable.
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

    /**
     * Only a field meant to hold a sentence takes a stand-in like "NC" ; a date, a number
     * or an address would just be given a value it cannot represent.
     */
    private function acceptsFreeText(BazarField $field): bool
    {
        return $field instanceof TextareaField
            || ($field instanceof TextField && $field->getType() === 'text');
    }

    private function pickableOptions(BazarField $field): array
    {
        if (!($field instanceof EnumField) || !empty($field->isDistantJson)
            || !$this->hasCheckableOptions($field)) {
            return [];
        }
        $options = $field->getOptions();
        if (!is_array($options) || count($options) > self::MAX_PICKABLE_OPTIONS) {
            return [];
        }

        return $options;
    }

    private function checkEmail(EmailField $field, array $entry, $value): array
    {
        if (!is_string($value) || $this->isEmailAddress($value)) {
            return [];
        }
        $normalized = strtolower(trim($value));
        $fix = ['set' => $this->isEmailAddress($normalized) ? $normalized : ''];

        return [$this->problem(self::INVALID_EMAIL, $entry, $field, $value, $fix)];
    }

    /**
     * Judge an address on its structure alone, since FILTER_VALIDATE_EMAIL refuses the
     * accents that a name like josé@exemple.fr legitimately carries.
     */
    private function isEmailAddress(string $value): bool
    {
        return filter_var(preg_replace('/[^\x20-\x7E]/', 'a', $value), FILTER_VALIDATE_EMAIL) !== false;
    }

    private function checkDate(DateField $field, array $entry, $value): array
    {
        if (!is_string($value) || strtotime($value) !== false) {
            return [];
        }

        return [$this->problem(self::INVALID_DATE, $entry, $field, $value, ['set' => ''])];
    }

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

    private function editable(string $code, array $entry, BazarField $field, string $detail, string $kind, string $suggested): array
    {
        return array_replace(
            $this->problem($code, $entry, $field, $detail, null),
            ['freeText' => $kind, 'suggested' => $suggested]
        );
    }

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

    private function checkFile(FileField $field, array $entry, $value): array
    {
        if (!is_string($value)) {
            return [];
        }

        switch ($field->locateFile($entry, $value)) {
            case FileField::FILE_MISSING:
                return [$this->problem(self::MISSING_FILE, $entry, $field, $value, ['set' => ''])];
            case FileField::FILE_UNREADABLE:
                return [$this->reportOnly(self::UNREADABLE_FILE, $entry, $field, $value, 'BAZ_CHECKCONTENT_FIX_PERMISSIONS')];
            case FileField::FILE_REMOTE:
                return $this->checkRemoteFile($field, $entry, $value);
            default:
                return [];
        }
    }

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
                $url . ' — ' . _t('BAZ_CHECKCONTENT_UNFETCHED_' . strtoupper($probe['reason'])),
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

    private function reportOnly(string $code, array $entry, BazarField $field, string $detail, string $fixLabel): array
    {
        return array_replace(
            $this->problem($code, $entry, $field, $detail, null),
            ['fixLabel' => $fixLabel]
        );
    }

    private function checkOptions(EnumField $field, array $entry, $value): array
    {
        if (!$this->hasCheckableOptions($field)) {
            return [];
        }
        $options = $field->getOptions();
        if (!is_string($value) || !is_array($options) || empty($options)) {
            return [];
        }

        $values = $this->splitValues($field, $value);
        $unknown = array_diff($values, array_keys($options));
        if (empty($unknown)) {
            return [];
        }

        $kept = array_intersect($values, array_keys($options));
        $fix = ['set' => $this->holdsSeveralValues($field) ? implode(',', $kept) : ''];

        return [$this->problem(self::UNKNOWN_OPTION, $entry, $field, implode(', ', $unknown), $fix)];
    }

    private function checkEntryReferences(EnumField $field, array $entry, $value): array
    {
        if (!is_string($value) || !empty($field->isDistantJson)) {
            return [];
        }

        $values = $this->splitValues($field, $value);
        $broken = array_values(array_filter($values, function ($tag) {
            return !isset($this->getEntryTags()[$tag]);
        }));
        if (empty($broken)) {
            return [];
        }

        $kept = array_values(array_diff($values, $broken));
        $fix = ['set' => $this->holdsSeveralValues($field) ? implode(',', $kept) : ''];

        return [$this->problem(self::BROKEN_ENTRY, $entry, $field, implode(', ', $broken), $fix)];
    }

    private function checkOrphans(array $entry, array $knownProperties): array
    {
        $problems = [];
        foreach ($entry as $key => $value) {
            if (!is_string($key) || isset($knownProperties[$key])
                || in_array($key, self::RESERVED_KEYS, true)
                || $this->isDerivedKey($key, $knownProperties)) {
                continue;
            }
            $problems[] = [
                'code' => self::ORPHAN_FIELD,
                'key' => self::ORPHAN_FIELD . '::' . ($entry['id_fiche'] ?? '') . '::' . $key,
                'entryId' => $entry['id_fiche'] ?? '',
                'entryTitle' => $entry['bf_titre'] ?? ($entry['id_fiche'] ?? ''),
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

    private function splitValues(EnumField $field, string $value): array
    {
        $values = $this->holdsSeveralValues($field) ? explode(',', $value) : [$value];

        return array_values(array_filter(array_map('trim', $values), 'strlen'));
    }

    private function holdsSeveralValues(EnumField $field): bool
    {
        $structure = $field->getValueStructure()[$field->getPropertyName()] ?? [];

        return ($structure['_mode_'] ?? 'single') === 'multiple';
    }

    /**
     * A tags field builds its option list out of the values already stored on entries, so
     * checking a value against it can only ever confirm itself.
     */
    private function hasCheckableOptions(EnumField $field): bool
    {
        return !$field instanceof TagsField;
    }

    private function problem(string $code, array $entry, BazarField $field, $detail, ?array $fix): array
    {
        $entryId = $entry['id_fiche'] ?? '';

        return [
            'code' => $code,
            'key' => $code . '::' . $entryId . '::' . $field->getPropertyName(),
            'entryId' => $entryId,
            'entryTitle' => $entry['bf_titre'] ?? $entryId,
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

    private function getEntryTags(): array
    {
        if ($this->entryTags === null) {
            $this->entryTags = array_fill_keys($this->entryManager->getAllEntriesTags(), true);
        }

        return $this->entryTags;
    }

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
