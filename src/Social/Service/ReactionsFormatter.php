<?php

namespace YesWiki\Social\Service;

use YesWiki\Core\YesWikiController;
use YesWiki\Files\Service\ProgramFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Service\UrlFormatter;

class ReactionsFormatter extends YesWikiController
{
    protected ReactionManager $reactionManager;

    protected UrlFormatter $urlFormatter;

    public function __construct(
        ReactionManager $reactionManager,
        UrlFormatter $urlFormatter,
        private readonly Storage $storage,
        private readonly ProgramFiles $programFiles,
    ) {
        $this->urlFormatter = $urlFormatter;
        $this->reactionManager = $reactionManager;
    }

    /**
     * format Reactions Labels.
     *
     * @param array<array-key, string>|null $ids           the reaction ids to label, or null to derive
     *                                                     them from the labels themselves
     * @param array<array-key, string>      $defaultLabels label to fall back to, per reaction id
     *
     * @return array{labels: array<array-key, string>, ids: array<array-key, string>}
     */
    public function formatReactionsLabels(string $labelsComaSeparated, ?array $ids = null, array $defaultLabels = []): array
    {
        $rawLabels = empty($labelsComaSeparated) ? [] : array_map('trim', explode(',', $labelsComaSeparated));
        if (is_null($ids)) {
            $labels = $rawLabels;
            $ids = array_map('URLify::slug', $labels);
        } else {
            $ids = array_map('URLify::slug', $ids);
            $labels = [];
            foreach ($ids as $k => $id) {
                $labels[$k] = (!empty($rawLabels[$k]))
                    ? $rawLabels[$k]
                    : (
                        (array_key_exists($id, $defaultLabels))
                        ? $defaultLabels[$id]
                        : $id
                    );
            }
        }

        return compact(['labels', 'ids']);
    }

    /**
     * format Reactions Labels.
     *
     * @param array<array-key, string> $ids           the reaction ids to find an image for
     * @param array<array-key, string> $defaultImages image path to fall back to, per reaction id
     *
     * @return array<array-key, string> image URL per reaction id
     */
    public function formatImages(array $ids, string $imagesComaSeparated, array $defaultImages = []): array
    {
        $rawImages = empty($imagesComaSeparated) ? [] : array_map('trim', explode(',', $imagesComaSeparated));
        $images = [];
        foreach ($ids as $k => $id) {
            $sanitizedImageFilename = empty($rawImages[$k]) ? '' : basename($rawImages[$k]);
            $baseUrl = $this->urlFormatter->getBaseUrl();
            $images[$k] = empty($rawImages[$k])
                ? (
                    (array_key_exists($id, $defaultImages))
                    ? (
                        $this->programFiles->exists($defaultImages[$id])
                        ? "$baseUrl/{$defaultImages[$id]}"
                        : $defaultImages[$id]
                    )
                    : (
                        (array_key_exists($k, $defaultImages))
                        ? (
                            $this->programFiles->exists($defaultImages[$k])
                            ? "$baseUrl/{$defaultImages[$k]}"
                            : $defaultImages[$k]
                        )
                        : ''
                    )
                )
                : (
                    basename($rawImages[$k]) !== $rawImages[$k]
                    ? ''
                    : (
                        (preg_match('/\\.(gif|jpeg|png|jpg|svg|webp)$/i', $rawImages[$k]))
                        ? (
                            $this->storage->exists("custom/images/{$rawImages[$k]}")
                            ? "$baseUrl/custom/images/{$rawImages[$k]}"
                            : (
                                $this->storage->exists("files/{$rawImages[$k]}")
                                ? "$baseUrl/files/{$rawImages[$k]}"
                                : (
                                    file_exists(YESWIKI_PROGRAM_DIR . "/styles/images/{$rawImages[$k]}")
                                    ? "$baseUrl/styles/images/{$rawImages[$k]}"
                                    : ''
                                )
                            )
                        )
                        : (
                            file_exists(YESWIKI_PROGRAM_DIR . "/styles/images/mikone-{$rawImages[$k]}.svg")
                            ? "$baseUrl/styles/mikone-{$rawImages[$k]}.svg"
                            : $rawImages[$k]
                        )
                    )
                );
        }

        return $images;
    }

    /**
     * @param array<array-key, string> $ids    the reaction ids offered by this reaction field
     * @param array<array-key, string> $labels label per reaction, in the same order as $ids
     * @param array<array-key, string> $images image URL per reaction, in the same order as $ids
     *
     * @return array{
     *     reactions: array<string, array{id: string, label: string, image: string, nbReactions: int}>,
     *     userReactions: list<string>,
     *     oldIdsUserReactions: list<string>
     * }
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
