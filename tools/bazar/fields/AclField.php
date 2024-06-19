<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Service\AclService;

/**
 * @Field({"acls"})
 */
class AclField extends BazarField
{
    protected $aclService;
    protected $askIfActivateComments;
    protected $entryReadRight;
    protected $entryWriteRight;
    protected $entryCommentRight;
    protected $params;
    protected $options;

    public const OPTIONS = [
        'oui' => 'YES',
        'non' => 'NO',
    ];
    public const OPTION_YES = 'oui';
    public const OPTION_NO = 'non';

    protected const FIELD_ENTRY_READ_RIGHT = 1;
    protected const FIELD_ENTRY_WRITE_RIGHT = 2;
    protected const FIELD_ENTRY_COMMENT_RIGHT = 3;
    protected const FIELD_LABEL = 4;
    protected const FIELD_NAME = 6;
    protected const FIELD_ASK_IF_ACTIVATE_COMMENTS = 7;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->askIfActivateComments = in_array($this->filterNotEmptyString($values, self::FIELD_ASK_IF_ACTIVATE_COMMENTS, 'no'), [1, true, '1', 'true', 'yes'], true);

        $this->name = $this->filterNotEmptyString($values, self::FIELD_NAME, $this->askIfActivateComments ? 'bf_commentaires' : null);
        $this->propertyName = $this->name;

        $this->default = $this->filterNotEmptyString(['default' => $this->default], 'default', '');
        $this->default = ($this->default === '' || in_array($this->default, array_keys(self::OPTIONS), true)) ? $this->default : '';

        $this->label = $this->filterNotEmptyString($values, self::FIELD_LABEL, _t('BAZ_ACTIVATE_COMMENTS'));
        $this->hint = $this->filterNotEmptyString(['hint' => $this->hint], 'hint', _t('BAZ_ACTIVATE_COMMENTS_HINT'));

        $this->entryReadRight = $this->filterNotEmptyString($values, self::FIELD_ENTRY_READ_RIGHT, '*');
        $this->entryWriteRight = $this->filterNotEmptyString($values, self::FIELD_ENTRY_WRITE_RIGHT, '%');
        $this->entryCommentRight = $this->filterNotEmptyString($values, self::FIELD_ENTRY_COMMENT_RIGHT, 'comments-closed');

        $this->aclService = $this->getService(AclService::class);
        $this->params = $this->getService(ParameterBagInterface::class);

        $this->options = null;
    }

    protected function filterNotEmptyString(array $data, string $key, ?string $default): ?string
    {
        return (
            isset($data[$key]) &&
            is_string($data[$key]) &&
            !empty(trim($data[$key]))
        )
            ? trim($data[$key])
            : $default;
    }

    protected function renderInput($entry)
    {
        $commentsAlreadyClosed = false;
        $isYesWikiType = in_array($this->getCommentsType(), ['', 'yeswiki']);
        if ($isYesWikiType && !empty($entry['id_fiche'])) {
            $currentCommentAcl = $this->aclService->load($entry['id_fiche'], 'comment', false);
            $commentsAlreadyClosed = (!empty($currentCommentAcl['list']) && $currentCommentAcl['list'] == 'comments-closed');
        }

        return ($this->askIfActivateComments)
            ? $this->render('@bazar/inputs/comments.twig', [
                'value' => $commentsAlreadyClosed ? self::OPTION_NO : $this->getValue($entry),
                'options' => $this->getOptions(),
                'showAlertForCommentsNotActivated' => $isYesWikiType &&
                    $this->params->get('comments_activated') !== true,
            ])
            : '';
    }

    public function formatValuesBeforeSave($entry)
    {
        if (empty($this->aclService->load($entry['id_fiche'], 'read', false)['list'])) {
            $this->aclService->save($entry['id_fiche'], 'read', $this->replaceWithCreator($this->entryReadRight, $entry));
        }
        if (empty($this->aclService->load($entry['id_fiche'], 'write', false)['list'])) {
            $this->aclService->save($entry['id_fiche'], 'write', $this->replaceWithCreator($this->entryWriteRight, $entry));
        }

        if ($this->askIfActivateComments) {
            $commentsType = $this->getCommentsType();
            switch ($commentsType) {
                case 'yeswiki':
                case '':
                    if ($this->getValue($entry) === self::OPTION_YES) {
                        $this->openComments($entry);
                        break;
                    }
                    // no break
                case 'external_humhub':
                case 'embedded_humhub':
                case 'discourse':
                default:
                    $this->closeComments($entry);
                    break;
            }

            return parent::formatValuesBeforeSave($entry);
        } else {
            if (empty($this->aclService->load($entry['id_fiche'], 'comment', false)['list'])) {
                $this->aclService->save($entry['id_fiche'], 'comment', $this->replaceWithCreator($this->entryCommentRight, $entry));
            }

            return (!empty($this->propertyName))
            ? [
                'fields-to-remove' => [
                    $this->propertyName,
                ],
            ]
            : [];
        }
    }

    protected function renderStatic($entry)
    {
        return '';
    }

    protected function getValue($entry)
    {
        $value = parent::getValue($entry);

        return in_array($value, array_keys(self::OPTIONS), true) ? $value : '';
    }

    private function replaceWithCreator($right, $entry)
    {
        // le signe # ou le mot user indiquent que le owner de la fiche sera utilisé pour les droits
        if ($right === 'user' or $right === '#') {
            return $entry['nomwiki'];
        }

        return $right;
    }

    public function getCommentsType(): string
    {
        $commentsType = $this->params->get('comments_handler');

        return (
            empty($commentsType) ||
            !is_string($commentsType)
        )
        ? ''
        : $commentsType;
    }

    public function getOptions()
    {
        if (is_null($this->options)) {
            // force usage of predefined values with translation
            $this->options = array_map('_t', self::OPTIONS);
        }

        return $this->options;
    }

    public function getAskIfActivateComments()
    {
        return $this->askIfActivateComments;
    }

    protected function closeComments($entry)
    {
        if (!empty($entry['id_fiche']) &&
                is_string($entry['id_fiche']) &&
                !empty(trim($entry['id_fiche']))) {
            $this->aclService->save($entry['id_fiche'], 'comment', 'comments-closed');
        }
    }

    protected function openComments($entry)
    {
        if (!empty($entry['id_fiche']) &&
                is_string($entry['id_fiche']) &&
                !empty(trim($entry['id_fiche']))) {
            $defaultRights = (empty($this->entryCommentRight) || $this->entryCommentRight === 'comments-closed')
                    ? '+' //backup
                    : $this->entryCommentRight;
            $this->aclService->save($entry['id_fiche'], 'comment', $defaultRights);
        }
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return [
            'type' => $this->getType(),
            'entryReadRight' => $this->entryReadRight,
            'entryWriteRight' => $this->entryWriteRight,
            'entryCommentRight' => $this->entryCommentRight,
            'name' => $this->getName(),
            'id' => $this->getPropertyName(),
            'propertyName' => $this->getPropertyName(),
            'label' => $this->getLabel(),
            'hint' => $this->getHint(),
            'default' => $this->getDefault(),
            'askIfActivateComments' => $this->getAskIfActivateComments(),
        ];
    }
}
