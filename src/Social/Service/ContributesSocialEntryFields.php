<?php

namespace YesWiki\Social\Service;

use YesWiki\Content\Entity\ContributesEntryFields;

/**
 * An entry's comments and reactions, for whatever is rendering it.
 *
 * These four values used to be four methods on `Content`'s `EntryExtraFieldsService`, each
 * reaching into `CommentService` or `ReactionManager`. That is how the entry model came to
 * depend on the social features layered over it; the view asks each module for its part now
 * (ADR-0019).
 */
class ContributesSocialEntryFields implements ContributesEntryFields
{
    public function __construct(
        private readonly CommentService $comments,
        private readonly ReactionManager $reactions,
    ) {
    }

    public function contributedFieldNames(): array
    {
        return ['comments', 'comments_count', 'reactions', 'reactions_count'];
    }

    public function contributedField(string $name, string $entryId): mixed
    {
        return match ($name) {
            'comments' => $this->comments->loadCommentsRecursive($entryId),
            'comments_count' => $this->comments->getCommentsCount($entryId),
            'reactions' => $this->reactions->getReactions($entryId, [], '', true),
            'reactions_count' => $this->countsByReaction($entryId),
            default => null,
        };
    }

    /**
     * `getReactions()`'s shape is awkward in a template, so it is flattened to
     * `bf_my_reaction: { like: { label, image, count } }`.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function countsByReaction(string $entryId): array
    {
        $result = [];
        foreach ($this->reactions->getReactions($entryId, [], '', true) as $field => $data) {
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
}
