<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Core\Service\CommentService;
use YesWiki\Core\Service\ReactionManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Wiki;

// Get more data about an entry
class EntryExtraFieldsService
{
    public const EXTRA_FIELDS = ['comments', 'comments_count', 'reactions', 'reactions_count', 'triples', 'linked_data'];

    protected $wiki;
    protected $entryId;

    public function __construct(Wiki $wiki)
    {
        $this->wiki = $wiki;
    }

    public function setEntryId($entryId)
    {
        $this->entryId = $entryId;
    }

    // get('comments'), get('nb_comments')
    public function get($prop)
    {
        $methodName = 'get' . $this->snakeToPascal($prop);
        if (method_exists($this, $methodName)) {
            return $this->$methodName();
        }

        return null;
    }

    private function snakeToPascal(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));
    }

    public function getReactions()
    {
        return $this->wiki->services->get(ReactionManager::class)->getReactions($this->entryId, [], '', true);
    }

    public function getReactionsCount()
    {
        $reactions = $this->wiki->services->get(ReactionManager::class)->getReactions($this->entryId, [], '', true);
        // The format of getReactions is not easy to use in a template, so we transform it
        // bf_my_reaction: { like: { label: "I like", icon: "..", count: 5 }, dislike: { ... } }
        $result = [];
        foreach ($reactions as $field => $data) {
            $fieldResult = [];
            foreach ($data['parameters']['labels'] as $reactionId => $label) {
                $fieldResult[$reactionId] = ['label' => $label, 'image' => null, 'count' => 0];
            }
            foreach ($data['parameters']['images'] as $reactionId => $image) {
                $fieldResult[$reactionId]['image'] = $image;
            }
            foreach ($data['nb_reactions'] as $reactionId => $count) {
                $fieldResult[$reactionId]['count'] = $count;
            }
            $result[$field] = $fieldResult;
        }

        return $result;
    }

    public function getComments()
    {
        return $this->wiki->services->get(CommentService::class)->loadCommentsRecursive($this->entryId);
    }

    public function getCommentsCount()
    {
        return $this->wiki->services->get(CommentService::class)->getCommentsCount($this->entryId);
    }

    public function getTriples()
    {
        return $this->wiki->services->get(TripleStore::class)->getMatching($this->entryId, null, null, '=');
    }

    public function getLinkedData(): array
    {
        $fields = [];
        $entryManager = $this->wiki->services->get(EntryManager::class);
        $formManager = $this->wiki->services->get(FormManager::class);

        $entry = $entryManager->getOne($this->entryId);
        $entryFields = $formManager->findTypeOfFields($entry['id_typeannonce'], ['SelectEntryField', 'CheckboxEntryField']);
        foreach ($entryFields as $field) {
            $prop = $field->getPropertyName();
            $fields[$prop] = [];
            if (!empty($entry[$prop])) {
                $entries = explode(',', $entry[$prop]);
                if (count($entries) === 1) {
                    $val = array_pop($entries);
                    $fields[$prop][$val] = $entryManager->getOne($entry[$prop]);
                } else {
                    foreach ($entries as $val) {
                        $fields[$prop][$val] = $entryManager->getOne($val);
                    }
                }
            }
        }

        return $fields;
    }

    public function appendHtmlData(array $linkedData): string
    {
        $sep = '_-_';
        $htmlData = '';
        foreach ($linkedData as $fieldName => $entries) {
            foreach ($entries as $entry) {
                $htmlData .= str_replace(
                    'data-',
                    'data-' . $fieldName . $sep . $entry['id_fiche'] . $sep,
                    $entry['html_data']
                ) . ' ';
            }
        }

        return $htmlData;
    }
}
