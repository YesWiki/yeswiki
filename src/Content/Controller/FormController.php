<?php

namespace YesWiki\Content\Controller;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Content\Field\MapField;
use YesWiki\Content\Service\ActivityPubService;
use YesWiki\Identity\Service\CsrfTokenChecker;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\FormPropertiesService;
use YesWiki\Identity\Service\Guard;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Content\Service\IcalFormatter;
use YesWiki\Core\Service\LanguageService;
use YesWiki\Content\Service\WebfingerService;
use YesWiki\Core\YesWikiController;

class FormController extends YesWikiController
{
    protected $csrfTokenChecker;
    protected $formManager;
    protected $hibernationService;
    protected $activityPubService;
    protected $webfingerService;

    public function __construct(FormManager $formManager, HibernationService $hibernationService, CsrfTokenChecker $csrfTokenChecker, ActivityPubService $activityPubService, WebfingerService $webfingerService)
    {
        $this->csrfTokenChecker = $csrfTokenChecker;
        $this->formManager = $formManager;
        $this->hibernationService = $hibernationService;
        $this->activityPubService = $activityPubService;
        $this->webfingerService = $webfingerService;
    }

    public function displayAll($message)
    {
        $forms = $this->formManager->getAll();

        $values = [];
        if (is_array($forms)) {
            foreach ($forms as $form) {
                $values[$form['id']]['title'] = $form['label'];
                $values[$form['id']]['description'] = $form['description'];
                $values[$form['id']]['canEdit'] = !$this->hibernationService->isWikiHibernated() && $this->getService(Guard::class)->isAllowed('saisie_formulaire');
                $values[$form['id']]['canDelete'] = !$this->hibernationService->isWikiHibernated() && $this->wiki->UserIsAdmin();
                $values[$form['id']]['isSemantic'] = !empty($form['sem_template']);
                $values[$form['id']]['isActivityPubEnabled'] = $form['activitypub_enable'] === '1';
                $values[$form['id']]['isGeo'] = !empty(array_filter($form['prepared'], function ($field) {
                    return $field instanceof MapField;
                }));
                $values[$form['id']]['isDate'] = $this->getService(IcalFormatter::class)->isICALForm($form);
                $values[$form['id']]['bookmarklet'] = $form['entry_bookmarklet'] ?? null;
            }
        }

        return $this->render('@core/forms/forms_table.twig', [
            'message' => $message,
            'forms' => $values,
            'userIsAdmin' => $this->wiki->UserIsAdmin(),
            'isWikiHibernated' => $this->hibernationService->isWikiHibernated(),
        ]);
    }

    /**
     * The form designer (javascripts/form-builder/) is pure JS and reads its labels
     * from wiki.lang; its keys live in the main PHP catalogs, so push them into the
     * javascript catalog when rendering the designer page.
     */
    private function loadDesignerTranslations(): void
    {
        $prefixes = ['BAZ_FORM_', 'FORM_BUILDER_', 'FORM_EDIT_', 'BAZ_REACTIONS_', 'BAZAR_VIDEO_'];
        $names = [
            'BAZ_ACTIVATE_COMMENTS', 'BAZ_ACTIVATE_COMMENTS_HINT', 'BAZ_ACTIVATE_REACTIONS',
            'BAZAR_URL_DISPLAY_VIDEO', 'BAZ_BOOKMARKLET_HINT', 'BAZ_FILEFIELD_FILE',
            'GEOLOCATER_GROUP_GEOLOCATIZATION', 'GEOLOCATER_GROUP_GEOLOCATIZATION_HINT',
            'EVERYONE', 'IDENTIFIED_USERS', 'MEMBER_OF_GROUP',
            'NO', 'YES', 'LEFT', 'RIGHT', 'PRIMARY', 'SECONDARY', 'NORMAL_F', 'SMALL_F',
        ];
        $designerKeys = array_filter(
            $GLOBALS['translations'] ?? [],
            function ($key) use ($prefixes, $names) {
                foreach ($prefixes as $prefix) {
                    if (str_starts_with($key, $prefix)) {
                        return true;
                    }
                }

                return in_array($key, $names, true);
            },
            ARRAY_FILTER_USE_KEY,
        );
        $this->getService(LanguageService::class)->loadTranslations($designerKeys, true);
    }

