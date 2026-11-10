<?php

namespace YesWiki\Identity\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Service\FormManager;
use YesWiki\Core\DashboardShell;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Identity\Service\SignIn\AccountLinks;
use YesWiki\Identity\Service\SignIn\ProviderRegistry;
use YesWiki\Identity\Service\SignIn\ProvidersEditor;
use YesWiki\Identity\Service\SignIn\SignInFlow;
use YesWiki\Identity\Service\SignIn\Type\LocalType;
use YesWiki\Identity\SignIn\Contract\HandshakeMode;
use YesWiki\Identity\SignIn\Contract\PasswordProvider;
use YesWiki\Identity\SignIn\Contract\ProviderConfig;
use YesWiki\Identity\SignIn\Contract\ProviderType;
use YesWiki\Identity\SignIn\Contract\PublishesMetadata;
use YesWiki\Kernel\Service\Flash;
use YesWiki\Kernel\Service\PageContext;
use YesWiki\Kernel\Service\UrlFormatter;
use YesWiki\Render\Service\TemplateEngine;

/** `/admin/sign-in` -- the providers of `sso_providers`, listed, added, edited, switched and deleted (spec 8.3). */
class AdminSignInController extends YesWikiController
{
    use DashboardShell;
    use RendersSignInOutcome;

    private const ADMIN_ACL = ['@admins'];
    private const CURRENT = 'admin/sign-in';

    #[Route('/admin/sign-in', methods: ['GET'], options: ['acl' => self::ADMIN_ACL])]
    public function index(): Response
    {
        $registry = $this->getService(ProviderRegistry::class);
        $types = $registry->types();
        $accounts = $this->getService(AccountLinks::class)->countByProvider();

        $providers = [];
        foreach ($this->getService(ProvidersEditor::class)->all() as $id => $entry) {
            $typeName = (string)($entry['type'] ?? '');
            $type = $types[$typeName] ?? null;
            $providers[] = [
                'id' => $id,
                'label' => is_string($entry['label'] ?? null) && $entry['label'] !== '' ? $entry['label'] : $id,
                'typeLabel' => $type?->label() ?? $typeName,
                'available' => $type !== null,
                'enabled' => (bool)($entry['enabled'] ?? true),
                'accounts' => $accounts[$id] ?? 0,
                'isLocal' => $typeName === LocalType::NAME,
            ];
        }

        $offered = [];
        foreach ($types as $name => $type) {
            if ($name !== LocalType::NAME) {
                $offered[] = ['name' => $name, 'label' => $type->label()];
            }
        }

        return $this->page('@core/admin/sign-in.twig', self::CURRENT, [
            'providers' => $providers,
            'offered' => $offered,
            'problems' => $registry->problems(),
        ]);
    }

    #[Route('/admin/sign-in/new/{type}', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function add(string $type): Response
    {
        $providerType = $this->getService(ProviderRegistry::class)->types()[$type] ?? null;
        if ($providerType === null || $type === LocalType::NAME) {
            Flash::error(_t('SIGN_IN_ADMIN_UNKNOWN_TYPE'));

            return $this->toIndex();
        }

        $errors = [];
        $posted = [];
        $request = $this->getRequest();
        if ($request->isMethod('POST')) {
            $posted = $request->request->all();
            $errors = $this->guardedAgainstForgery();
            if ($errors === []) {
                $created = $this->getService(ProvidersEditor::class)->create($type, $posted);
                if (is_string($created)) {
                    Flash::success(_t('SIGN_IN_ADMIN_CREATED'));

                    return $this->afterSave($created, $posted);
                }
                $errors = $created;
            }
        }

        return $this->form($providerType, null, [], $posted, $errors);
    }

