<?php

namespace YesWiki\Admin\Controller;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpClient\HttpClient;
use YesWiki\Identity\Service\AclService;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Content\Service\TripleStore;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Core\YesWikiController;
use YesWiki\Wiki;

/**
 * Outgoing webhooks on comment and bazar-entry events (ticket 20, formerly the
 * yeswiki-extension-webhooks repo). Absorbed as-is: fires on comment/entry
 * create/update/delete via the yeswiki.event_subscriber tag, subscriptions are
 * stored as JSON in the triple store. Scope unchanged — no form/user events.
 *
 * Two adaptations from the extension version, both disclosed in the ticket doc:
 * the interface_exists() compatibility split (WebhooksControllerCommons) is
 * collapsed into one class since core always ships symfony/event-dispatcher,
 * and the HTTP layer uses symfony/http-client (already a core dependency)
 * instead of Guzzle.
 */
class WebhooksController extends YesWikiController implements EventSubscriberInterface
{
    public const WEBHOOKS_ACTION_CREATED_COMMENT = 'comment.created';
    public const WEBHOOKS_ACTION_MODIFIED_COMMENT = 'comment.updated';
    public const WEBHOOKS_ACTION_DELETED_COMMENT = 'comment.deleted';
    public const WEBHOOKS_ACTION_CREATED_ENTRY = 'entry.created';
    public const WEBHOOKS_ACTION_MODIFIED_ENTRY = 'entry.updated';
    public const WEBHOOKS_ACTION_DELETED_ENTRY = 'entry.deleted';

    // formerly tools/webhooks/wiki.php defines
    public const ACTION_ADD = 'add';
    public const ACTION_EDIT = 'edit';
    public const ACTION_DELETE = 'delete';
    public const FORMAT_RAW = 'raw';
    public const FORMAT_ACTIVITYPUB = 'activitypub';
    public const FORMAT_MATTERMOST = 'mattermost';
    public const FORMAT_SLACK = 'slack';
    public const FORMAT_YESWIKI = 'yeswiki';
    public const VUE_TEST = 'test-webhook';
    public const VOCABULARY_WEBHOOK = 'http://yeswiki.net/_vocabulary/webhook';
    public const VOCABULARY_TEST = 'http://yeswiki.net/_vocabulary/webhook-test';
    public const ACTIVITYPUB_PUBLIC_URI = 'https://www.w3.org/ns/activitystreams#Public';

    protected $aclService;
    protected $debugMode;
    protected $entryManager;
    protected $formManager;
    protected $params;
    protected $semanticTransformer;
    protected $tripleStore;
    protected $userManager;

    public function __construct(
        AclService $aclService,
        EntryManager $entryManager,
        FormManager $formManager,
        ParameterBagInterface $params,
        SemanticTransformer $semanticTransformer,
        TripleStore $tripleStore,
        UserManager $userManager,
        Wiki $wiki
    ) {
        $this->aclService = $aclService;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->params = $params;
        $this->semanticTransformer = $semanticTransformer;
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
        $this->debugMode = null;
    }

    public static function getSubscribedEvents()
    {
        return [
            self::WEBHOOKS_ACTION_CREATED_COMMENT => 'sendCommentCreatedWebHook',
            self::WEBHOOKS_ACTION_MODIFIED_COMMENT => 'sendCommentModifiedWebHook',
            self::WEBHOOKS_ACTION_DELETED_COMMENT => 'sendCommentDeletedWebHook',
            self::WEBHOOKS_ACTION_CREATED_ENTRY => 'sendEntryCreatedWebHook',
            self::WEBHOOKS_ACTION_MODIFIED_ENTRY => 'sendEntryModifiedWebHook',
            self::WEBHOOKS_ACTION_DELETED_ENTRY => 'sendEntryDeletedWebHook',
        ];
    }

