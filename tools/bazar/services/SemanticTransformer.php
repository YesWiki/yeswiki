<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Bazar\Field\BazarField;

class SemanticTransformer
{
    public function convertToSemanticData($form, $data, $isHtmlFormatted = false)
    {
        if (!$form['bn_sem_type']) {
            throw new \Exception(_t('BAZAR_SEMANTIC_TYPE_MISSING'));
        }

        // If context is a JSON decode it, otherwise use the string
        $semanticData['@context'] = (array)json_decode($form['bn_sem_context']) ?: $form['bn_sem_context'];

        // If we have multiple types split by comma, generate an array, otherwise use a string
        $semanticData['@type'] = strpos($form['bn_sem_type'], ',')
            ? array_map(function ($str) {
                return trim($str);
            }, explode(',', $form['bn_sem_type']))
            : $form['bn_sem_type'];

        // Add the ID of the Bazar object
        $semanticData['@id'] = $GLOBALS['wiki']->href('', $data['id_fiche']);

        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $fieldPropName = $field->getPropertyName();
                $fieldSemanticPredicate = $field->getSemanticPredicate();
                $fieldType = $field->getType();
            }

            // If the file is not semantically defined, ignore it
            if ($fieldSemanticPredicate && isset($data[$fieldPropName])) {
                $value = $data[$fieldPropName];
                if ($value) {
                    // We don't want this additional formatting if we are already dealing with HTML-formatted data
                    if (!$isHtmlFormatted) {
                        // If this is a file or image, add the base URL
                        if ($fieldType === 'file') {
                            $value = $GLOBALS['wiki']->getBaseUrl() . '/' . BAZ_CHEMIN_UPLOAD . $value;
                        }

                        // If this is a linked entity (listefiche), use the URL
                        if (startsWith($fieldPropName, 'listefiche')) {
                            $value = $GLOBALS['wiki']->href('', $value);
                        }
                    }

                    if (is_array($fieldSemanticPredicate)) {
                        // If we have multiple fields, duplicate the data
                        foreach ($fieldSemanticPredicate as $sem_type) {
                            $semanticData[$sem_type] = $value;
                        }
                    } else {
                        $semanticData[$fieldSemanticPredicate] = $value;
                    }
                }
            }
        }

        return $semanticData;
    }

    public function convertFromSemanticData($formId, $data)
    {
        $form = baz_valeurs_formulaire($formId);

        // Initialize by copying basic information
        $nonSemanticData = [
            'id_fiche' => $data['id_fiche'],
            'antispam' => $data['antispam'],
            'id_typeannonce' => $data['id_typeannonce'],
        ];

        if (($data['@type'] && $data['@type'] !== $form['bn_sem_type']) || $data['type'] && $data['type'] !== $form['bn_sem_type']) {
            throw new \Exception('The @type of the sent data must be ' . $form['bn_sem_type']);
        }

        foreach ($form['prepared'] as $field) {
            if ($field instanceof BazarField) {
                $fieldPropName = $field->getPropertyName();
                $fieldSemanticPredicate = $field->getSemanticPredicate();
                $fieldType = $field->getType();
            }

            // If the file is not semantically defined, ignore it
            if ($fieldSemanticPredicate && $data[$fieldSemanticPredicate]) {
                // TODO Handle this inside the Field classes ?
                if ($fieldType === 'date') {
                    $date = new \DateTime($data[$fieldSemanticPredicate]);
                    $nonSemanticData[$fieldPropName] = $date->format('Y-m-d');
                    $nonSemanticData[$fieldPropName . '_allday'] = 0;
                    $nonSemanticData[$fieldPropName . '_hour'] = $date->format('H');
                    $nonSemanticData[$fieldPropName . '_minutes'] = $date->format('i');
                } elseif ($fieldType === 'image') {
                    $nonSemanticData['image' . $fieldPropName] = $data[$fieldSemanticPredicate];
                } else {
                    $nonSemanticData[$fieldPropName] = $data[$fieldSemanticPredicate];
                }
            }
        }

        return $nonSemanticData;
    }
}
