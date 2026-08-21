<?php

namespace YesWiki\Admin\Controller;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpClient\HttpClient;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\SemanticTransformer;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\AclService;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Service\EventDispatcher;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\Redirector;
use YesWiki\Kernel\Service\RuntimeConfig;
use YesWiki\Kernel\Service\TripleStore;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Kernel\Service\WikiUrls;

/**
 * Outgoing webhooks on comment and bazar-entry events (ticket 20, formerly the yeswiki-extension-webhooks repo).
 */
class WebhooksController extends YesWikiController implements EventSubscriberInterface
{
    public const WEBHOOKS_ACTION_CREATED_COMMENT = 'comment.created';
    public const WEBHOOKS_ACTION_MODIFIED_COMMENT = 'comment.updated';
    public const WEBHOOKS_ACTION_DELETED_COMMENT = 'comment.deleted';
    public const WEBHOOKS_ACTION_CREATED_ENTRY = 'entry.created';
    public const WEBHOOKS_ACTION_MODIFIED_ENTRY = 'entry.updated';
    public const WEBHOOKS_ACTION_DELETED_ENTRY = 'entry.deleted';

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

    protected AclService $aclService;

    /** @var bool|null memoised on first read by getDebugMode() */
    protected $debugMode;

    protected EntryManager $entryManager;
    protected FormManager $formManager;
    protected ParameterBagInterface $params;
    protected SemanticTransformer $semanticTransformer;
    protected TripleStore $tripleStore;
    protected UserManager $userManager;

    protected ContainerInterface $container;

