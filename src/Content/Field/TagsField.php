<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\EntryTagsCleared;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;

#[\Field(['tags'])]
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
            if ($this->getRequest()->query->has($this->propertyName)) {
                $value = stripslashes($this->getRequest()->query->get($this->propertyName));
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
     * The keyword index is keyed by the Content's tag, so the tag has to exist before this field can write it.
     */
    public function requiresTagBeforeFormatting(): bool
    {
        return true;
    }

    public function formatValuesBeforeSave($entry)
    {
        $tripleStore = $this->getService(TripleStore::class);

        $value = $this->getValue($entry);

        // once per entry per request: a second tags field on the same form must not delete what
        // the first one just wrote, and a second entry saved in the same request must still have
        // its own keywords cleared, which the flag this replaces got wrong (ticket 45)
        if (!empty($entry['tag']) && $this->getService(EntryTagsCleared::class)->needsClearing($entry['tag'])) {
            $tripleStore->delete($entry['tag'], 'http://outils-reseaux.org/_vocabulary/tag', null, '', '');
        }

        $tags = explode(',', (string)$value);
        foreach ($tags as $tag) {
            trim($tag);
            if ($tag != '') {
                $tripleStore->create($entry['tag'] ?? '', 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '');
            }
        }

        return [$this->propertyName => $value];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);

        $tags = explode(',', (string)$value);

        if (count($tags) > 0 && !empty($tags[0])) {
            sort($tags);
            $tags = array_map(function ($tag) {
                return '<a class="tag-label label label-info" href="' . $this->getService(UrlFormatter::class)->href('listpages', $this->getService(\YesWiki\Kernel\Service\PageContext::class)->getTag(), 'tags=' . urlencode(trim($tag))) . '" title="' . _t('TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS') . '">' . $tag . '</a>';
            }, $tags);

            return $this->render('@core/fields/tags.twig', [
                'value' => join(' ', $tags) ?? '',
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

    private function loadOptionsFromTags()
    {
        $tripleStore = $this->getService(TripleStore::class);

        $rawOptions = $tripleStore->getMatching(null, 'http://outils-reseaux.org/_vocabulary/tag');
        $this->options = array_map(function ($rawOption) {
            return $rawOption['value'];
        }, $rawOptions);
        $this->options = array_combine($this->options, $this->options);
    }
}