    #[Route('/admin/sign-in/{id}/edit', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function edit(string $id): Response
    {
        $entry = $this->getService(ProvidersEditor::class)->all()[$id] ?? null;
        $providerType = $entry === null ? null : ($this->getService(ProviderRegistry::class)->types()[(string)($entry['type'] ?? '')] ?? null);
        if ($entry === null || $providerType === null) {
            Flash::error(_t($entry === null ? 'SIGN_IN_ADMIN_UNKNOWN_PROVIDER' : 'SIGN_IN_ADMIN_UNAVAILABLE_NOT_EDITABLE'));

            return $this->toIndex();
        }

        $errors = [];
        $posted = [];
        $request = $this->getRequest();
        if ($request->isMethod('POST')) {
            $posted = $request->request->all();
            $errors = $this->guardedAgainstForgery();
            if ($errors === []) {
                $errors = $this->getService(ProvidersEditor::class)->update($id, $posted);
                if ($errors === []) {
                    Flash::success(_t('SIGN_IN_ADMIN_SAVED'));

                    return $this->afterSave($id, $posted);
                }
            }
        }

        return $this->form($providerType, $id, $entry, $posted, $errors);
    }

    #[Route('/admin/sign-in/{id}/toggle', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function toggle(string $id): Response
    {
        $errors = $this->guardedAgainstForgery();
        if ($errors === []) {
            $on = filter_var($this->getRequest()->request->get('enabled', '0'), FILTER_VALIDATE_BOOLEAN);
            $refusal = $this->getService(ProvidersEditor::class)->setEnabled($id, $on);
            $refusal === null ? Flash::success(_t($on ? 'SIGN_IN_ADMIN_SWITCHED_ON' : 'SIGN_IN_ADMIN_SWITCHED_OFF')) : Flash::error($refusal);
        } else {
            Flash::error($errors[0]);
        }

        return $this->toIndex();
    }

    #[Route('/admin/sign-in/{id}/delete', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function delete(string $id): Response
    {
        $errors = $this->guardedAgainstForgery();
        if ($errors === []) {
            $refusal = $this->getService(ProvidersEditor::class)->delete($id);
            $refusal === null ? Flash::success(_t('SIGN_IN_ADMIN_DELETED')) : Flash::error($refusal);
        } else {
            Flash::error($errors[0]);
        }

        return $this->toIndex();
    }

    /**
     * Runs the real protocol against the provider, as the signed-in administrator, and shows what came back (spec 8.3).
     * A redirect provider is left for and comes back to its callback; a password provider first asks for a test login and password.
     */
    #[Route('/admin/sign-in/{id}/test', methods: ['GET', 'POST'], options: ['acl' => self::ADMIN_ACL])]
    public function test(string $id): Response
    {
        $registry = $this->getService(ProviderRegistry::class);
        $config = $registry->config($id);
        if ($config === null) {
            Flash::error(_t('SIGN_IN_ADMIN_UNKNOWN_PROVIDER'));

            return $this->toIndex();
        }
        $flow = $this->getService(SignInFlow::class);
        if (!$registry->get($id) instanceof PasswordProvider) {
            return $this->renderSignInOutcome($flow->begin($id, '', HandshakeMode::Test));
        }

        $errors = [];
        $request = $this->getRequest();
        if ($request->isMethod('POST')) {
            $errors = $this->guardedAgainstForgery();
            if ($errors === []) {
                $posted = $request->request->all();
                $login = $posted['login'] ?? '';
                $password = $posted['password'] ?? '';

                return $this->renderSignInOutcome($flow->password($id, is_string($login) ? $login : '', is_string($password) ? $password : '', false, HandshakeMode::Test));
            }
        }

        return $this->page('@core/admin/sign-in-test.twig', self::CURRENT, [
            'id' => $id,
            'label' => $config->label,
            'result' => null,
            'form' => [
                'action' => $this->getService(UrlFormatter::class)->href('', 'admin/sign-in/' . rawurlencode($id) . '/test'),
                'errors' => $errors,
            ],
        ]);
    }

