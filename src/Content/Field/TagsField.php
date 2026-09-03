<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Attribute\Field;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Search\Service\TagsManager;

#[Field(['tags'])]
class TagsField extends EnumField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        if (!empty($this->linkedObjectName)) {
            $this->name = $this->linkedObjectName;
        }
        $this->maxChars = $this->maxChars ?? 255;
        $this->propertyName = $this->name;
    }

    public function getValueStructure()
    {
        return [$this->propertyName => ['_mode_' => 'multiple', '_type_' => 'string']];
    }

    /**
     * Keywords reach this field in two shapes: a comma-separated string on a bazar entry, whose tags field is an ordinary form field the webmaster named, and a list on a page, where they are `body.keywords` (ticket 09).
     *
     * @param array<string, mixed>|null $entry
     *
     * @return string|null
     */
    protected function getValue($entry)
    {
        $value = parent::getValue($entry);

        return is_array($value) ? implode(',', array_filter($value, 'is_string')) : $value;
    }

    protected function renderInput($entry)
    {
        $value = $this->getValue($entry);

        if (!isset($value)) {
            if ($this->getRequest()->query->has((string)$this->propertyName)) {
                $value = stripslashes((string)$this->getRequest()->query->get((string)$this->propertyName));
            } else {
                $value = stripslashes($this->default);
            }
        }

        return $this->render('@core/inputs/tags.twig', [
            'value' => $value,

            'tagsSearchUrl' => $this->getService(UrlFormatter::class)->href('', 'api/tags'),
        ]);
    }

    /**
     * The value is all this writes now.
     *
     * It used to keep a second index of its own beside it, as `_vocabulary/tag` triples. The
     * keywords of every Content are indexed once, by `SearchIndexer`, from the body this returns
     * (ticket 62).
     */
    public function formatValuesBeforeSave($entry)
    {
        return [$this->propertyName => $this->getValue($entry)];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);

        $tags = explode(',', (string)$value);

        if (!empty($tags[0])) {
            sort($tags);
            $tags = array_map(function ($tag) {
                return '<a class="tag-label label label-info" href="' . $this->getService(UrlFormatter::class)->href('', 'search', ['tags' => trim($tag)]) . '" title="' . _t('TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS') . '">' . $tag . '</a>';
            }, $tags);

            return $this->render('@core/fields/tags.twig', [
                'value' => join(' ', $tags),
            ]);
        }

        return '';
    }

    public function getName()
    {
        return $this->name;
    }

    public function getOptions()
    {
        if (empty($this->options)) {
            $this->loadOptionsFromTags();
        }

        return parent::getOptions();
    }

    private function loadOptionsFromTags(): void
    {
        $keywords = array_column($this->getService(TagsManager::class)->getAll(), 'value');
        $this->options = array_combine($keywords, $keywords);
    }
}
