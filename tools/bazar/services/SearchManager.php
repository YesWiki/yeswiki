<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Field\EnumField;
use YesWiki\Bazar\Service\FormManager;

class SearchManager
{
    public function __construct()
    {
    }

    /**
     * prepare searches
     * @param string $phrase
     * @param array $forms (needed to filter only on concerned forms)
     * @return array ['needle 1'=>[], // when not in list
     *     'needle 2'=>[$result1,$result2]
     *     ,...]  // each $result= [
     *                             'propertyName' => 'bf_...',
     *                             'key' => 'bf_...',
     *                             'isCheckBox' => true,
     *                             ]
     */
    public function searchWithLists(string $phrase, array $forms = []):array
    {
        $needles = [];
        // catch "exact text" and rest separated by space
        if (!empty($phrase) && preg_match_all('/^([^" ]+)|(?:")([^"]+)(?:")|([^" ]+)$|(?: )([^" ]+)(?: )/', $phrase, $matches)) {
            // find needles
            foreach ($matches[0] as $key => $match) {
                for ($i=1; $i < 5; $i++) {
                    if (!empty($matches[$i][$key])) {
                        if (!array_key_exists($matches[$i][$key], $needles)) {
                            $needles[$matches[$i][$key]] = [];
                        }
                    }
                }
            }
            
            // find needle in lists
            // search in list values
            foreach ($forms as $form) {
                foreach ($this->searchInFormOptions($needles, $form) as $result) {
                    $needle = $result['needle'];
                    if (array_key_exists($needle, $needles)) {
                        array_push($needles[$needle], $result);
                    } else {
                        $needles[$needle] = [$result];
                    }
                }
            }
        }
        return $needles;
    }

    
    /**
     * search needles in values (options) of EnumField and return array [['propertyName' => ...,'key'=>$key,'isCheckbox' => true],]
     * @param array $needles
     * @param array $form
     * @return array
     */
    private function searchInFormOptions(array $needles, array $form): array
    {
        $results = [];
        foreach ($form['prepared'] as $field) {
            if ($field instanceof EnumField) {
                $options = $field->getOptions();
                if (is_array($options)) {
                    foreach ($options as $key => $option) {
                        foreach ($needles as $needle => $values) {
                            // mb_strtolower instead of strtolower to manage utf 8 characters
                            if (strpos(mb_strtolower($option), mb_strtolower($needle)) !== false) {
                                $results[] = [
                                    'propertyName' => $field->getPropertyName(),
                                    'key' => $key,
                                    'isCheckBox' => ($field instanceof CheckboxField),
                                    'needle' => $needle,
                                ];
                            }
                        }
                    }
                }
            }
        }
        return $results;
    }
}