    public function sendCommentCreatedWebHook($event)
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], ['comment' => $data['data']], self::WEBHOOKS_ACTION_CREATED_COMMENT);
    }

    public function sendCommentModifiedWebHook($event)
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], ['comment' => $data['data']], self::WEBHOOKS_ACTION_MODIFIED_COMMENT);
    }

    public function sendCommentDeletedWebHook($event)
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], [
            'comment' => $data['data'],
            'associatedComments' => $data['data']['associatedComments'],
            'parentPage' => $data['data']['parentPage'],
        ], self::WEBHOOKS_ACTION_DELETED_COMMENT);
    }

    public function sendEntryCreatedWebHook($event)
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], $data['data'], self::ACTION_ADD);
    }

    public function sendEntryModifiedWebHook($event)
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], $data['data'], self::ACTION_EDIT);
    }

    public function sendEntryDeletedWebHook($event)
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], $data['data'], self::ACTION_DELETE);
    }

    protected function showComments(): bool
    {
        return $this->wiki->services->has(EventDispatcher::class);
    }

    private function getDebugMode(): bool
    {
        if (is_null($this->debugMode)) {
            $this->debugMode = ($this->wiki->GetConfigValue('debug') == 'yes');
        }

        return $this->debugMode;
    }

    /**
     * execution of $function with catch of errors.
     *
     * @param string|array $function string = a function , otherwise [className, Method]
     */
    public function securedExecution($function, $param1 = null, $param2 = null, $param3 = null)
    {
        try {
            $isMethod = (is_array($function) && count($function) == 2);
            if (!$isMethod) {
                return $function($param1, $param2, $param3);
            }
            $object = $function[0];
            $method = $function[1];

            return $object->$method($param1, $param2, $param3);
        } catch (\Throwable $th) {
            if ($this->getDebugMode() && $this->wiki->UserIsAdmin()) {
                throw $th;
            }
            $functionName = $isMethod
                ? (
                    is_string(get_class($function[0])) ? get_class($function[0]) . '->' : ''
                ) . (
                    is_string($function[1]) ? $function[1] : ''
                )
                : $function;

            $_SESSION['message'] = ($_SESSION['message'] ?? '') . str_replace(
                ['{method}', '{function}', "\n"],
                [__METHOD__, $functionName, ''],
                nl2br(_t('WEBHOOKS_POST_ERROR'))
            );

            // TODO find a way not to change config
            $this->wiki->config['toast_class'] = 'alert alert-warning';
            $this->wiki->config['toast_duration'] = 10000;
        }
    }

    public function viewWebhooksForm(): string
    {
        if (!empty($_POST['url']) && $this->wiki->UserIsAdmin()) {
            $this->registerWebhooks();
        }

        return $this->render('@core/webhooks/webhooks_form.twig', [
            'url' => getAbsoluteUrl(),
            'webhooks' => $this->get_all_webhooks(),
            'forms' => $this->formManager->getAll(),
            'formats' => $this->params->get('webhooks_formats'),
            'showComment' => $this->showComments(),
        ]);
    }

    protected function registerWebhooks()
    {
        // First delete all existing triples for this resource
        $this->tripleStore->delete($this->wiki->GetPageTag(), self::VOCABULARY_WEBHOOK, null, '', '');

        $numFields = count($_POST['url']);

        for ($i = 0; $i < $numFields; $i++) {
            if ($_POST['url'][$i]) {
                // Check that URL is valid
                if (!$this->is_valid_url(trim($_POST['url'][$i]))) {
                    $this->wiki->exit(_t('WEBHOOKS_ERROR_INVALID_URL'));
                }

                $formId = ($_POST['form'][$i] !== 'comments') ? intval($_POST['form'][$i]) : 'comments';
                // If ActivityPub is selected, check that the selected form(s) are semantic
                if ($_POST['format'][$i] === self::FORMAT_ACTIVITYPUB) {
                    if ($formId === 0) {
                        // Check that all forms are semantic
                        foreach ($this->formManager->getAll() as $form) {
                            if (!$form['sem_type']) {
                                $this->wiki->exit(_t('WEBHOOKS_ERROR_FORM_NOT_SEMANTIC'));
                            }
                        }
                    } elseif ($formId !== 'comments') {
                        // Check that the selected form is semantic
                        $form = $this->formManager->getOne($formId);
                        if (!$form['sem_type']) {
                            $this->wiki->exit(_t('WEBHOOKS_ERROR_FORM_NOT_SEMANTIC'));
                        }
                    }
                }

                // All good, save webhook
                $this->tripleStore->create(
                    $this->wiki->GetPageTag(),
                    self::VOCABULARY_WEBHOOK,
                    json_encode([
                        'format' => $_POST['format'][$i],
                        'form' => $formId,
                        'url' => trim($_POST['url'][$i]),
                    ]),
                    '',
                    ''
                );
            }
        }

        // Redirect so that we don't resubmit form on reload
        header('Location:' . $_SERVER['REQUEST_URI']);
    }

    public function get_all_webhooks($form_id = 0)
    {
        // Select all webhooks
        $all_webhooks = array_map(function ($webhook) {
            return json_decode($webhook['value'], true);
        }, $this->tripleStore->getAll('BazaR', self::VOCABULARY_WEBHOOK, '', ''));

        if ($form_id === 0) {
            return $all_webhooks;
        }

        // Return only webhooks which must be called for this form_id
        return array_filter($all_webhooks, function ($webhook) use ($form_id) {
            return !isset($webhook['form']) || ($form_id != 'comments' && $webhook['form'] === 0) || $webhook['form'] === $form_id;
        });
    }

    protected function is_valid_url($url)
    {
        if (preg_match('/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*(\\.[_a-z]{2,5})?((:[0-9]{1,5})?\\/.*)?$/i', $url)) {
            return $url;
        }

        return false;
    }

    protected function get_notification_text($data, $action_type, $user_name)
    {
        switch ($action_type) {
            case self::WEBHOOKS_ACTION_CREATED_COMMENT:
            case self::WEBHOOKS_ACTION_MODIFIED_COMMENT:
            case self::WEBHOOKS_ACTION_DELETED_COMMENT:
                $formulaire = '';
                break;
            default:
                $idformulaire = $data['form_id'] ?? '';
                if (is_array($idformulaire) and count($idformulaire) > 0) {
                    $idformulaire = $idformulaire[0];
                }
                if (!empty($idformulaire) && strval($idformulaire) == strval(intval($idformulaire))) {
                    $formulaire = $this->formManager->getOne($idformulaire);
                } else {
                    $formulaire = $this->formManager->getAll()[0];
                }
                break;
        }
        $tabData = [
            'data' => $data,
            'form' => $formulaire,
            'user' => $user_name,
            'url' => $this->params->get('base_url'),
        ];
        switch ($action_type) {
            case self::ACTION_ADD:
                return $this->render('@core/webhooks/message-add.twig', $tabData);
            case self::ACTION_EDIT:
                return $this->render('@core/webhooks/message-edit.twig', $tabData);
            case self::ACTION_DELETE:
                return $this->render('@core/webhooks/message-delete.twig', $tabData);
            case self::WEBHOOKS_ACTION_CREATED_COMMENT:
                return $this->render('@core/webhooks/message-create-comment.twig', $tabData);
            case self::WEBHOOKS_ACTION_MODIFIED_COMMENT:
                return $this->render('@core/webhooks/message-modify-comment.twig', $tabData);
            case self::WEBHOOKS_ACTION_DELETED_COMMENT:
                return $this->render('@core/webhooks/message-delete-comment.twig', $tabData);
        }
    }

    protected function format_date_xsd($date)
    {
        $date_array = explode(' ', $date);

        return $date_array[0] . 'T' . $date_array[1] . 'Z';
    }

    protected function get_actor_uri($actor)
    {
        if ($this->params->has('webhooks_activitypub_default_actor')
            && !empty($this->params->get('webhooks_activitypub_default_actor'))) {
            // If a default global-wide actor is defined, use it
            // (the extension version returned ->has() here — a pre-existing bug
            // sending boolean true as the actor URI — fixed on absorption)
            return $this->params->get('webhooks_activitypub_default_actor');
        }
        // If no field is marked as an actor, take the current logged-in user
        if (!$actor) {
            $user = $this->userManager->getLoggedUser();
            $actor = $this->wiki->href('', !empty($user['name']) ? $user['name'] : _t('WEBHOOKS_ANONYMOUS_USER'));
        }
        // If a base URL is defined in the configs, replace the yeswiki base URL with it
        if ($this->params->has('webhooks_activitypub_actors_base_url')
            && !empty($this->params->get('webhooks_activitypub_actors_base_url'))) {
            $actor = str_replace($this->params->get('base_url'), '', $actor);

            return $this->params->get('webhooks_activitypub_actors_base_url') . $actor;
        }

        return $actor;
    }

    protected function format_json_data($format, $data)
    {
        switch ($format) {
            case self::FORMAT_RAW:
                return $data;

            case self::FORMAT_ACTIVITYPUB:
                $semanticData = $data['data']['semantic'];
                if (!$semanticData) {
                    throw new \Exception('Webhook error: unable to format data for activitypub (no semantic data defined)');
                }

                $actorUri = $this->get_actor_uri($semanticData['actor']);
                $to = [
                    $actorUri . '/followers',
                    self::ACTIVITYPUB_PUBLIC_URI,
                ];
                $activityPubActions = [
                    self::ACTION_ADD => 'Create',
                    self::ACTION_EDIT => 'Update',
                    self::ACTION_DELETE => 'Delete',
                ];

                if ($data['action'] === self::ACTION_DELETE) {
                    $object = $semanticData['@id'];
                } else {
                    $object = array_merge(
                        [
                            // In ActivityPub, IDs and types are defined without the @ prefix (go figure ?)
                            'id' => $semanticData['@id'],
                            'type' => $semanticData['@type'],
                            'attributedTo' => $actorUri,
                            'to' => $to,
                            // If published or updated are defined as a semantic field, this will be overwritten
                            'published' => $this->format_date_xsd($data['data']['created_at']),
                            'updated' => $this->format_date_xsd($data['data']['updated_at']),
                        ],
                        $data['data']['semantic']
                    );

                    // Remove unused keys
                    unset($object['@context']);
                    unset($object['@type']);
                    unset($object['@id']);
                    unset($object['actor']);
                }

                return [
                    '@context' => $semanticData['@context'],
                    'type' => $activityPubActions[$data['action']],
                    'to' => $to,
                    'actor' => $actorUri,
                    'object' => $object,
                ];

            case self::FORMAT_MATTERMOST:
                return [
                    'username' => $this->params->get('webhooks_bot_name'),
                    'icon_url' => $this->params->get('webhooks_bot_icon'),
                    'text' => $data['text'],
                ];

            case self::FORMAT_SLACK:
                return ['text' => $data['text']];

            case self::FORMAT_YESWIKI:
                // remove not used fields
                foreach ($data['data'] as $key => $value) {
                    if (!in_array($key, ['tag', 'bf_titre', 'form_id', 'url', 'updated_at'], true)) {
                        unset($data['data'][$key]);
                    }
                }
                $data['base_url'] = $this->params->get('base_url');

                return $data;
        }
    }

    /**
     * update $webhook['url'], $data and options according to $webhook['format'].
     *
     * @param array $data
     *
     * @return array [$url, $options (to merge to current options))]
     */
    protected function extract_url_options($webhook, $data)
    {
        $options = [];
        // default (the extension version left $url undefined when a yeswiki-format
        // webhook had no query string — fixed on absorption)
        $url = $webhook['url'];
        switch ($webhook['format']) {
            case self::FORMAT_YESWIKI:
                $query = parse_url($webhook['url'], PHP_URL_QUERY);
                if (!empty($query)) {
                    parse_str($query, $queries);

                    // get bearer
                    if (isset($queries['bearer'])) {
                        if (!empty($queries['bearer'])) {
                            $options['headers'] = ['Authorization' => 'Bearer ' . $queries['bearer']];
                        }
                        unset($queries['bearer']);
                    }

                    // refresh url
                    array_walk($queries, function (&$item, $key) {
                        $item = empty($item)
                            ? $key
                            : (
                                is_array($item)
                                ? $key . '=' . implode(',', $item)
                                : $key . '=' . $item
                            );
                    });
                    $newQuery = implode('&', $queries);
                    $url = str_replace($query, $newQuery, $webhook['url']);
                }
                break;

            default:
                break;
        }

        return [$url, $options];
    }

    public function webhooks_post_all($data, $action_type)
    {
        switch ($action_type) {
            case self::WEBHOOKS_ACTION_CREATED_COMMENT:
            case self::WEBHOOKS_ACTION_MODIFIED_COMMENT:
            case self::WEBHOOKS_ACTION_DELETED_COMMENT:
                $form_id = 'comments';
                break;
            default:
                if (!isset($data['form_id'])) {
                    throw new \Exception('Webhook error: unable to determine the form ID (form_id is not defined)');
                }

                $form_id = intval($data['form_id']);
                break;
        }

        $webhooks = $this->get_all_webhooks($form_id);

        if (count($webhooks) > 0) {
            // Add the semantic data if they don't already exist

            if (!isset($data['semantic'])) {
                // If one of the webhook is using ActivityPub
                $activityPubWebhooks = array_filter($webhooks, function ($webhook) {
                    return $webhook['format'] === self::FORMAT_ACTIVITYPUB;
                });

                if (count($activityPubWebhooks) > 0) {
                    $data['semantic'] = $this->semanticTransformer->convertToSemanticData($data['form_id'], $data);
                }
            }

            // Prepare data to send

            $logged_user = $this->userManager->getLoggedUser();
            $logged_user_name = empty($logged_user) ? _t('WEBHOOKS_ANONYMOUS_USER') : $logged_user['name'];

            $data_to_send = [
                'action' => $action_type,
                'user' => $logged_user_name,
                'text' => $this->get_notification_text($data, $action_type, $logged_user_name),
                'data_type' => 'bazar',
                'bazar_form_id' => $form_id,
                'data' => $data,
            ];

            // Send data to all webhooks, concurrently (symfony/http-client requests
            // are lazy: they all fire when the first response is consumed), with the
            // same short timeout and ignore-errors behavior as the Guzzle version
            $client = HttpClient::create([
                'headers' => ['Connection' => 'Close'],
                // to prevent 504 error if a webhook is not reachable; unlike
                // Guzzle's total-duration `timeout`, Symfony's `timeout` is an
                // idle timeout — max_duration is what caps the whole request
                'timeout' => 4,
                'max_duration' => 4,
            ]);

            $responses = [];
            foreach ($webhooks as $webhook) {
                list($url, $options) = $this->extract_url_options($webhook, $data_to_send);
                try {
                    $responses[] = $client->request(
                        'POST',
                        $url,
                        $options + ['json' => $this->format_json_data($webhook['format'], $data_to_send)]
                    );
                } catch (\Throwable $th) {
                    // invalid URL etc. — skip this webhook, keep the others
                }
            }

            // Wait for the requests to complete, otherwise the code may end before
            // the request is sent; errors (unreachable host, 5xx) are ignored
            foreach ($responses as $response) {
                try {
                    $response->getStatusCode();
                } catch (\Throwable $th) {
                    // Do nothing on errors...
                }
            }
        }
    }
}