    /**
     * Massages the form-edit POST into the stored entry_* property shapes (ADR-0010):
     * checkbox-gated nested objects become arrays-or-null, empty sub-values are
     * compacted away, the comments toggle becomes a real boolean. A null/empty value
     * means "cleared" -- FormManager::update() removes the property from the body.
     */
    private function normalizeFormPropertiesPost(array $data): array
    {
        $data['entry_permit_activate_comments'] = !empty($data['entry_permit_activate_comments']);

        $metadatas = array_filter(array_map(function ($value) {
            return trim((string)$value);
        }, (array)($data['entry_metadatas'] ?? [])), function ($value) {
            return $value !== '';
        });
        $data['entry_metadatas'] = $metadatas ?: null;

        foreach (['entry_creates_user', 'entry_bookmarklet'] as $property) {
            if (empty($data[$property . '_enable'])) {
                $data[$property] = null;
            } else {
                $config = array_filter(array_map(function ($value) {
                    return trim((string)$value);
                }, (array)($data[$property] ?? [])), function ($value) {
                    return $value !== '';
                });
                $data[$property] = $config ?: null;
            }
            unset($data[$property . '_enable']);
        }

        return $data;
    }

    public function create()
    {
        if ($this->wiki->UserIsAdmin()) {
            $form = null;
            $post = $this->getRequest()->request;
            if ($post->has('valider')) {
                $form = $this->formManager->getFromRawData($post->all());
                if ($this->formIsValid($form)) {
                    $this->formManager->create($this->normalizeFormPropertiesPost($post->all()));

                    /* mrflos : i think this is not used */
                    /* if ($this->activityPubService->isEnabled($form)) { */
                    /*     $this->activityPubService->postCreateActivity($form); */
                    /* } */

                    return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NOUVEAU_FORMULAIRE_ENREGISTRE'], false));
                }
            }

            $this->loadDesignerTranslations();

            return $this->render('@core/forms/forms_form.twig', [
                'form' => $form,
                'formAndListIds' => baz_forms_and_lists_ids(),
                'groupsList' => $this->getGroupsListIfEnabled(),
                'onlyOneEntryOptionAvailable' => $this->formManager->isAvailableOnlyOneEntryOption(),
            ]);
        }

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
    }

