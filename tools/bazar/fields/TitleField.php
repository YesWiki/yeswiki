<?php

namespace YesWiki\Bazar\Field;

use Psr\Container\ContainerInterface;
use YesWiki\Bazar\Service\FicheManager;

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
        $ficheManager = $this->getService(FicheManager::class);

        // TODO improve import detection
        if (!isset($GLOBALS['_BAZAR_']['provenance']) || $GLOBALS['_BAZAR_']['provenance'] !== 'import') {
            preg_match_all('#{{(.*)}}#U', $value, $matches);
            foreach ($matches[1] as $fieldName) {
                if (isset($entry[$fieldName])) {
                    if (preg_match('#^listefiche#', $fieldName) !== false || preg_match('#^checkboxfiche#', $fieldName) !== false) {
                        // For a "listefiche" or a "checkboxfiche", find the entry's title
                        $fiche = $ficheManager->getOne($entry[$fieldName]);
                        $value = str_replace('{{' . $fieldName . '}}', ($fiche['bf_titre'] != null) ? $fiche['bf_titre'] : '', $value);
                    } elseif (preg_match('#^liste#', $fieldName) !== false || preg_match('#^checkbox#', $fieldName) !== false) {
                        // For a "liste" or a "checkbox", find the list labels
                        $liste = preg_replace('#^(liste|checkbox)(.*)#', '$2', $fieldName);
                        $listValues = baz_valeurs_liste($liste);
                        $list = explode(',', $entry[$fieldName]);
                        $listLabels = [];
                        foreach ($list as $l) {
                            $listLabels[] = $listValues['label'][$l];
                        }
                        $value = str_replace('{{' . $fieldName . '}}', implode(', ', $listLabels), $value);
                    } else {
                        $value = str_replace('{{' . $fieldName . '}}', $entry[$fieldName], $value);
                    }
                }
            }
        }

        // Generate an ID for the entry based on the title
        $entry['id_fiche'] = (isset($entry['id_fiche']) ? $entry['id_fiche'] : genere_nom_wiki($value));
        
        return [$this->propertyName => $value, 'id_fiche' => $entry['id_fiche']];
    }

    public function renderStatic($entry)
    {
        return $this->render("@bazar/fields/title.twig", [
            'value' => $this->getValue($entry)
        ]);
    }
}
