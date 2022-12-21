<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Bazar\Field\SelectListField;
use YesWiki\Core\Service\AclService;
use YesWiki\Wiki;

/**
 * @Field({"comments"})
 */
class CommentsField extends SelectListField
{
    public const OPTION_YES = 'oui';
    public const OPTION_NO = 'non';
    public const OPTIONS = [
        'oui' => 'YES',
        'non' => 'NO',
    ];
    protected const FIELD_DEFAULT_RIGHTS = 7;

    protected $aclService;
    protected $defaultRights;
    protected $params;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);
        $this->defaultRights = (isset($values[self::FIELD_DEFAULT_RIGHTS]) && is_string($values[self::FIELD_DEFAULT_RIGHTS]))
            ? trim($values[self::FIELD_DEFAULT_RIGHTS])
            : '+';
        $this->listLabel = $this->name;
        $this->propertyName = $this->name;
        $this->keywords = '';
        $this->queries = '';

        if (empty($this->label) || empty(trim($this->label))) {
            $this->label = _t('BAZ_ACTIVATE_COMMENTS');
        }
        if (empty($this->hint) || empty(trim($this->hint))) {
            $this->hint = _t('BAZ_ACTIVATE_COMMENTS_HINT');
        }

        $this->params = $this->getService(ParameterBagInterface::class);
        $this->aclService = $this->getService(AclService::class);
    }

    public function loadOptionsFromList()
    {
        // force usage of predefined values with translation
        $this->options = array_map('_t', self::OPTIONS);
    }

    public function getLinkedObjectName()
    {
        // there is no linkedObjectName
        return 'CommentsField';
    }

    protected function renderInput($entry)
    {
        return $this->render('@bazar/inputs/comments.twig', [
            'value' => $this->getValue($entry),
            'options' => $this->options,
            'showAlertForCommentsNotActivated' =>
                in_array($this->getCommentsType(), ['','yeswiki']) &&
                $this->params->get('comments_activated') !== true
        ]);
    }

    protected function renderStatic($entry)
    {
        return null;
    }

    protected function getValue($entry)
    {
        $value = parent::getValue($entry);
        return $value == self::OPTION_YES ? self::OPTION_YES : self::OPTION_NO;
    }

    // Format input values before save
    public function formatValuesBeforeSave($entry)
    {
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
    }

    public function getCommentsType(): string
    {
        $commentsType = $this->params->get('comments_handler');

        return (
            empty($commentsType) ||
            !is_string($lmcommentsType)
        )
        ? ''
        : $commentsType;
    }

    public function getDefaultRights(): string
    {
        return $this->defaultRights;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'commentsType' => $this->getCommentsType(),
                'defaultRights' => $this->getDefaultRights()
            ]
        );
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
            $defaultRights = trim($this->getDefaultRights());
            if (empty($defaultRights) || $defaultRights === 'comments-closed') {
                // backup
                $defaultRights = '+';
            } else {
                $defaultRights = str_replace(',', "\n", $defaultRights);
            }
            $this->aclService->save($entry['id_fiche'], 'comment', $defaultRights);
        }
    }
}