    public function update($id)
    {
        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            $form = $this->formManager->getOne($id);
            $post = $this->getRequest()->request;
            if ($post->has('valider')) {
                $form = $this->formManager->getFromRawData($post->all());
                if ($this->formIsValid($form)) {
                    $this->formManager->update($this->normalizeFormPropertiesPost($post->all()));

                    return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_MODIFIE'], false));
                }
            }

            $this->loadDesignerTranslations();

            return $this->render('@core/forms/forms_form.twig', [
                'form' => $form,
                'formAndListIds' => baz_forms_and_lists_ids(),
                'groupsList' => $this->getGroupsListIfEnabled(),
                'onlyOneEntryOptionAvailable' => $this->formManager->isAvailableOnlyOneEntryOption() && $this->formManager->isAvailableOnlyOneEntryMessage(),
            ]);
        }

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
    }

    private function formIsValid($form)
    {
        // the entry title is computed from entry_title_template (ADR-0010): at least
        // one of its {{field}} references must exist in the template, otherwise every
        // entry title would come out empty
        $titleTemplate = trim((string)($this->getRequest()->request->get('entry_title_template') ?? $form['entry_title_template'] ?? ''));
        if ($titleTemplate === '') {
            $titleTemplate = FormPropertiesService::DEFAULT_TITLE_TEMPLATE;
        }
        $referenced = $this->getService(FormPropertiesService::class)->referencedFieldNames($titleTemplate);
        $fieldNames = array_filter(array_map(function ($field) {
            return $field->getPropertyName();
        }, $form['prepared']));
        $referencesExistingField = !empty(array_intersect($referenced, $fieldNames));
        if (!$referencesExistingField) {
            Flash::error(_t('BAZ_FORM_NEED_TITLE'));

            return false;
        }

        return true;
    }

    public function delete($id)
    {
        if ($this->wiki->UserIsAdmin()) {
            try {
                $this->csrfTokenChecker->checkToken('main', 'POST', 'confirmDeleteToken', false);
                $this->formManager->clear($id);
                $this->formManager->delete($id);

                return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_ET_FICHES_SUPPRIMES'], false));
            } catch (TokenNotFoundException $th) {
                $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => $th->getMessage()], false));
            }
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    public function empty($id)
    {
        if ($this->wiki->UserIsAdmin()) {
            try {
                $this->csrfTokenChecker->checkToken('main', 'POST', 'confirmEmptyToken', false);
                $this->formManager->clear($id);

                return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_VIDE'], false));
            } catch (TokenNotFoundException $th) {
                $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => $th->getMessage()], false));
            }
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    public function clone($id)
    {
        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            $this->formManager->clone($id);

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORM_CLONED'], false));
        }

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
    }

    public function manageAbonnements($id)
    {
        $form = $this->formManager->getOne($id);

        $post = $this->getRequest()->request;
        if ($post->has('actor_handle')) {
            if (!$this->wiki->UserIsAdmin() || $this->hibernationService->isWikiHibernated()) {
                return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'idformulaire' => $id], false));
            }

            $actorHandle = $post->get('actor_handle');
            $recipientUri = str_starts_with($actorHandle, 'http') ? $actorHandle : $this->webfingerService->getRemoteActor($actorHandle);

            $this->activityPubService->postActivity(['type' => 'Follow', 'object' => $recipientUri, 'to' => $recipientUri], $form);

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_ADDED', 'idformulaire' => $id], false));
        }

        $followers = $this->activityPubService->getFollowers($form);
        $following = $this->activityPubService->getFollowing($form);

        $domain = parse_url($this->wiki->GetBaseURL(), PHP_URL_HOST);

        return $this->render('@core/forms/abonnements.twig', [
            'message' => $this->getRequest()->query->get('msg'),
            'form' => $form,
            'domain' => $domain,
            'followers' => $followers,
            'following' => $following,
        ]);
    }

    public function addFollowing($id, $actorUri)
    {
        if (!$this->wiki->UserIsAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'idformulaire' => $id], false));
        }

        $form = $this->formManager->getOne($id);

        $this->activityPubService->postActivity(['type' => 'Follow', 'object' => $actorUri, 'to' => $actorUri], $form);

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_ADDED', 'idformulaire' => $id], false));
    }

    public function removeFollowing($id, $actorUri)
    {
        if (!$this->wiki->UserIsAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'idformulaire' => $id], false));
        }

        $form = $this->formManager->getOne($id);
        $formActorUri = $this->activityPubService->getFormActorUri($form);

        $this->activityPubService->removeFollowing($form, $actorUri);

        $this->activityPubService->postActivity([
            'type' => 'Undo',
            'object' => [
                'type' => 'Follow',
                'actor' => $formActorUri,
                'object' => $actorUri,
                'to' => $actorUri,
            ],
            'to' => $actorUri,
        ], $form);

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_REMOVED', 'idformulaire' => $id], false));
    }

    public function syncActorPosts($id, $actorUri)
    {
        if (!$this->wiki->UserIsAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'idformulaire' => $id], false));
        }

        $form = $this->formManager->getOne($id);
        $stats = $this->activityPubService->syncActorPosts($actorUri, $form);

        Flash::success(sprintf(
            _t('BAZ_SYNC_COMPLETE'),
            $stats['created'],
            $stats['updated'],
            $stats['deleted'],
        ));

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'idformulaire' => $id], false));
    }

    public function removeFollower($id, $actorUri)
    {
        if (!$this->wiki->UserIsAdmin() || $this->hibernationService->isWikiHibernated()) {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS', 'idformulaire' => $id], false));
        }

        $form = $this->formManager->getOne($id);
        $formActorUri = $this->activityPubService->getFormActorUri($form);

        $this->activityPubService->removeFollower($form, $actorUri);

        $this->activityPubService->postActivity([
            'type' => 'Undo',
            'object' => [
                'type' => 'Accept',
                'actor' => $formActorUri,
                'object' => [
                    'type' => 'Follow',
                    'actor' => $formActorUri,
                    'object' => $actorUri,
                    'to' => $actorUri,
                ],
                'to' => $actorUri,
            ],
            'to' => $actorUri,
        ], $form);

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWER_REMOVED', 'idformulaire' => $id], false));
    }

    private function getGroupsListIfEnabled(): ?array
    {
        return $this->wiki->UserIsAdmin()
            ? $this->wiki->GetGroupsList()
            : null;
    }
}
