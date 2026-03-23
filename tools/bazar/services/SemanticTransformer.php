<?php

namespace YesWiki\Bazar\Service;

use YesWiki\Core\Service\TemplateEngine;

class SemanticTransformer
{
    protected $templateEngine;

    public function __construct(TemplateEngine $templateEngine)
    {
        $this->templateEngine = $templateEngine;
    }

    public function convertToSemanticData($form, $data, $isHtmlFormatted = false): array
    {
        if (empty($form['bn_sem_template'])) {
            throw new \Exception(_t('BAZAR_SEMANTIC_TYPE_MISSING'));
        }

        $json = $this->templateEngine->renderFromStringNoEscape($form['bn_sem_template'], $data);
        $semanticData = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Semantic template produced invalid JSON: ' . json_last_error_msg());
        }

        return $semanticData;
    }

    public function convertFromSemanticData($formId, $data): array
    {
        $form = baz_valeurs_formulaire($formId);

        if (empty($form['bn_sem_reverse_template'])) {
            throw new \Exception('No reverse semantic template defined for form ' . $formId);
        }

        $json = $this->templateEngine->renderFromStringNoEscape($form['bn_sem_reverse_template'], $data);
        $fields = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Reverse semantic template produced invalid JSON: ' . json_last_error_msg());
        }

        return array_merge([
            'id_fiche' => $data['id_fiche'] ?? '',
            'antispam' => $data['antispam'] ?? '',
            'id_typeannonce' => $data['id_typeannonce'] ?? '',
        ], $fields);
    }
}
