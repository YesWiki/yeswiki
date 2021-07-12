<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\FormManager;

/**
 * Generate a title based on other values from the entry
 * titre***{{bf_nom}} - {{bf_prenom}} - {{listeListeOuiNon}} - {{checkboxListePartenaires}}***
 *
 * @Field({"titre"})
 */
class TitleField extends BazarField
{
    protected $titleTemplate;

    protected const FIELD_TITLE_TEMPLATE = 1;

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->propertyName = 'bf_titre';
        $this->titleTemplate = $values[self::FIELD_TITLE_TEMPLATE];
    }

    protected function renderInput($entry)
    {
        return $this->render("@bazar/inputs/title.twig", [
            'titleTemplate' => $this->titleTemplate
        ]);
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);
        $formManager = $this->getService(FormManager::class);

        // TODO improve import detection
        if (!isset($GLOBALS['_BAZAR_']['provenance']) || $GLOBALS['_BAZAR_']['provenance'] !== 'import') {
            preg_match_all('#{{(.*)}}#U', $value, $matches);
            $formId = $entry['id_typeannonce'] ?? null;
            foreach ($matches[1] as $fieldName) {
                $field = $formManager->findFieldFromNameOrPropertyName($fieldName,$formId);
                if ($field instanceof EnumField || $field instanceof FileField){
                    $fieldValue = $field->getValue($entry);
                    if ($field instanceof EnumField){
                        // get value instead of key
                        $replacement = $field->getOptions()[$fieldValue] ?? '';
                    } else {
                        $replacement = $fieldValue;
                    }
                    $value = str_replace('{{' . $fieldName . '}}', $replacement, $value);
                } elseif (isset($entry[$fieldName])) {
                    $value = str_replace('{{' . $fieldName . '}}', $entry[$fieldName], $value);
                }
            }
        }

        // Generate an ID for the entry based on the title
        $entry['id_fiche'] = (isset($entry['id_fiche']) ? $entry['id_fiche'] : genere_nom_wiki($value));
        
        return [$this->propertyName => $value, 'id_fiche' => $entry['id_fiche']];
    }

    protected function renderStatic($entry)
    {
        return $this->render("@bazar/fields/title.twig", [
            'value' => $this->getValue($entry)
        ]);
    }
}
