<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Core\Service\StringUtilService;
use YesWiki\Wiki;

/**
 * @Field({"checkboxfiche"})
 */
class CheckboxEntryField extends CheckboxField
{
    public $isDistantJson;
    protected $baseUrl;

    private const TEMPLATE_LINE_TYPE = 0;
    private const TEMPLATE_LINE_NAME = 1;
    private const DESCRIPTION_FIELD_NAME = 'bf_description';
    private const THUMBNAIL_SIZE = 64;
    private const EXCERPT_MAX_LENGTH = 140;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->type = 'checkboxfiche';

        // load options only when needed but not at construct to prevent infinite loops

        $wiki = $this->services->get(Wiki::class);
        $this->displayFilterLimit = $wiki->config['BAZ_MAX_CHECKBOXLISTE_SANS_FILTRE'];
        $this->displaySelectAllLimit = empty($wiki->config['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL']) ?
            $this->displayFilterLimit :
            $wiki->config['BAZ_MAX_CHECKBOXENTRY_WITHOUT_SELECTALL'];
        $this->formName = null;
        $this->normalDisplayMode = (in_array(
            $wiki->config['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'],
            array_keys(self::CHECKBOX_TWIG_LIST)
        )) ? $wiki->config['BAZ_MAX_CHECKBOXENTRY_DISPLAY_MODE'] :
            self::CHECKBOX_DISPLAY_MODE_LIST;
        $this->dragAndDropDisplayMode = '@bazar/inputs/checkbox_drag_and_drop_entry.twig';

        $this->isDistantJson = filter_var($this->name, FILTER_VALIDATE_URL);

        if ($this->isDistantJson) {
            $this->prepareJSONEntryField();
        } else {
            $this->options = null;
            $this->baseUrl = null;
        }
    }

    protected function renderStatic($entry)
    {
        $keys = $this->getValues($entry);
        $values = [];
        foreach ($keys as $key) {
            if (in_array($key, array_keys($this->getOptions()))) {
                $values[$key]['value'] = $this->options[$key];
                if ($this->isDistantJson) {
                    if (!empty($this->optionsUrls[$key])) {
                        $values[$key]['href'] = $this->optionsUrls[$key];
                    } else {
                        $values[$key]['href'] = $this->baseUrl . $key;
                    }
                } else {
                    $values[$key]['href'] = $this->services->get(Wiki::class)->Href('', $key);
                }
            }
        }

        return (count($values) > 0) ? $this->render('@bazar/fields/checkboxentry.twig', [
            'values' => $values,
        ]) : '';
    }

    protected function getFormName()
    {
        // needed for CheckboxEntry to update title only when
        // rendering Input and prevent infinite loop at construct

        if (!empty($this->name)) {
            $form = $this->getLinkedForm();
            $this->formName = isset($form['bn_label_nature']) ? ('Fiches ' . $form['bn_label_nature']) : _t('BAZ_NO_FORMS_FOUND');
        }

        return $this->formName;
    }

    public function getOptions()
    {
        return $this->getEntriesOptions();
    }

    protected function orderOptions(array $options): array
    {
        $orderBy = $this->orderBy;
        if (
            empty($orderBy)
            || in_array($orderBy, [self::ORDER_BY_LABEL, self::ORDER_BY_ID], true)
            || empty($this->optionsEntries)
        ) {
            return parent::orderOptions($options);
        }

        uksort($options, function ($first, $second) use ($orderBy) {
            return $this->compareForOrder(
                $this->optionsEntries[$first][$orderBy] ?? '',
                $this->optionsEntries[$second][$orderBy] ?? ''
            );
        });

        return $options;
    }

    protected function getOptionsDetails(array $optionsIds): array
    {
        if ($this->isDistantJson || empty($optionsIds) || empty($this->optionsEntries)) {
            return [];
        }

        [$imageFieldName, $descriptionFieldName] = $this->getPreviewFieldNames();
        if (empty($imageFieldName) && empty($descriptionFieldName)) {
            return [];
        }

        $details = [];
        foreach ($optionsIds as $optionId) {
            $entry = $this->optionsEntries[$optionId] ?? null;
            if (empty($entry)) {
                continue;
            }
            $details[$optionId] = [
                'image' => empty($imageFieldName) ? null : $this->getThumbnailUrl($entry, $imageFieldName),
                'description' => empty($descriptionFieldName) ? null : $this->getExcerpt($entry[$descriptionFieldName] ?? ''),
            ];
        }

        return $details;
    }

    /**
     * The form the options are entries of, or an empty array when they come from elsewhere.
     */
    private function getLinkedForm(): array
    {
        $formId = $this->getLinkedObjectName();
        if (!is_numeric($formId)) {
            return [];
        }

        return $this->services->get(FormManager::class)->getOne(strval($formId)) ?? [];
    }

    /**
     * Names of the linked form's fields previewed beside each option.
     */
    private function getPreviewFieldNames(): array
    {
        $form = $this->getLinkedForm();
        $imageFieldName = $descriptionFieldName = null;
        foreach ($form['template'] ?? [] as $templateLine) {
            $fieldType = $templateLine[self::TEMPLATE_LINE_TYPE] ?? '';
            $fieldName = $templateLine[self::TEMPLATE_LINE_NAME] ?? '';
            if (empty($fieldName)) {
                continue;
            }
            if ($fieldType === 'image' && $imageFieldName === null) {
                $imageFieldName = $fieldName;
            }
            if ($fieldType === 'textelong' && ($descriptionFieldName === null || $fieldName === self::DESCRIPTION_FIELD_NAME)) {
                $descriptionFieldName = $fieldName;
            }
        }

        return [$imageFieldName, $descriptionFieldName];
    }

    /**
     * Thumbnail of an entry's image, resized to the size the option row shows.
     */
    private function getThumbnailUrl(array $entry, string $imageFieldName): ?string
    {
        $value = $entry['image' . $imageFieldName] ?? '';
        if (empty($value)) {
            return null;
        }
        if (StringUtilService::isWebAddress($value)) {
            return $value;
        }

        $source = 'files/' . $value;
        $resized = redimensionner_image(
            $source,
            'cache/image_' . self::THUMBNAIL_SIZE . '_' . self::THUMBNAIL_SIZE . '_' . $value,
            self::THUMBNAIL_SIZE,
            self::THUMBNAIL_SIZE,
            'crop'
        );

        return empty($resized) ? null : $resized;
    }

    /**
     * Plain text opening of a description, short enough to fit on an option row.
     */
    private function getExcerpt($rawValue): ?string
    {
        if (!is_scalar($rawValue)) {
            return null;
        }
        $text = str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', strval($rawValue));
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, YW_CHARSET);
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if ($text === '') {
            return null;
        }

        return mb_strlen($text) > self::EXCERPT_MAX_LENGTH
            ? mb_substr($text, 0, self::EXCERPT_MAX_LENGTH) . '…'
            : $text;
    }

    /**
     * check if the current class is EnumEntry.
     */
    public function isEnumEntryField(): bool
    {
        return true;
    }
}
