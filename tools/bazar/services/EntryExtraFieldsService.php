<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Core\Service\CommentService;
use YesWiki\Core\Service\ReactionManager;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Wiki;

// Get more data about an entry
class EntryExtraFieldsService
{
    public const EXTRA_FIELDS = ['comments', 'comments_count', 'reactions', 'reactions_count', 'triples'];

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
}
