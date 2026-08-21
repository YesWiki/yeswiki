<?php

namespace YesWiki\Content\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Render\Service\TemplateEngine;

class SemanticTransformer
{
    protected $templateEngine;
    protected $params;

    public function __construct(TemplateEngine $templateEngine, ParameterBagInterface $params)
    {
        $this->templateEngine = $templateEngine;
        $this->params = $params;
    }

    /**
     * @param array<string, mixed> $form
     *
     * @return array<string, mixed>
     */
    public function convertToSemanticData(array $form, mixed $data): array
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

    /**
     * @param array<string, mixed> $form the form itself, not its id: looking it up here would
     *                                   make this service depend on FormManager, and FormManager
     *                                   already depends on EntryManager which depends on this
     *                                   (ticket 04's cycle, reintroduced and caught by the
     *                                   container in ticket 50)
     *
     * @return array<string, mixed>
     */
    public function convertFromSemanticData(array $form, mixed $data): array
    {
        if (empty($form['sem_reverse_template'])) {
            throw new \Exception('No reverse semantic template defined for form ' . ($form['id'] ?? '?'));
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
