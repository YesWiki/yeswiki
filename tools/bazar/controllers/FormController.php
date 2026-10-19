<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Bazar\Field\MapField;
use YesWiki\Bazar\Service\ActivityPubInbox;
use YesWiki\Bazar\Service\ActivityPubProfiles;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\Guard;
use YesWiki\Bazar\Service\WebfingerService;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\YesWikiController;
use YesWiki\Security\Controller\SecurityController;

class FormController extends YesWikiController
{
    protected $csrfTokenController;
    protected $formManager;
    protected $securityController;
    protected $activityPubService;
    protected $webfingerService;

    public function __construct(FormManager $formManager, SecurityController $securityController, CsrfTokenController $csrfTokenController, ActivityPubService $activityPubService, WebfingerService $webfingerService)
    {
        $this->csrfTokenController = $csrfTokenController;
        $this->formManager = $formManager;
        $this->securityController = $securityController;
        $this->activityPubService = $activityPubService;
        $this->webfingerService = $webfingerService;
    }

    public function displayAll($message)
    {
        $forms = $this->formManager->getAll();

        $post = $this->getRequest()->request;
        // If there are forms to import
        if ($post->has('imported-form')) {
            if (!$this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
                return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
            }
            foreach ($post->all('imported-form') as $id => $value) {
                $value = json_decode($value, true);
                $invalidNames = $this->formManager->invalidPropertyNames($this->formManager->getFromRawData($value));
                if (!empty($invalidNames)) {
                    Flash::error(htmlspecialchars($value['bn_label_nature'] ?? '') . ' : ' . _t('BAZ_FORM_INVALID_FIELD_NAMES', ['names' => htmlspecialchars(implode(', ', $invalidNames))]));
                    continue;
                }
                $existingForms = multiArraySearch($forms, 'bn_label_nature', $value['bn_label_nature']);
                // If a form with the same name exist, replace it
                if (count($existingForms) > 0) {
                    // Replace with ID of existing formulaire
                    $value['bn_id_nature'] = $existingForms[0]['bn_id_nature'];
                    $this->formManager->update($value);
                } else {
                    $value['bn_id_nature'] = $id;
                    $this->formManager->create($value);
                }
            }

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORM_IMPORT_SUCCESSFULL'], false));
        }

        $values = [];
        if (is_array($forms)) {
            foreach ($forms as $form) {
                $values[$form['bn_id_nature']]['title'] = $form['bn_label_nature'];
                $values[$form['bn_id_nature']]['description'] = $form['bn_description'];
                $values[$form['bn_id_nature']]['canEdit'] = !$this->securityController->isWikiHibernated() && $this->getService(Guard::class)->isAllowed('saisie_formulaire');
                $values[$form['bn_id_nature']]['canDelete'] = !$this->securityController->isWikiHibernated() && $this->wiki->UserIsAdmin();
                $values[$form['bn_id_nature']]['isSemantic'] = !empty($form['bn_sem_template']);
                $values[$form['bn_id_nature']]['isActivityPubEnabled'] = $form['bn_activitypub_enable'] === '1';
                $values[$form['bn_id_nature']]['isGeo'] = !empty(array_filter($form['prepared'], function ($field) {
                    return $field instanceof MapField;
                }));
                $values[$form['bn_id_nature']]['isDate'] = $this->getService(IcalFormatter::class)->isICALForm($form);
            }
        }

        return $this->render('@bazar/forms/forms_table.twig', [
            'message' => $message,
            'forms' => $values,
            'userIsAdmin' => $this->wiki->UserIsAdmin(),
            'isWikiHibernated' => $this->securityController->isWikiHibernated(),
        ]);
    }