    /**
     * The add and edit screen. A secret is never put in it: the field starts empty, and a stored one is only announced.
     *
     * @param array<string, mixed> $entry  the entry as stored; empty for a new provider
     * @param array<string, mixed> $posted what was just sent, shown again when it was refused
     * @param list<string>         $errors
     */
    private function form(ProviderType $type, ?string $id, array $entry, array $posted, array $errors): Response
    {
        $stored = is_array($entry['connection'] ?? null) ? $entry['connection'] : [];
        $postedConnection = is_array($posted['connection'] ?? null) ? $posted['connection'] : [];
        $settings = [];
        foreach ($type->connectionSettings() as $setting) {
            $declared = $setting->toArray();
            $name = $setting->name();
            $kind = (string)$setting->type();
            if (in_array($kind, ['divider', 'hint'], true)) {
                continue;
            }
            $value = $kind === 'secret' ? '' : ($postedConnection[$name] ?? $stored[$name] ?? '');
            $settings[] = [
                'name' => $name,
                'kind' => $kind,
                'label' => (string)($declared['label'] ?? $name),
                'hint' => (string)($declared['hint'] ?? ''),
                'required' => !empty($declared['required']),
                'placeholder' => (string)($declared['default'] ?? ''),
                'options' => is_array($declared['options'] ?? null) ? $declared['options'] : [],
                'value' => is_scalar($value) ? (string)$value : '',
                'checked' => $kind === 'checkbox' && filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'secretStored' => $kind === 'secret' && isset($stored[$name]) && $stored[$name] !== '',
                'multiline' => !empty($declared['multiline']),
            ];
        }

        $source = $posted === [] ? $entry : $posted;
        $attributes = [];
        foreach (ProvidersEditor::ATTRIBUTES as $name) {
            $attributes[] = [
                'name' => $name,
                'value' => (string)(($source['attributes'] ?? [])[$name] ?? ''),
                'placeholder' => $type->defaultAttributes()[$name] ?? '',
            ];
        }

        $mapping = [];
        foreach (is_array($source['group_mapping'] ?? null) ? $source['group_mapping'] : [] as $row) {
            if (is_array($row)) {
                $mapping[] = ['provider' => (string)($row['provider'] ?? ''), 'wiki' => (string)($row['wiki'] ?? '')];
            }
        }
        $extras = [];
        $extraSource = is_array($source['extra_fields'] ?? null) ? $source['extra_fields'] : [];
        foreach ($extraSource as $key => $row) {
            // stored as field => attribute; posted as rows.
            $extras[] = is_array($row)
                ? ['field' => (string)($row['field'] ?? ''), 'attribute' => (string)($row['attribute'] ?? '')]
                : ['field' => (string)$key, 'attribute' => (string)$row];
        }

        $callback = null;
        $metadata = null;
        $copyable = [];
        if ($id !== null) {
            $config = ProviderConfig::fromArray($id, $entry, $type->defaultAttributes());
            $callback = $this->takesPassword($type, $config) ? null : $this->getService(SignInFlow::class)->callbackUrlFor($config);
            $published = $this->published($type, $config);
            if ($published !== null) {
                $metadata = $this->getService(UrlFormatter::class)->href('', 'user/sign-in/' . rawurlencode($id) . '/metadata');
                $copyable = $published;
            }
        }

        $urls = $this->getService(UrlFormatter::class);

        return $this->page('@core/admin/sign-in-edit.twig', self::CURRENT, [
            'isNew' => $id === null,
            'id' => $id,
            'action' => $urls->href('', $id === null ? 'admin/sign-in/new/' . rawurlencode($type->name()) : 'admin/sign-in/' . rawurlencode($id) . '/edit'),
            'typeLabel' => $type->label(),
            'label' => (string)($source['label'] ?? ''),
            'enabled' => array_key_exists('enabled', $source) ? filter_var($source['enabled'], FILTER_VALIDATE_BOOLEAN) : true,
            'settings' => $settings,
            'callback' => $callback,
            'metadata' => $metadata,
            'copyable' => $copyable,
            'attributes' => $attributes,
            'createAccounts' => array_key_exists('create_accounts', $source) ? filter_var($source['create_accounts'], FILTER_VALIDATE_BOOLEAN) : true,
            'matchByEmail' => array_key_exists('match_by_email', $source) && filter_var($source['match_by_email'], FILTER_VALIDATE_BOOLEAN),
            'groupMapping' => $mapping,
            'extraFields' => $extras,
            'userFields' => $this->userFields(array_column($extras, 'field')),
            'errors' => $errors,
        ]);
    }