    public function __construct(
        ContainerInterface $container,
        AclService $aclService,
        EntryManager $entryManager,
        FormManager $formManager,
        ParameterBagInterface $params,
        SemanticTransformer $semanticTransformer,
        TripleStore $tripleStore,
        UserManager $userManager
    ) {
        $this->container = $container;
        $this->aclService = $aclService;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->params = $params;
        $this->semanticTransformer = $semanticTransformer;
        $this->tripleStore = $tripleStore;
        $this->userManager = $userManager;
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

    public function sendCommentCreatedWebHook(Event $event): void
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], ['comment' => $data['data']], self::WEBHOOKS_ACTION_CREATED_COMMENT);
    }

    public function sendCommentModifiedWebHook(Event $event): void
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], ['comment' => $data['data']], self::WEBHOOKS_ACTION_MODIFIED_COMMENT);
    }

    public function sendCommentDeletedWebHook(Event $event): void
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], [
            'comment' => $data['data'],
            'associatedComments' => $data['data']['associatedComments'],
            'parentPage' => $data['data']['parentPage'],
        ], self::WEBHOOKS_ACTION_DELETED_COMMENT);
    }

    public function sendEntryCreatedWebHook(Event $event): void
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], $data['data'], self::ACTION_ADD);
    }

    public function sendEntryModifiedWebHook(Event $event): void
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], $data['data'], self::ACTION_EDIT);
    }

    public function sendEntryDeletedWebHook(Event $event): void
    {
        $data = $event->getData();
        $this->securedExecution([$this, 'webhooks_post_all'], $data['data'], self::ACTION_DELETE);
    }

    protected function showComments(): bool
    {
        return $this->container->has(EventDispatcher::class);
    }

    private function getDebugMode(): bool
    {
        if (is_null($this->debugMode)) {
            $this->debugMode = ($this->getService(RuntimeConfig::class)->getValue('debug') == 'yes');
        }

        return $this->debugMode;
    }

    /**
     * execution of $function with catch of errors.
     *
     * @param callable-string|array{0: object, 1: string} $function string = a function , otherwise [className, Method]
     *
     * @return mixed whatever $function answered, or null when it threw and the error was reported as a toast
     */
    public function securedExecution($function, mixed $param1 = null, mixed $param2 = null, mixed $param3 = null)
    {
        try {
            if (!is_array($function)) {
                return $function($param1, $param2, $param3);
            }
            $object = $function[0];
            $method = $function[1];

            return $object->$method($param1, $param2, $param3);
        } catch (\Throwable $th) {
            if ($this->getDebugMode() && $this->getService(AclService::class)->isAdmin()) {
                throw $th;
            }
            $functionName = is_array($function)
                ? get_class($function[0]) . '->' . $function[1]
                : $function;

            $_SESSION['message'] = ($_SESSION['message'] ?? '') . str_replace(
                ['{method}', '{function}', "\n"],
                [__METHOD__, $functionName, ''],
                nl2br(_t('WEBHOOKS_POST_ERROR'))
            );

            $this->getService(RuntimeConfig::class)['toast_class'] = 'alert alert-warning';
            $this->getService(RuntimeConfig::class)['toast_duration'] = 10000;
        }
    }

    public function viewWebhooksForm(): string
    {
        if (!empty($_POST['url']) && $this->getService(AclService::class)->isAdmin()) {
            $this->registerWebhooks();
        }

        return $this->render('@core/webhooks/webhooks_form.twig', [
            'url' => WikiUrls::absoluteUrl(),
            'webhooks' => $this->get_all_webhooks(),

            'forms' => $this->formManager->getAllLabels(),
            'formats' => $this->params->get('webhooks_formats'),
            'showComment' => $this->showComments(),
        ]);
    }

    protected function registerWebhooks(): void
    {
        $this->tripleStore->delete($this->getService(PageContext::class)->getTag(), self::VOCABULARY_WEBHOOK, null, '', '');

        $numFields = count($_POST['url']);

        for ($i = 0; $i < $numFields; $i++) {
            if ($_POST['url'][$i]) {
                if (!$this->is_valid_url(trim($_POST['url'][$i]))) {
                    $this->getService(Redirector::class)->terminate(_t('WEBHOOKS_ERROR_INVALID_URL'));
                }

                $formId = ($_POST['form'][$i] !== 'comments') ? intval($_POST['form'][$i]) : 'comments';

                if ($_POST['format'][$i] === self::FORMAT_ACTIVITYPUB) {
                    if ($formId === 0) {
                        foreach ($this->formManager->getAll() as $form) {
                            if (!$form['sem_type']) {
                                $this->getService(Redirector::class)->terminate(_t('WEBHOOKS_ERROR_FORM_NOT_SEMANTIC'));
                            }
                        }
                    } elseif ($formId !== 'comments') {
                        // no such form is as good a reason to refuse as a non-semantic one
                        $form = $this->formManager->getOne($formId);
                        if (empty($form['sem_type'])) {
                            $this->getService(Redirector::class)->terminate(_t('WEBHOOKS_ERROR_FORM_NOT_SEMANTIC'));
                        }
                    }
                }

                $this->tripleStore->create(
                    $this->getService(PageContext::class)->getTag(),
                    self::VOCABULARY_WEBHOOK,
                    json_encode([
                        'format' => $_POST['format'][$i],
                        'form' => $formId,
                        'url' => trim($_POST['url'][$i]),
                    ], JSON_THROW_ON_ERROR),
                    '',
                    ''
                );
            }
        }

        header('Location:' . $_SERVER['REQUEST_URI']);
    }

    /**
     * The webhooks registered on the BazaR page, optionally only those watching one form.
     *
     * @param int|string $form_id 0 for every webhook, 'comments' for the comment ones, otherwise a form id
     *
     * @return array<int, array<string, mixed>> each decoded from its triple's JSON value
     */
    public function get_all_webhooks($form_id = 0)
    {
        $all_webhooks = array_map(function ($webhook) {
            return json_decode($webhook['value'], true);
        }, $this->tripleStore->getAll('BazaR', self::VOCABULARY_WEBHOOK, '', ''));

        if ($form_id === 0) {
            return $all_webhooks;
        }

        return array_filter($all_webhooks, function ($webhook) use ($form_id) {
            return !isset($webhook['form']) || ($form_id != 'comments' && $webhook['form'] === 0) || $webhook['form'] === $form_id;
        });
    }

    /**
     * @param string $url
     *
     * @return string|false the url itself when it is a well-formed http(s) one, false otherwise
     */
    protected function is_valid_url($url)
    {
        if (preg_match('/^(http|https):\\/\\/[a-z0-9_]+([\\-\\.]{1}[a-z_0-9]+)*(\\.[_a-z]{2,5})?((:[0-9]{1,5})?\\/.*)?$/i', $url)) {
            return $url;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $data
     * @param string               $action_type one of the WEBHOOKS_ACTION_* / ACTION_* constants
     * @param string               $user_name
     *
     * @return string the rendered notification body
     */
    protected function get_notification_text($data, $action_type, $user_name)
    {
        switch ($action_type) {
            case self::WEBHOOKS_ACTION_CREATED_COMMENT:
            case self::WEBHOOKS_ACTION_MODIFIED_COMMENT:
            case self::WEBHOOKS_ACTION_DELETED_COMMENT:
                $formulaire = '';
                break;
            default:
                $formId = $data['form_id'] ?? '';
                if (is_array($formId) and count($formId) > 0) {
                    $formId = $formId[0];
                }
                if (!empty($formId) && strval($formId) == strval(intval($formId))) {
                    $formulaire = $this->formManager->getOne($formId);
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

        throw new \Exception("Webhook error: no notification template for action '{$action_type}'");
    }

    /**
     * @param string $date as stored, 'YYYY-MM-DD HH:MM:SS'
     *
     * @return string the same instant in the XSD dateTime spelling
     */
    protected function format_date_xsd($date)
    {
        $date_array = explode(' ', $date);

        return $date_array[0] . 'T' . $date_array[1] . 'Z';
    }

    /**
     * @param string|null $actor the entry's own actor uri, empty to fall back to the logged-in user
     *
     * @return string the actor uri, rebased on `webhooks_activitypub_actors_base_url` when one is configured
     */
    protected function get_actor_uri($actor)
    {
        $defaultActor = $this->configuredString('webhooks_activitypub_default_actor');
        if ($defaultActor !== '') {
            return $defaultActor;
        }

        if (!$actor) {
            $user = $this->userManager->getLoggedUser();
            $actor = $this->getService(UrlFormatter::class)->href('', !empty($user['name']) ? $user['name'] : _t('WEBHOOKS_ANONYMOUS_USER'));
        }

        $actorsBaseUrl = $this->configuredString('webhooks_activitypub_actors_base_url');
        if ($actorsBaseUrl !== '') {
            return $actorsBaseUrl . str_replace($this->configuredString('base_url'), '', $actor);
        }

        return $actor;
    }

    /**
     * A configuration value that is only meaningful as a string: '' when it is unset, or set
     * to something that is not one (a webmaster can put anything in the config file).
     */
    private function configuredString(string $name): string
    {
        if (!$this->params->has($name)) {
            return '';
        }
        $value = $this->params->get($name);

        return is_string($value) ? $value : '';
    }

    /**
     * @param string               $format one of the FORMAT_* constants
     * @param array<string, mixed> $data
     *
     * @return mixed the body to POST, shaped for $format
     */
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
                            'id' => $semanticData['@id'],
                            'type' => $semanticData['@type'],
                            'attributedTo' => $actorUri,
                            'to' => $to,

                            'published' => $this->format_date_xsd($data['data']['created_at']),
                            'updated' => $this->format_date_xsd($data['data']['updated_at']),
                        ],
                        $data['data']['semantic']
                    );

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
                foreach ($data['data'] as $key => $value) {
                    if (!in_array($key, ['tag', 'title', 'bf_titre', 'form_id', 'url', 'updated_at'], true)) {
                        unset($data['data'][$key]);
                    }
                }
                $data['base_url'] = $this->params->get('base_url');

                return $data;
        }

        // a format nobody recognises (an old triple, a webmaster's typo) is sent as-is,
        // the same as FORMAT_RAW -- better a delivered webhook than a silent null body
        return $data;
    }

    /**
     * update $webhook['url'], $data and options according to $webhook['format'].
     *
     * @param array<string, mixed> $webhook
     * @param array<string, mixed> $data
     *
     * @return array{0: string, 1: array<string, mixed>} [$url, $options (to merge to current options))]
     */
    protected function extract_url_options($webhook, $data)
    {
        $options = [];

        $url = $webhook['url'];
        switch ($webhook['format']) {
            case self::FORMAT_YESWIKI:
                $query = parse_url($webhook['url'], PHP_URL_QUERY);
                if (!empty($query)) {
                    parse_str($query, $queries);

                    if (isset($queries['bearer'])) {
                        // parse_str() answers an array for `bearer[]=...`, which is not a token
                        if (!empty($queries['bearer']) && is_string($queries['bearer'])) {
                            $options['headers'] = ['Authorization' => 'Bearer ' . $queries['bearer']];
                        }
                        unset($queries['bearer']);
                    }

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

    /**
     * @param array<string, mixed> $data
     * @param string               $action_type one of the WEBHOOKS_ACTION_* / ACTION_* constants
     *
     * @return void
     */
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
            if (!isset($data['semantic'])) {
                $activityPubWebhooks = array_filter($webhooks, function ($webhook) {
                    return $webhook['format'] === self::FORMAT_ACTIVITYPUB;
                });

                if (count($activityPubWebhooks) > 0) {
                    $data['semantic'] = $this->semanticTransformer->convertToSemanticData($data['form_id'], $data);
                }
            }

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

            $client = HttpClient::create([
                'headers' => ['Connection' => 'Close'],

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
                }
            }

            foreach ($responses as $response) {
                try {
                    $response->getStatusCode();
                } catch (\Throwable $th) {
                }
            }
        }
    }
}
