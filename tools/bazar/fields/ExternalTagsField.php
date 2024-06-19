<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\ExternalBazarService;

/**
 * @Field({"externaltagsfield"})
 */
class ExternalTagsField extends TagsField
{
    protected $JSONFormAddress;

    public function __construct(array $values, ContainerInterface $services)
    {
        $values[self::FIELD_TYPE] = $values[ExternalBazarService::FIELD_ORIGINAL_TYPE];
        $values[ExternalBazarService::FIELD_ORIGINAL_TYPE] = '';
        $this->JSONFormAddress = $values[ExternalBazarService::FIELD_JSON_FORM_ADDR];
        $values[ExternalBazarService::FIELD_JSON_FORM_ADDR] = '';

        parent::__construct($values, $services);
    }

    protected function renderInput($entry)
    {
        return '';
    }

    public function formatValuesBeforeSave($entry)
    {
        return null;
    }

    public function getOptions()
    {
        // load options only when needed but not at construct to prevent infinite loops
        if (is_null($this->options)) {
            $this->loadOptionsFromJSONForm($this->JSONFormAddress);
        }

        return $this->options;
    }

    public function loadOptionsFromTags()
    {
        $this->options = null;
        $this->getOptions();
    }

    protected function renderStatic($entry)
    {
        // copy from parent but with different href

        $value = $this->getValue($entry);

        $tags = explode(',', $value);

        if (count($tags) > 0 && !empty($tags[0])) {
            sort($tags);
            $tags = array_map(function ($tag) use ($entry) {
                return '<a class="tag-label label label-info" href="'
                    . $entry['external-data']['baseUrl'] . '?' . $GLOBALS['wiki']->GetPageTag() . '/listpages&tags=' . urlencode(trim($tag)) . '" title="' . _t('TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS') . '">' . $tag . '</a>';
            }, $tags);

            return $this->render('@bazar/fields/tags.twig', [
                'value' => join(' ', $tags) ?? '',
            ]);
        } else {
            return '';
        }
    }
}
