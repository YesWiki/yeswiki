<?php

namespace YesWiki\Core\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SemanticTransformer
{
    protected $templateEngine;
    protected $params;

    public function __construct(TemplateEngine $templateEngine, ParameterBagInterface $params)
    {
        $this->templateEngine = $templateEngine;
        $this->params = $params;
    }

    public function convertToSemanticData($form, $data): array
    {
        if (empty($form['sem_template'])) {
            throw new \Exception(_t('BAZAR_SEMANTIC_TYPE_MISSING'));
        }

        $json = $this->templateEngine->renderSandboxedFromStringNoEscape($form['sem_template'], $data);
        $semanticData = json_decode($json, true);

        $semanticData['id'] = $this->params->get('base_url') . $data['tag'];

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Semantic template produced invalid JSON: ' . json_last_error_msg());
        }

        return $semanticData;
    }

    public function convertFromSemanticData($formId, $data): array
    {
        $form = baz_valeurs_formulaire($formId);

        if (empty($form['sem_reverse_template'])) {
            throw new \Exception('No reverse semantic template defined for form ' . $formId);
        }

        $json = $this->templateEngine->renderSandboxedFromStringNoEscape($form['sem_reverse_template'], $data);
        $fields = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Reverse semantic template produced invalid JSON: ' . json_last_error_msg());
        }

        return array_merge([
            'antispam' => 1,
            'form_id' => $data['form_id'] ?? '',
        ], $fields);
    }
}
