<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Core\Service\HtmlPurifierService;

/**
 * @Field({"bookmarklet"})
 */
class BookmarkletField extends BazarField
{
    protected $urlField;
    protected $descriptionField;
    protected $text;

    protected const FIELD_URL_FIELD = 3;
    protected const FIELD_DESCRIPTION_FIELD = 4;
    protected const FIELD_TEXT_FIELD = 5;
    private const FIELD_CLASS_TYPE = 'BookmarkletField';

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->urlField = $values[self::FIELD_URL_FIELD] ?? 'bf_url';
        $this->descriptionField = $values[self::FIELD_DESCRIPTION_FIELD] ?? 'bf_description';
        $this->size = null;
        $this->maxChars = null;
        $this->default = '';
        $this->text = $this->services->get(HtmlPurifierService::class)->cleanHTML($values[self::FIELD_TEXT_FIELD] ?? '');
    }

    protected function renderInput($entry)
    {
        $wiki = $this->getWiki();
        if ($this->getWiki()->GetMethod() != 'bazariframe') {
            return $this->render('@bazar/inputs/bookmarklet.twig', [
                'urlParams' => [
                    'vue' => BAZ_VOIR_SAISIR,
                    'action' => BAZ_ACTION_NOUVEAU,
                    'id' => $entry['id_typeannonce'] ?? (function () {
                        $id = $this->getRequest()->query->get('id');
                        return (!empty($id) && is_scalar($id) && strval($id) == strval(intval($id))) ? strval($id) : '';
                    })(),
                ],
            ]);
        }
    }

    protected function renderStatic($entry)
    {
        if ($this->getWiki()->GetMethod() == 'bazariframe') {
            return '<a class="btn btn-danger pull-right" href="javascript:window.close();"><i class="fa fa-remove icon-remove icon-white"></i>&nbsp;' . _t('BAZ_CLOSE_THIS_WINDOW') . '</a>';
        }
    }

    // GETTERS. Needed to use them in the Twig syntax

    public function getUrlField()
    {
        return $this->urlField;
    }

    public function getDescriptionField()
    {
        return $this->descriptionField;
    }

    public function getText()
    {
        return $this->text;
    }

    public static function mapToFieldArray($fieldProps): array
    {
        $new = parent::mapToFieldArray($fieldProps);
        $new[self::FIELD_URL_FIELD] = $fieldProps['urlField'];
        $new[self::FIELD_DESCRIPTION_FIELD] = $fieldProps['descriptionField'];
        $new[self::FIELD_TEXT_FIELD] = $fieldProps['text'];
        ksort($new);
        return $new;
    }



    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'field_type' => self::FIELD_CLASS_TYPE,
                'urlField' => $this->getUrlField(),
                'descriptionField' => $this->getDescriptionField(),
                'text' => $this->getText(),
            ]
        );
    }
}
