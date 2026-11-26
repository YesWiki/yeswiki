<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Controller\EntryController;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\AssetRegistry;

class FormApiController extends YesWikiController
{
    #[Route('/api/forms', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllForms(): ApiResponse
    {
        $forms = $this->getService(FormManager::class)->getAll();

        return new ApiResponse(empty($forms) ? null : $forms);
    }

    /**
     * Live preview backing the form designer's field cards: each field object of the posted template goes through the very path the entry form uses (FormManager::prepareData + BazarField::renderInputIfPermitted), so a card shows the real Twig markup of the input instead of a JS look-alike.
     */
    #[Route('/api/forms/preview', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function previewFormTemplate(Request $request): Response
    {
        $template = json_decode((string)$request->request->get('template', ''), true);
        $ids = json_decode((string)$request->request->get('ids', ''), true);
        if (!is_array($template) || !is_array($ids) || count($ids) !== count($template)) {
            return new ApiResponse(['error' => _t('FORM_BUILDER_INVALID_JSON')], 400);
        }

        $registry = $this->getService(AssetRegistry::class);
        $html = '';

        $assets = $registry->capture(function () use ($template, $ids, &$html): void {
            ob_start();
            try {
                foreach (array_values($template) as $index => $fieldObject) {
                    $id = $ids[$index] ?? null;
                    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                        continue;
                    }
                    $html .= '<div hx-swap-oob="innerHTML:#yw-fb-preview-' . $id . '">'
                        . $this->renderFieldPreview($fieldObject)
                        . '</div>' . "\n";
                }
            } finally {
                ob_end_clean();
            }
        });

        return new Response($html . $assets->toOutOfBandHtml(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    #[Route('/api/forms/preview-form', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function previewWholeForm(Request $request): Response
    {
        $template = json_decode((string)$request->request->get('template', ''), true);
        if (!is_array($template)) {
            return new Response('<div class="yw-alert yw-alert--danger">' . htmlspecialchars(_t('FORM_BUILDER_INVALID_JSON')) . '</div>', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }
        $width = (string)$request->request->get('entry_form_width', '');
        $form = [
            'id' => 'preview',
            'label' => (string)$request->request->get('label', ''),
            'template' => $template,
            'prepared' => $this->getService(FormManager::class)->prepareData(['template' => $template]),
            'entry_form_width' => in_array($width, ['narrow', 'wide'], true) ? $width : null,
        ];

        $html = '';
        $assets = $this->getService(AssetRegistry::class)->capture(function () use ($form, &$html): void {
            ob_start();
            try {
                $html = $this->render('@core/entries/form.twig', [
                    'form' => $form,
                    'formAction' => '#',
                    'renderedInputs' => $this->getService(EntryController::class)->getRenderedInputs($form),
                    'passwordForEditing' => '',
                    'incomingUrl' => '',
                    'cancelUrl' => '',
                    'error' => '',
                    'preview' => true,
                ]);
            } finally {
                ob_end_clean();
            }
        });

        return new Response($html . $assets->toOutOfBandHtml(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** One template field object => its entry-form input HTML, or '' if unrenderable. */
    private function renderFieldPreview(mixed $fieldObject): string
    {
        if (!is_array($fieldObject) || empty($fieldObject['type'])) {
            return '';
        }
        try {
            $prepared = $this->getService(FormManager::class)->prepareData(['template' => [$fieldObject]]);
            $field = reset($prepared);

            return $field instanceof BazarField ? (string)$field->renderInputIfPermitted(null) : '';
        } catch (\Throwable $th) {
            return '';
        }
    }

    #[Route('/api/forms/{formId}', methods: ['GET'], options: ['acl' => ['public']])]
    public function getForm(string $formId): ApiResponse
    {
        if (strpos($formId, 'b64_') === 0) {
            $vFormID = base64_decode(urldecode(substr($formId, 4)), true);
        } else {
            $vFormID = $formId;
        }

        $bazarListService = $this->getService(BazarListService::class);

        if (!$bazarListService->isValidID($vFormID)) {
            throw new NotFoundHttpException();
        }

        $vForm = $bazarListService->getForms(['id' => $vFormID])[$vFormID] ?? null;

        if (!$vForm || !isset($vForm['id'])) {
            throw new NotFoundHttpException();
        }

        return new ApiResponse($vForm);
    }
}