    /**
     * What a provider publishing metadata gives the administrator to copy, as label and value rows; null when it publishes nothing.
     * A provider still half configured may fail to be built: the page then shows nothing to copy, and stays there to fix it.
     *
     * @return ?list<array{label: string, value: string}>
     */
    private function published(ProviderType $type, ProviderConfig $config): ?array
    {
        try {
            $provider = $type->create($config);
            if (!$provider instanceof PublishesMetadata) {
                return null;
            }
            $rows = [];
            foreach ($provider->copyableValues() as $label => $value) {
                $rows[] = ['label' => (string)$label, 'value' => (string)$value];
            }

            return $rows;
        } catch (\Throwable $unbuilt) {
            return null;
        }
    }

    /** A provider checking a password typed into the wiki's form sends nobody back: it has no callback to give. One that cannot be built yet keeps it shown. */
    private function takesPassword(ProviderType $type, ProviderConfig $config): bool
    {
        try {
            return $type->create($config) instanceof PasswordProvider;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * The User form's own fields, minus the ones the account always has.
     *
     * @param list<string> $alsoKept fields an extra attribute already names, listed even if the form no longer has them
     *
     * @return list<array{name: string, label: string}>
     */
    private function userFields(array $alsoKept): array
    {
        $form = $this->getService(FormManager::class)->getByContentType(ContentTypeSchema::TYPE_USER);
        $skipped = [ContentTypeSchema::tagMirrorField(ContentTypeSchema::TYPE_USER), 'password', 'email'];
        $fields = [];
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof BazarField) {
                continue;
            }
            $name = $field->getPropertyName();
            if ($name !== null && $name !== '' && !in_array($name, $skipped, true)) {
                $fields[$name] = ['name' => $name, 'label' => (string)($field->getLabel() ?: $name)];
            }
        }
        foreach ($alsoKept as $name) {
            if ($name !== '' && !isset($fields[$name])) {
                $fields[$name] = ['name' => $name, 'label' => $name];
            }
        }

        return array_values($fields);
    }

    /** @return list<string> */
    private function guardedAgainstForgery(): array
    {
        try {
            $this->getService(CsrfTokenChecker::class)->checkToken('main', 'POST', 'csrf-token', false);
        } catch (TokenNotFoundException $invalid) {
            return [_t('SIGN_IN_ADMIN_NOT_SAVED') . ' ' . $invalid->getMessage()];
        }

        return [];
    }

    /**
     * Back to the list after Save; on to the provider's test after "Save and Test".
     *
     * @param array<string, mixed> $posted
     */
    private function afterSave(string $id, array $posted): RedirectResponse
    {
        if (($posted['then'] ?? null) !== 'test') {
            return $this->toIndex();
        }

        return new RedirectResponse($this->getService(UrlFormatter::class)->href('', self::CURRENT . '/' . rawurlencode($id) . '/test'));
    }

    private function toIndex(): RedirectResponse
    {
        return new RedirectResponse($this->getService(UrlFormatter::class)->href('', self::CURRENT));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function page(string $template, string $current, array $data = []): Response
    {
        $this->getService(PageContext::class)->setTag($current);

        $templateEngine = $this->getService(TemplateEngine::class);

        return new Response($templateEngine->renderPage($templateEngine->render($template, $this->dashboardShell($current, $data))));
    }
}
