<?php

namespace YesWiki\Content\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\BazarListService;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;
use YesWiki\Kernel\Service\AssetRegistry;

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
     * Answers with **markup**, not JSON: one out-of-band swap per field, addressed by the
     * designer's own field id, plus one out-of-band swap carrying whatever assets the input
     * templates declared while rendering -- leaflet for a map, vditor for a long text. Before
     * ticket 14 the stylesheets were recovered by diffing `strlen($GLOBALS['css'])` across
     * the render and the scripts were simply lost, which is why a map preview arrived as
     * markup with no leaflet behind it and never became a map.
     *
     * Addressing by id rather than by position also removes the fragility the positional
     * answer had: prepareData() silently drops typeless entries, so one unbuildable field
     * used to shift every card after it.
     *
     * Declared here rather than on FormController because routed controllers are
     * instantiated by YesWikiControllerResolver with `new $class()`: only a controller
     * with a no-argument constructor can back a route.
     */
    #[Route('/api/forms/preview', methods: ['POST'], options: ['acl' => ['@admins']])]
    public function previewFormTemplate(Request $request)
    {
        $template = json_decode((string)$request->request->get('template', ''), true);
        $ids = json_decode((string)$request->request->get('ids', ''), true);
        if (!is_array($template) || !is_array($ids) || count($ids) !== count($template)) {
            return new ApiResponse(['error' => _t('FORM_BUILDER_INVALID_JSON')], 400);
        }

        $registry = $this->getService(AssetRegistry::class);
        $html = '';

        // The whole batch renders in one capture scope: the assets come back as a value
        // instead of leaking into a page that, for an API response, does not exist. The set
        // is self-contained by design -- it re-declares leaflet even if the designer page
        // already has it, and the browser-side registry is what declines to load it twice.
        $assets = $registry->capture(function () use ($template, $ids, &$html): void {
            // a field rendering may echo instead of returning (old-style extension fields):
            // keep any stray output out of the response body
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
        // `$vFormId` here and `$vFormID` below -- one letter apart, so the base64 branch
        // assigned a variable nothing read and the lookup got an undefined one. Fetching a form
        // through `/api/forms/b64_…` has never worked (ticket 40).
        if (strpos($formId, 'b64_') === 0) {
            $vFormID = base64_decode(urldecode(substr($formId, 4)), true);
        } else {
            $vFormID = $formId;
        }

        $vForm = $this->getService(BazarListService::class)->getForms(['id' => $vFormID])[$vFormID];

        if (!$vForm || !isset($vForm['id'])) {
            throw new NotFoundHttpException();
        }

        return new ApiResponse($this->stripFormSecrets($vForm));
    }
}