    public function create()
    {
        if ($this->wiki->UserIsAdmin()) {
            $form = null;
            $post = $this->getRequest()->request;
            if ($post->has('valider')) {
                $form = $this->formManager->getFromRawData($post->all());
                if ($this->formIsValid($form)) {
                    $this->formManager->create($post->all());

                    /* mrflos : i think this is not used */
                    /* if ($this->activityPubService->isEnabled($form)) { */
                    /*     $this->activityPubService->postCreateActivity($form); */
                    /* } */

                    return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NOUVEAU_FORMULAIRE_ENREGISTRE'], false));
                }
            }

            return $this->render('@bazar/forms/forms_form.twig', [
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
                    $this->formManager->update($post->all());

                    return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_MODIFIE'], false));
                }
            }

            return $this->render('@bazar/forms/forms_form.twig', [
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
        $titleFields = array_filter($form['prepared'], function ($field) {
            return $field->getPropertyName() == 'bf_titre';
        });
        if (count($titleFields) == 0) {
            Flash::error(_t('BAZ_FORM_NEED_TITLE'));

            return false;
        }
        $invalidNames = $this->formManager->invalidPropertyNames($form);
        if (!empty($invalidNames)) {
            Flash::error(_t('BAZ_FORM_INVALID_FIELD_NAMES', ['names' => htmlspecialchars(implode(', ', $invalidNames))]));

            return false;
        }

        return true;
    }

    public function delete($id)
    {
        if ($this->wiki->UserIsAdmin()) {
            try {
                $this->csrfTokenController->checkToken('main', 'POST', 'confirmDeleteToken', false);
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
                $this->csrfTokenController->checkToken('main', 'POST', 'confirmEmptyToken', false);
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
        if (!$this->wiki->UserIsAdmin()) {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
        $form = $this->formManager->getOne($id);
        if (empty($form)) {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire'], false));
        }

        $post = $this->getRequest()->request;
        if ($post->has('actor_handle') || $post->has('activitypub_username')) {
            $tab = $post->has('activitypub_username') ? 'profile' : 'following';
            if ($this->refusesChanges()) {
                return $this->redirectToActivityPub($id, $tab);
            }
            try {
                $this->csrfTokenController->checkToken('main', 'POST', 'csrf-token', false);
                if ($tab === 'profile') {
                    $this->saveActivityPubProfile($form);
                    Flash::success(_t('BAZ_ACTIVITYPUB_PROFILE_SAVED'));
                } else {
                    $this->followHandle($form, trim((string)$post->get('actor_handle')));
                    Flash::success(_t('BAZ_FOLLOWING_ADDED'));
                }
            } catch (\Exception $e) {
                Flash::error(htmlspecialchars($e->getMessage()));
            }

            return $this->redirectToActivityPub($id, $tab);
        }

        $profile = $this->getService(ActivityPubProfiles::class)->get($id);
        $followRequests = $this->activityPubService->getFollowRequests($form);
        $tab = $this->getRequest()->query->get('tab');
        if (!in_array($tab, ['profile', 'following', 'followers'], true)) {
            $tab = $profile['username'] === '' ? 'profile' : (empty($followRequests) ? 'following' : 'followers');
        }

        return $this->render('@bazar/forms/activitypub.twig', [
            'message' => $this->getRequest()->query->get('msg'),
            'form' => $form,
            'tab' => $tab,
            'profile' => $profile,
            'suggestedUsername' => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(removeAccents((string)$form['bn_label_nature']))), '_'),
            'domain' => parse_url($this->wiki->GetBaseURL(), PHP_URL_HOST),
            'actorUri' => $this->activityPubService->getFormActorUri($form),
            'isEnabled' => $this->activityPubService->isEnabled($form),
            'followers' => $this->activityPubService->getFollowers($form),
            'followRequests' => $followRequests,
            'following' => $this->activityPubService->getFollowing($form),
            'pendingFollowing' => $this->activityPubService->getPendingFollowing($form),
            'maxFields' => ActivityPubProfiles::MAX_FIELDS,
        ]);
    }

    /**
     * Saves the profile posted from the ActivityPub page, storing any uploaded avatar or banner first.
     */
    private function saveActivityPubProfile($form): void
    {
        $profiles = $this->getService(ActivityPubProfiles::class);
        $post = $this->getRequest()->request;
        $profile = [
            'username' => $post->get('activitypub_username'),
            'summary' => $post->get('activitypub_summary'),
            'icon' => $post->get('activitypub_icon'),
            'image' => $post->get('activitypub_image'),
            'approve_followers' => $post->has('activitypub_approve_followers'),
            'fields' => [],
        ];
        $names = $post->all('activitypub_field_name');
        $values = $post->all('activitypub_field_value');
        foreach ($names as $index => $name) {
            $profile['fields'][(string)$name] = (string)($values[$index] ?? '');
        }
        foreach (['icon', 'image'] as $role) {
            $upload = $this->getRequest()->files->get('activitypub_' . $role . '_file');
            if ($upload instanceof UploadedFile && $upload->getError() !== UPLOAD_ERR_NO_FILE) {
                $profile[$role] = $profiles->storeImage($form['bn_id_nature'], $role, $upload);
            }
        }
        $profiles->save($form['bn_id_nature'], $profile);
    }

    private function followHandle($form, string $actorHandle): void
    {
        $recipientUri = str_starts_with($actorHandle, 'http') ? $actorHandle : $this->webfingerService->getRemoteActor($actorHandle);
        if (empty($recipientUri)) {
            throw new \Exception(_t('BAZ_ACTIVITYPUB_ACTOR_NOT_FOUND', ['handle' => $actorHandle]));
        }
        $this->activityPubService->follow($form, $recipientUri);
    }

    private function redirectToActivityPub($id, string $tab, ?string $message = null)
    {
        return $this->wiki->redirect($this->wiki->href('', '', array_filter([
            'vue' => 'abonnements',
            'action' => 'list',
            'idformulaire' => $id,
            'tab' => $tab,
            'msg' => $message,
        ]), false));
    }

    /**
     * Whether the current user may not change the form's ActivityPub account, with the reason flashed.
     */
    private function refusesChanges(): bool
    {
        if (!$this->wiki->UserIsAdmin()) {
            Flash::error(_t('BAZ_NEED_ADMIN_RIGHTS'));

            return true;
        }
        if ($this->securityController->isWikiHibernated()) {
            Flash::error(_t('WIKI_IN_HIBERNATION'));

            return true;
        }

        return false;
    }

    public function addFollowing($id, $actorUri)
    {
        if ($this->refusesChanges()) {
            return $this->redirectToActivityPub($id, 'following');
        }
        try {
            $this->activityPubService->follow($this->formManager->getOne($id), $actorUri);
        } catch (\Exception $e) {
            Flash::error(htmlspecialchars($e->getMessage()));

            return $this->redirectToActivityPub($id, 'following');
        }

        return $this->redirectToActivityPub($id, 'following', 'BAZ_FOLLOWING_ADDED');
    }

    public function removeFollowing($id, $actorUri)
    {
        if ($this->refusesChanges()) {
            return $this->redirectToActivityPub($id, 'following');
        }
        try {
            $this->activityPubService->unfollow($this->formManager->getOne($id), $actorUri);
        } catch (\Exception $e) {
            Flash::error(htmlspecialchars($e->getMessage()));
        }

        return $this->redirectToActivityPub($id, 'following', 'BAZ_FOLLOWING_REMOVED');
    }

    public function syncActorPosts($id, $actorUri)
    {
        if ($this->refusesChanges()) {
            return $this->redirectToActivityPub($id, 'following');
        }
        try {
            $this->csrfTokenController->checkToken('main', 'GET', 'synctoken', false);
        } catch (TokenNotFoundException $th) {
            Flash::error(htmlspecialchars($th->getMessage()));

            return $this->redirectToActivityPub($id, 'following');
        }

        $stats = $this->getService(ActivityPubInbox::class)->syncActorPosts($actorUri, $this->formManager->getOne($id));
        Flash::success(sprintf(_t('BAZ_SYNC_COMPLETE'), $stats['created'], $stats['updated'], $stats['deleted']));
        if ($stats['failed'] > 0) {
            Flash::warning(sprintf(_t('BAZ_SYNC_FAILED'), $stats['failed']));
        }

        return $this->redirectToActivityPub($id, 'following');
    }

    /**
     * Accepts a pending follow request, posted from the followers tab.
     */
    public function acceptFollower($id, $actorUri)
    {
        if ($this->refusesChanges()) {
            return $this->redirectToActivityPub($id, 'followers');
        }
        try {
            $this->csrfTokenController->checkToken('main', 'POST', 'csrf-token', false);
            $this->activityPubService->acceptFollower($this->formManager->getOne($id), (string)$actorUri);
            Flash::success(_t('BAZ_ACTIVITYPUB_FOLLOWER_ACCEPTED'));
        } catch (\Exception $e) {
            Flash::error(htmlspecialchars($e->getMessage()));
        }

        return $this->redirectToActivityPub($id, 'followers');
    }

    /**
     * Refuses a pending follow request or removes a follower, telling the remote actor.
     */
    public function removeFollower($id, $actorUri)
    {
        if ($this->refusesChanges()) {
            return $this->redirectToActivityPub($id, 'followers');
        }
        try {
            $this->activityPubService->rejectFollower($this->formManager->getOne($id), (string)$actorUri);
        } catch (\Exception $e) {
            Flash::error(htmlspecialchars($e->getMessage()));
        }

        return $this->redirectToActivityPub($id, 'followers', 'BAZ_FOLLOWER_REMOVED');
    }

    private function getGroupsListIfEnabled(): ?array
    {
        return $this->wiki->UserIsAdmin()
            ? $this->wiki->GetGroupsList()
            : null;
    }
}
