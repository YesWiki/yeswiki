<?php

namespace YesWiki\Content\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;

#[\Field(['tags'])]
class TagsField extends EnumField
{
    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        // A tags field is an Enum whose name historically sat in the linked-object slot,
        // because the CSV template had nowhere else to put it. A field object that names
        // itself outright -- every JSON template since ticket 26, the Page form's
        // `keywords` among them -- is taken at its word instead; without this, declaring
        // a tags field with a `name` produced an input with no name at all, so its value
        // was never posted.
        if (!empty($this->linkedObjectName)) {
            $this->name = $this->linkedObjectName;
        }
        $this->maxChars = $this->maxChars ?? 255;
        $this->propertyName = $this->name;
    }

    public function getValueStructure() // See BazarField::getValueStructure
    {
        return [$this->propertyName => ['_mode_' => 'multiple', '_type_' => 'string']];
    }

    /**
     * Keywords reach this field in two shapes: a comma-separated string on a bazar entry,
     * whose tags field is an ordinary form field the webmaster named, and a list on a page,
     * where they are `body.keywords` (ticket 09). Flattening here is what lets the rest of
     * the field -- and everything that reads a field's value -- see one shape.
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
            // the widget live-searches the keyword vocabulary rather than being handed it
            'tagsSearchUrl' => $this->getService(UrlFormatter::class)->href('', 'api/tags'),
        ]);
    }

    /**
     * The keyword index is keyed by the Content's tag, so the tag has to exist before this
     * field can write it. Without this, creating a Content wrote every keyword against an
     * empty resource -- junk rows in `triples`, and keywords that stayed unindexed until
     * the next edit, which is the first time the tag was already there.
     */
    public function requiresTagBeforeFormatting(): bool
    {
        return true;
    }

    public function formatValuesBeforeSave($entry)
    {
        // TODO use TagsManager instead of TripleStore
        $tripleStore = $this->getService(TripleStore::class);

        $value = $this->getValue($entry);

        // Delete existing tags linked to this entry
        if (!isset($GLOBALS['delete_tags']) && !empty($entry['tag'])) {
            $tripleStore->delete($entry['tag'], 'http://outils-reseaux.org/_vocabulary/tag', null, '', '');
            $GLOBALS['delete_tags'] = true;
        }

        // Add back all specified tags
        $tags = explode(',', (string)$value);
        foreach ($tags as $tag) {
            trim($tag);
            if ($tag != '') {
                $tripleStore->create($entry['tag'], 'http://outils-reseaux.org/_vocabulary/tag', $tag, '', '');
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
                return '<a class="tag-label label label-info" href="' . $GLOBALS['yeswikiServices']->get(UrlFormatter::class)->href('listpages', $GLOBALS['yeswikiServices']->get(\YesWiki\Kernel\Service\PageContext::class)->getTag(), 'tags=' . urlencode(trim($tag))) . '" title="' . _t('TAGS_SEE_ALL_PAGES_WITH_THIS_TAGS') . '">' . $tag . '</a>';
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
        // TODO use TagsManager instead of TripleStore
        $tripleStore = $this->getService(TripleStore::class);

        $rawOptions = $tripleStore->getMatching(null, 'http://outils-reseaux.org/_vocabulary/tag');
        $this->options = array_map(function ($rawOption) {
            return $rawOption['value'];
        }, $rawOptions);
        $this->options = array_combine($this->options, $this->options);
    }
}
