<?php

namespace YesWiki\Core\Controller;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Core\Field\MapField;
use YesWiki\Core\Service\ActivityPubService;
use YesWiki\Core\Service\FormManager;
use YesWiki\Core\Service\Guard;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\WebfingerService;
use YesWiki\Core\YesWikiController;

class FormController extends YesWikiController
{
    protected $csrfTokenController;
    protected $formManager;
    protected $hibernationService;
    protected $activityPubService;
    protected $webfingerService;

    public function __construct(FormManager $formManager, HibernationService $hibernationService, CsrfTokenController $csrfTokenController, ActivityPubService $activityPubService, WebfingerService $webfingerService)
    {
        $this->csrfTokenController = $csrfTokenController;
        $this->formManager = $formManager;
        $this->hibernationService = $hibernationService;
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
                $values[$form['bn_id_nature']]['canEdit'] = !$this->hibernationService->isWikiHibernated() && $this->getService(Guard::class)->isAllowed('saisie_formulaire');
                $values[$form['bn_id_nature']]['canDelete'] = !$this->hibernationService->isWikiHibernated() && $this->wiki->UserIsAdmin();
                $values[$form['bn_id_nature']]['isSemantic'] = !empty($form['bn_sem_template']);
                $values[$form['bn_id_nature']]['isActivityPubEnabled'] = $form['bn_activitypub_enable'] === '1';
                $values[$form['bn_id_nature']]['isGeo'] = !empty(array_filter($form['prepared'], function ($field) {
                    return $field instanceof MapField;
                }));
                $values[$form['bn_id_nature']]['isDate'] = $this->getService(IcalFormatter::class)->isICALForm($form);
            }
        }

        return $this->render('@core/forms/forms_table.twig', [
            'message' => $message,
            'forms' => $values,
            'userIsAdmin' => $this->wiki->UserIsAdmin(),
            'isWikiHibernated' => $this->hibernationService->isWikiHibernated(),
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
                    $this->formManager->update($post->all());

                    return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_MODIFIE'], false));
                }
            }

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
        $titleFields = array_filter($form['prepared'], function ($field) {
            return $field->getPropertyName() == 'bf_titre';
        });
        if (count($titleFields) == 0) {
            Flash::error(_t('BAZ_FORM_NEED_TITLE'));

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
