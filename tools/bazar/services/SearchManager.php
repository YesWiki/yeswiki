<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\CheckboxField;
use YesWiki\Bazar\Field\EnumField;

class SearchManager
{
    public function __construct()
    {
    }

    /**
     * prepare searches.
     *
     * @param array $forms (needed to filter only on concerned forms)
     *
     * @return array ['needle 1'=>[], // when not in list
     *               'needle 2'=>[$result1,$result2]
     *               ,...]  // each $result= [
     *               'propertyName' => 'bf_...',
     *               'key' => 'bf_...',
     *               'isCheckBox' => true,
     *               ]
     */
    public function searchWithLists(string $phrase, array $forms = []): array
    {
        $needles = [];
        // catch "exact text" and rest separated by space
        if (!empty($phrase) && preg_match_all('/^([^" ]+)|(?:")([^"]+)(?:")|([^" ]+)$|(?: )([^" ]+)(?: )/', $phrase, $matches)) {
            // find needles
            foreach ($matches[0] as $key => $match) {
                for ($i = 1; $i < 5; $i++) {
                    if (!empty($matches[$i][$key])) {
                        if (!array_key_exists($matches[$i][$key], $needles)) {
                            $needle = $this->prepareNeedleForRegexp($matches[$i][$key]);
                            $needles[$needle] = [];
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
     * search needles in values (options) of EnumField and return array [['propertyName' => ...,'key'=>$key,'isCheckbox' => true],].
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
                            if (is_array($option)) {
                                $option = implode(' ', $option); // rare cases with arrays, ex: usernames
                            }
                            // mb_strtolower instead of strtolower to manage utf 8 characters
                            if (preg_match('/' . mb_strtolower(preg_quote($needle)) . '/i', mb_strtolower($option), $matches)) {
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

    /**
     * prepare needle by removing accents and define string for regexp.
     */
    private function prepareNeedleForRegexp(string $needle): string
    {
        // be careful to ( and )
        $needle = str_replace(['(', ')', '/'], ['\\(', '\\)', '\\/'], $needle);

        // remove accents
        $needle = str_replace(
            ['à', 'á', 'â', 'ã', 'ä', 'ç', 'è', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'À', 'Á', 'Â', 'Ã', 'Ä', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ù', 'Ú', 'Û', 'Ü', 'Ý'],
            ['a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'a', 'a', 'a', 'a', 'a', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y'],
            $needle
        );

        // add for regexp
        $needle = str_replace(
            [
                'a',
                'c',
                'e',
                'i',
                'n',
                'o',
                'u',
                'y',
            ],
            [
                '(a|à|á|â|ã|ä|A|À|Á|Â|Ã|Ä)',
                '(c|ç|C|Ç)',
                '(e|è|é|ê|ë|E|È|É|Ê|Ë)',
                '(i|ì|í|î|ï|I|Ì|Í|Î|Ï)',
                '(n|ñ|N|Ñ)',
                '(o|ò|ó|ô|õ|ö|O|Ò|Ó|Ô|Õ|Ö)',
                '(u|ù|ú|û|ü|U|Ù|Ú|Û|Ü)',
                '(y|ý|ÿ|Y|Ý)',
            ],
            $needle
        );

        return $needle;
    }
}