<?php

namespace YesWiki\Bazar\Service;

class SemanticTransformer
{
    public function convertToSemanticData($formId, $data, $isHtmlFormatted = false)
    {
        $form = baz_valeurs_formulaire($formId);
        if (!$form['bn_sem_type']) {
            throw new \Exception(_t('BAZAR_SEMANTIC_TYPE_MISSING'));
        }

        // If context is a JSON decode it, otherwise use the string
        $semanticData['@context'] = (array) json_decode($form['bn_sem_context']) ?: $form['bn_sem_context'];

        // If we have multiple types split by comma, generate an array, otherwise use a string
        $semanticData['@type'] = strpos($form['bn_sem_type'], ',')
            ? array_map(function ($str) {
                return trim($str);
            }, explode(',', $form['bn_sem_type']))
            : $form['bn_sem_type'];

        // Add the ID of the Bazar object
        $semanticData['@id'] = $GLOBALS['wiki']->href('', $data['id_fiche']);

        $fields_infos = bazPrepareFormData($form);
        foreach ($fields_infos as $field_info) {
            // If the file is not semantically defined, ignore it
            if ($field_info['sem_type']) {
                $value = $data[$field_info['id']];
                if ($value) {
                    // We don't want this additional formatting if we are already dealing with HTML-formatted data
                    if (!$isHtmlFormatted) {
                        // If this is a file or image, add the base URL
                        if ($field_info['type'] === 'file') {
                            $value = $GLOBALS['wiki']->getBaseUrl() . "/" . BAZ_CHEMIN_UPLOAD . $value;
                        }

                        // If this is a linked entity (listefiche), use the URL
                        if (startsWith($field_info['id'], 'listefiche')) {
                            $value = $GLOBALS['wiki']->href('', $value);
                        }
                    }

                    if (is_array($field_info['sem_type'])) {
                        // If we have multiple fields, duplicate the data
                        foreach ($field_info['sem_type'] as $sem_type) {
                            $semanticData[$sem_type] = $value;
                        }
                    } else {
                        $semanticData[$field_info['sem_type']] = $value;
                    }
                }
            }
        }

        return $semanticData;
    }

    public function convertFromSemanticData($formId, $data)
    {
        // Initialize by copying basic information
        $nonSemanticData = ['id_fiche' => $data['id_fiche'], 'antispam' => $data['antispam'], 'id_typeannonce' => $data['id_typeannonce']];

        $form = baz_valeurs_formulaire($formId);

        if (($data['@type'] && $data['@type'] !== $form['bn_sem_type']) || $data['type'] && $data['type'] !== $form['bn_sem_type']) {
            exit('The @type of the sent data must be ' . $form['bn_sem_type']);
        }

        $fields_infos = bazPrepareFormData($form);
        foreach ($fields_infos as $field_info) {
            // If the file is not semantically defined, ignore it
            if ($field_info['sem_type'] && $data[$field_info['sem_type']]) {
                if ($field_info['type'] === 'date') {
                    $date = new \DateTime($data[$field_info['sem_type']]);
                    $nonSemanticData[$field_info['id']] = $date->format('Y-m-d');
                    $nonSemanticData[$field_info['id'] . '_allday'] = 0;
                    $nonSemanticData[$field_info['id'] . '_hour'] = $date->format('H');
                    $nonSemanticData[$field_info['id'] . '_minutes'] = $date->format('i');
                } elseif ($field_info['type'] === 'image') {
                    $nonSemanticData['image'.$field_info['id']] = $data[$field_info['sem_type']];
                } else {
                    $nonSemanticData[$field_info['id']] = $data[$field_info['sem_type']];
                }
            }
        }

        return $nonSemanticData;
    }
}
