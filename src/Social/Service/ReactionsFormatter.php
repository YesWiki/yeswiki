<?php

namespace YesWiki\Social\Service;

use YesWiki\Content\Service\FileManager;
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
        private readonly FileManager $fileManager,
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
     * The image URL, emoji or sprite name each reaction is drawn with.
     *
     * @param array<array-key, string> $ids
     * @param array<array-key, string> $defaultImages
     *
     * @return array<array-key, string>
     */
    public function formatImages(array $ids, string $imagesComaSeparated, array $defaultImages = []): array
    {
        $rawImages = empty($imagesComaSeparated) ? [] : array_map('trim', explode(',', $imagesComaSeparated));
        $images = [];
        foreach ($ids as $k => $id) {
            $images[$k] = empty($rawImages[$k])
                ? $this->defaultImage($defaultImages[$id] ?? $defaultImages[$k] ?? '')
                : $this->resolveImage($rawImages[$k]);
        }

        return $images;
    }

    private function defaultImage(string $image): string
    {
        if ($image === '') {
            return '';
        }

        return $this->programFiles->exists($image) ? $this->urlFormatter->getBaseUrl() . '/' . $image : $image;
    }

    private function resolveImage(string $raw): string
    {
        if (basename($raw) !== $raw) {
            return '';
        }
        $baseUrl = $this->urlFormatter->getBaseUrl();
        if (preg_match('/\\.(gif|jpeg|png|jpg|svg|webp)$/i', $raw)) {
            foreach (['custom/images', 'files'] as $dir) {
                if ($this->storage->exists("$dir/$raw")) {
                    return "$baseUrl/$dir/$raw";
                }
            }

            return file_exists(YESWIKI_PROGRAM_DIR . "/styles/images/$raw") ? "$baseUrl/styles/images/$raw" : '';
        }
        if (file_exists(YESWIKI_PROGRAM_DIR . "/styles/images/mikone-$raw.svg")) {
            return "$baseUrl/styles/images/mikone-$raw.svg";
        }
        if ($this->fileManager->getOne($raw) !== null) {
            return $this->urlFormatter->href('', 'api/files/' . rawurlencode($raw) . '/download', ['width' => 128, 'height' => 128], false);
        }

        return $raw;
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
