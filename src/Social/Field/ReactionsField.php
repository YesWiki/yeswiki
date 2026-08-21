<?php

namespace YesWiki\Social\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Field\BazarField;
use YesWiki\Identity\Service\AuthenticationService;
use YesWiki\Social\Service\ReactionsFormatter;

#[\Field(['reactions'])]
class ReactionsField extends BazarField
{
    protected const FIELD_IDS = 2;
    protected const FIELD_LABELS = 3;
    protected const FIELD_IMAGES = 4;
    protected const FIELD_LABEL_REACTION = 6;

    public const DEFAULT_REACTIONS = [
        'top-gratitude' => [
            'title_t' => 'BAZ_REACTIONS_DEFAULT_GRATITUDE',
            'image' => 'styles/images/mikone-top-gratitude.svg',
        ],
        'j-aime' => [
            'title_t' => 'BAZ_REACTIONS_DEFAULT_I_LOVE',
            'image' => 'styles/images/mikone-j-aime.svg',
        ],
        'j-ai-appris' => [
            'title_t' => 'BAZ_REACTIONS_DEFAULT_I_UNDERSTOOD',
            'image' => 'styles/images/mikone-j-ai-appris.svg',
        ],
        'pas-compris' => [
            'title_t' => 'BAZ_REACTIONS_DEFAULT_NOT_UNDERSTOOD',
            'image' => 'styles/images/mikone-pas-compris.svg',
        ],
        'pas-d-accord' => [
            'title_t' => 'BAZ_REACTIONS_DEFAULT_NOT_AGREE',
            'image' => 'styles/images/mikone-pas-d-accord.svg',
        ],
        'idee-noire' => [
            'title_t' => 'BAZ_REACTIONS_DEFAULT_BLACK_IDEA',
            'image' => 'styles/images/mikone-idee-noire.svg',
        ],
    ];
    public const DEFAULT_OPTIONS = [
        'oui' => 'YES',
        'non' => 'NO',
    ];
    public const DEFAULT_OK_KEY = 'oui';
    public const MAX_REACTIONS = 1;

    protected $ids;
    protected $labels;
    protected $images;
    protected $imagesPath;
    protected $options;
    protected $reactionsFormatter;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->reactionsFormatter = $services->get(ReactionsFormatter::class);
        $this->imagesPath = null;
        $this->options = array_map('_t', self::DEFAULT_OPTIONS);

        if (empty(trim((string)$this->name))) {
            $this->name = 'reactions';
        }

        $this->label = $values[self::FIELD_LABEL_REACTION] ?? '';
        if (empty(trim($this->label))) {
            $this->label = _t('BAZ_ACTIVATE_REACTIONS');
        }

        $this->size = null;
        $this->maxChars = null;

        $this->ids = trim($values[self::FIELD_IDS]);
        $this->ids = empty($this->ids) ? [] : explode(',', $this->ids);
        $this->ids = array_map('trim', $this->ids);

        $labels = isset($values[self::FIELD_LABELS]) && is_string($values[self::FIELD_LABELS])
            ? trim($values[self::FIELD_LABELS])
            : '';

        list('labels' => $this->labels, 'ids' => $this->ids) = $this->reactionsFormatter->formatReactionsLabels(
            $labels,
            empty($this->ids)
                ? (
                    empty($labels)
                    ? array_keys(self::DEFAULT_REACTIONS)
                    : null
                )
                : $this->ids,
            array_map(function ($reactionData) {
                return _t($reactionData['title_t']);
            }, self::DEFAULT_REACTIONS)
        );

        $this->images = isset($values[self::FIELD_IMAGES]) && is_string($values[self::FIELD_IMAGES]) ? trim($values[self::FIELD_IMAGES]) : '';
    }

    protected function renderStatic($entry)
    {
        $currentEntryTag = $this->getCurrentTag($entry);

        if (is_null($currentEntryTag) || $this->getValue($entry) !== self::DEFAULT_OK_KEY) {
            return '';
        }

        $user = $this->getService(AuthenticationService::class)->getLoggedUser();
        $username = empty($user['name']) ? '' : $user['name'];

        $imagesPath = $this->getImagesPath();
        list('reactions' => $reactionItems, 'userReactions' => $userReactions, 'oldIdsUserReactions' => $oldIdsUserReactions) =
            $this->reactionsFormatter->getReactionItems(
                $currentEntryTag,
                $username,
                $this->name,
                $this->ids,
                $this->labels,
                $this->getImagesPath(),
                true
            );

        return $this->render('@core/fields/reactions.twig', [
            'reactionId' => $this->name,
            'reactionItems' => $reactionItems,
            'userName' => $username,
            'userReaction' => $userReactions,
            'oldIdsUserReactions' => $oldIdsUserReactions,
            'maxReaction' => self::MAX_REACTIONS,
            'pageTag' => $currentEntryTag,
            'showCommentMessage' => !empty($entry['bf_commentaires']) && $entry['bf_commentaires'] == 'oui',
        ]);
    }

    public function getImagesPath(): array
    {
        if (is_null($this->imagesPath)) {
            $this->imagesPath = $this->reactionsFormatter->formatImages(
                $this->ids,
                $this->images,
                array_map(function ($reactionsData) {
                    return $reactionsData['image'];
                }, self::DEFAULT_REACTIONS)
            );
        }

        return $this->imagesPath;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function getLabels(): array
    {
        return $this->labels;
    }

    protected function getCurrentTag($entry): ?string
    {
        return !empty($entry['tag']) ? $entry['tag'] : null;
    }

    protected function renderInput($entry)
    {
        return $this->render('@core/inputs/select.twig', [
            'value' => $this->getValue($entry),
            'options' => $this->options,
        ]);
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'ids' => $this->getIds(),
                'labels' => $this->getLabels(),
                'images' => array_map('basename', $this->getImagesPath()),
            ]
        );
    }
}
