<?php

namespace YesWiki\Core\Controller;

use YesWiki\Core\Service\ReactionManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Wiki;

class ReactionsController extends YesWikiController
{
    protected $reactionManager;

    public function __construct(
        ReactionManager $reactionManager,
        Wiki $wiki
    ) {
        $this->reactionManager = $reactionManager;
        $this->wiki = $wiki;
    }

    /**
     * format Reactions Labels.
     *
     * @return array ['labels'=>string[],'ids'=>string[]]]
     */
    public function formatReactionsLabels(string $labelsComaSeparated, ?array $ids = null, array $defaultLabels = []): array
    {
        $rawLabels = empty($labelsComaSeparated) ? [] : array_map('trim', explode(',', $labelsComaSeparated));
        if (is_null($ids)) {
            $labels = $rawLabels;
            $ids = array_map('URLify::slug', $labels);
        } else {
            // security to prevent badly formatted ids
            $ids = array_map('URLify::slug', $ids);
            $labels = [];
            foreach ($ids as $k => $id) {
                $labels[$k] = (!empty($rawLabels[$k]))
                    ? $rawLabels[$k]
                    : (
                        // if ids are default ones, we have some titles
                        (array_key_exists($id, $defaultLabels))
                        ? $defaultLabels[$id]
                        : $id  // we show just the id, as it's our only information available
                    );
            }
        }

        return compact(['labels', 'ids']);
    }

    /**
     * format Reactions Labels.
     *
     * @return string[]
     */
    public function formatImages(array $ids, string $imagesComaSeparated, array $defaultImages = []): array
    {
        $rawImages = empty($imagesComaSeparated) ? [] : array_map('trim', explode(',', $imagesComaSeparated));
        $images = [];
        foreach ($ids as $k => $id) {
            $sanitizedImageFilename = empty($rawImages[$k]) ? '' : basename($rawImages[$k]);
            $baseUrl = $this->wiki->getBaseUrl();
            $images[$k] = empty($rawImages[$k]) // if ids are default ones, we have some images
                ? (
                    (array_key_exists($id, $defaultImages))
                    ? (
                        file_exists($defaultImages[$id])
                        ? "$baseUrl/{$defaultImages[$id]}"
                        : $defaultImages[$id]
                    )
                    : (
                        (array_key_exists($k, $defaultImages))
                        ? (
                            file_exists($defaultImages[$k])
                            ? "$baseUrl/{$defaultImages[$k]}"
                            : $defaultImages[$k]
                        )
                        : ''
                    )
                )
                : (
                    basename($rawImages[$k]) !== $rawImages[$k]
                    ? '' // error
                    : (
                        (preg_match('/\\.(gif|jpeg|png|jpg|svg|webp)$/i', $rawImages[$k]))
                        ? (
                            file_exists("custom/images/{$rawImages[$k]}")
                            ? "$baseUrl/custom/images/{$rawImages[$k]}"
                            : (
                                file_exists("files/{$rawImages[$k]}")
                                ? "$baseUrl/files/{$rawImages[$k]}"
                                : (
                                    file_exists("styles/images/{$rawImages[$k]}")
                                    ? "$baseUrl/styles/images/{$rawImages[$k]}"
                                    : '' // error
                                )
                            )
                        )
                        : (
                            file_exists("styles/images/mikone-{$rawImages[$k]}.svg")
                            ? "$baseUrl/styles/mikone-{$rawImages[$k]}.svg"
                            : $rawImages[$k]
                        )
                    )
                );
        }

        return $images;
    }

    /**
     * @param bool $isDefaultReactionFied = false
     *
     * @return array [
     *               'reactions' => [
     *               (string $id) => [
     *               'id'=>string,
     *               'label'=>string,
     *               'image'=>string,
     *               'nbReactions'=>integer
     *               ]
     *               ],
     *               'userReactions' = >string[] $ids
     *               'oldIdsUserReactions' = >string[] $ids
     *               ]
     */
    public function getReactionItems(string $pageTag, string $userName, string $reactionId, array $ids, array $labels, array $images, bool $isDefaultReactionFied = false): array
    {
        $reactions = [];
        $userReactions = [];
        $oldIdsUserReactions = [];
        $uniqueIds = ["$reactionId|$pageTag"];
        if ($isDefaultReactionFied) {
            $uniqueIds['oldId'] = "reactionField|$pageTag";
        }
        foreach ($ids as $k => $id) {
            $reactions[$id] = [
                'id' => $id,
                'label' => $labels[$k] ?? '',
                'image' => $images[$k] ?? '',
                'nbReactions' => 0,
            ];
        }
        $allReactions = $this->reactionManager->getReactions($pageTag, [$reactionId]);
        foreach ($uniqueIds as $k => $uniqueId) {
            if (!empty($allReactions[$uniqueId]['reactions'])) {
                foreach ($allReactions[$uniqueId]['reactions'] as $reaction) {
                    if (isset($reactions[$reaction['id']])) {
                        $reactions[$reaction['id']]['nbReactions'] = $reactions[$reaction['id']]['nbReactions'] + 1;
                        if (!empty($userName) && $reaction['user'] === $userName && !in_array($reaction['id'], $userReactions)) {
                            $userReactions[] = $reaction['id'];
                            if ($k === 'oldId') {
                                $oldIdsUserReactions[] = $reaction['id'];
                            }
                        }
                    }
                }
            }
        }

        return compact(['reactions', 'userReactions', 'oldIdsUserReactions']);
    }
}
