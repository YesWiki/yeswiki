<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;

class FormApiController extends YesWikiController
{
    /**
     * The form arrays carry the ActivityPub keypair (merged from page metadata for
     * internal use); the private key must never leave through the public API.
     */
    private function stripFormSecrets(array $form): array
    {
        unset($form['activitypub_private_key']);

        return $form;
    }

    #[Route('/api/forms', methods: ['GET'], options: ['acl' => ['public']])]
    public function getAllForms()
    {
        $forms = $this->getService(FormManager::class)->getAll();
        $forms = array_map([$this, 'stripFormSecrets'], $forms);

        return new ApiResponse(empty($forms) ? null : $forms);
    }

    /**
     * Live preview backing the form designer's field cards: each field object of the
     * posted template goes through the very path the entry form uses
     * (FormManager::prepareData + BazarField::renderInputIfPermitted), so a card shows
     * the real Twig markup of the input instead of a JS look-alike.
     *
     * Every posted item yields exactly one `previews` entry -- an empty string when the
     * field cannot be built or its rendering throws -- so the designer maps the answer
     * positionally back onto its cards. Fields are prepared one at a time on purpose:
     * prepareData() silently drops typeless entries, which would shift every index after
     * them.
     *
     * `styles` carries the stylesheet links the input templates registered while
     * rendering (date.css, leaflet.css, ...); the designer page has no other way to know
     * about them, and the previews would show up unstyled without them.
     *
     * Declared here rather than on FormController because routed controllers are
     * instantiated by YesWikiControllerResolver with `new $class()`: only a controller
     * with a no-argument constructor can back a route.
     */
    #[Route('/api/forms/preview', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function previewFormTemplate(Request $request)
    {
        $template = json_decode((string)$request->request->get('template', ''), true);
        if (!is_array($template)) {
            return new ApiResponse(['error' => _t('FORM_BUILDER_INVALID_JSON')], 400);
        }

        $cssLength = strlen($GLOBALS['css'] ?? '');
        $previews = [];
        // a field rendering may echo instead of returning (old-style extension fields):
        // keep any stray output out of the JSON body
        ob_start();
        try {
            foreach ($template as $fieldObject) {
                $previews[] = $this->renderFieldPreview($fieldObject);
            }
        } finally {
            ob_end_clean();
        }

        return new ApiResponse([
            'previews' => $previews,
            'styles' => substr($GLOBALS['css'] ?? '', $cssLength),
        ]);
    }

    /** One template field object => its entry-form input HTML, or '' if unrenderable. */
    private function renderFieldPreview($fieldObject): string
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
    public function getForm($formId)
    {
        if (strpos($formId, 'b64_') === 0) {
            $vFormId = base64_decode(urldecode(substr($formId, 4)), true);
        } else {
            $vFormID = $formId;
        }

        $vForm = $this->getService(BazarListService::class)->getForms(['idtypeannonce' => $vFormID])[$vFormID];

        if (!$vForm || !isset($vForm['id'])) {
            throw new NotFoundHttpException();
        }

        return new ApiResponse($this->stripFormSecrets($vForm));
    }
}
