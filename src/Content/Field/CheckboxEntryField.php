<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\ImageResizer;
use YesWiki\Kernel\Service\StringUtilService;
use YesWiki\Kernel\Service\UrlFormatter;

#[Field(['checkboxfiche'])]
class CheckboxEntryField extends CheckboxField
{
    private const DESCRIPTION_FIELD_NAME = 'bf_description';
    private const THUMBNAIL_SIZE = 64;
    private const EXCERPT_MAX_LENGTH = 140;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'checkboxfiche';

        $this->displayFilterLimit = $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXLISTE_SANS_FILTRE'];
        $this->displaySelectAllLimit = empty($this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL']) ?
            $this->displayFilterLimit :
            $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL'];
        $this->formName = null;
        $this->normalDisplayMode = (in_array(
            $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'],
            array_keys(self::CHECKBOX_TWIG_LIST)
        )) ? $this->getService(\YesWiki\Kernel\Service\RuntimeConfig::class)['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'] :
            self::CHECKBOX_DISPLAY_MODE_LIST;
        $this->dragAndDropDisplayMode = '@core/inputs/checkbox_drag_and_drop_entry.twig';

        $this->options = null;
    }

    protected function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = [];
        $options = $this->getOptions();
        foreach ($keys as $key) {
            if (array_key_exists($key, $options)) {
                $values[$key]['value'] = $options[$key];
                $values[$key]['href'] = $this->getService(UrlFormatter::class)->href('', $key);
            }
        }

        return (count($values) > 0) ? $this->render('@core/fields/checkboxentry.twig', [
            'values' => $values,
        ]) : '';
    }

    protected function getFormName()
    {
        $form = $this->getLinkedForm();
        $this->formName = isset($form['label']) ? ('Fiches ' . $form['label']) : _t('BAZ_NO_FORMS_FOUND');

        return $this->formName;
    }

    protected function orderOptions(array $options): array
    {
        $orderBy = $this->orderBy;
        if ($orderBy === '' || in_array($orderBy, [self::ORDER_BY_LABEL, self::ORDER_BY_ID], true) || empty($this->optionsEntries)) {
            return parent::orderOptions($options);
        }

        uksort($options, fn ($first, $second) => $this->compareForOrder(
            $this->optionsEntries[$first][$orderBy] ?? '',
            $this->optionsEntries[$second][$orderBy] ?? ''
        ));

        return $options;
    }

    protected function labelForMissingOption(int|string $optionId): string
    {
        $entry = $this->getService(EntryManager::class)->getOne((string)$optionId);
        $title = $entry['title'] ?? $entry['bf_titre'] ?? '';

        return is_scalar($title) && (string)$title !== '' ? (string)$title : parent::labelForMissingOption($optionId);
    }

    protected function getOptionsDetails(array $optionsIds): array
    {
        if (empty($optionsIds) || empty($this->optionsEntries)) {
            return [];
        }

        [$imagePropertyName, $descriptionPropertyName] = $this->previewPropertyNames();
        if ($imagePropertyName === null && $descriptionPropertyName === null) {
            return [];
        }

        $details = [];
        foreach ($optionsIds as $optionId) {
            $entry = $this->optionsEntries[$optionId] ?? null;
            if (empty($entry)) {
                continue;
            }
            $details[$optionId] = [
                'image' => $imagePropertyName === null ? null : $this->thumbnailUrl((string)($entry[$imagePropertyName] ?? '')),
                'description' => $descriptionPropertyName === null ? null : $this->excerpt($entry[$descriptionPropertyName] ?? ''),
            ];
        }

        return $details;
    }

    /** @return array<string, mixed> the form the options are entries of, empty when there is none */
    private function getLinkedForm(): array
    {
        $formId = (string)$this->getLinkedObjectName();
        if (!is_numeric($formId)) {
            return [];
        }

        return $this->getService(FormManager::class)->getOne($formId) ?? [];
    }

    /** @return array{0: ?string, 1: ?string} property names of the linked form's first image and of its description */
    private function previewPropertyNames(): array
    {
        $imagePropertyName = $descriptionPropertyName = null;
        foreach ($this->getLinkedForm()['prepared'] ?? [] as $field) {
            if ($field instanceof ImageField && $imagePropertyName === null) {
                $imagePropertyName = $field->getPropertyName();
            }
            if ($field instanceof TextareaField && ($descriptionPropertyName === null || $field->getPropertyName() === self::DESCRIPTION_FIELD_NAME)) {
                $descriptionPropertyName = $field->getPropertyName();
            }
        }

        return [$imagePropertyName, $descriptionPropertyName];
    }

    /** The entry image, cropped to the size an option row shows. */
    private function thumbnailUrl(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (StringUtilService::isWebAddress($value)) {
            return $value;
        }

        $uploadPath = rtrim($this->getService(AttachedFilePaths::class)->uploadPath(), '/') . '/';
        $size = (string)self::THUMBNAIL_SIZE;
        $resized = $this->getService(ImageResizer::class)->cached($uploadPath . $value, $size, $size, 'crop');

        return $resized === '' ? null : $this->getService(UrlFormatter::class)->getBaseUrl() . '/' . $resized;
    }

    /** The plain-text opening of a description, short enough for an option row. */
    private function excerpt(mixed $rawValue): ?string
    {
        if (!is_scalar($rawValue)) {
            return null;
        }
        $text = str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', (string)$rawValue);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string)preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > self::EXCERPT_MAX_LENGTH
            ? mb_substr($text, 0, self::EXCERPT_MAX_LENGTH) . '…'
            : $text;
    }

    public function getOptions()
    {
        return $this->getEntriesOptions();
    }

    /** check if the current class is EnumEntry. */
    public function isEnumEntryField(): bool
    {
        return true;
    }
}
