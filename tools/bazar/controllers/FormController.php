<?php

namespace YesWiki\Bazar\Controller;

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Tamtamchik\SimpleFlash\Flash;
use YesWiki\Bazar\Field\MapField;
use YesWiki\Bazar\Service\ActivityPubService;
use YesWiki\Bazar\Service\WebfingerService;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\Guard;
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
    protected $lang;

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
                $existingForms = multiArraySearch($forms, 'title', $value['title']);
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
                $values[$form['id']]['title'] = $form['title'];
                $values[$form['id']]['description'] = $form['description'];
                $values[$form['id']]['canEdit'] = !$this->securityController->isWikiHibernated() && $this->getService(Guard::class)->isAllowed('saisie_formulaire');
                $values[$form['id']]['canDelete'] = !$this->securityController->isWikiHibernated() && $this->wiki->UserIsAdmin();
                $values[$form['id']]['isSemantic'] = isset($form['bn_sem_type']) && $form['bn_sem_type'] !== '';
                $values[$form['id']]['isGeo'] = !empty(array_filter($form['prepared'], function ($field) {
                    return $field instanceof MapField;
                }));
                $values[$form['id']]['isDate'] = $this->getService(IcalFormatter::class)->isICALForm($form);
            }
        }

        return $this->render('@bazar/forms/forms_table.twig', [
            'message' => $message,
            'forms' => $values,
            'userIsAdmin' => $this->wiki->UserIsAdmin(),
            'isMultilang' => isset($this->wiki->config['supported_langs']),
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

                    /* mrflos : i think this is not used*/
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
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
        }
    }

    public function update($id)
    {
        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            $form = $this->formManager->getOne($id);

            $tag = $form['tag'] ?? $form['bn_label_nature'];
            $post = $this->getRequest()->request;

            if ($post->has('valider')) {
            $form = $this->formManager->getFromRawData($post->all());
            if ($this->formIsValid($form)) {
                $this->formManager->update($post->all(), $tag);

                return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_FORMULAIRE_MODIFIE'], false));
            }
        }

            return $this->render('@bazar/forms/forms_form.twig', [
                'form' => $form,
                'formAndListIds' => baz_forms_and_lists_ids(),
                'groupsList' => $this->getGroupsListIfEnabled(),
                'onlyOneEntryOptionAvailable' => $this->formManager->isAvailableOnlyOneEntryOption() && $this->formManager->isAvailableOnlyOneEntryMessage(),
            ]);
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
    }

    public function translate($tag)
    {
        if (is_numeric($tag)) {
            $tag = $this->formManager->getPageTagFromId($tag);
        }

        $req = $this->getRequest()->request;

        if ($this->getRequest()->getMethod() === 'POST') {
            if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {


                $form = json_decode($req->get('form'), true);
                $form['extralang'] = json_decode($req->get('extraLangs'), true);

                $saved = $this->formManager->update($form, $req->get('tag') , true);



                if ($req->get('onsubmit') === 'postmessage') {
                    return $this->render('@core/iframe_result.twig', [
                        'data' => ['msg' => 'form_updated', 'id' => $tag, 'title' => $form['title']],
                    ]);
                }

                $this->wiki->Redirect(
                    $this->wiki->Href('', '', [BAZ_VARIABLE_VOIR => BAZ_VOIR_FORMULAIRE], false)
                );
            } else {
                throw new \Exception('Not allowed');
            }
        }

        if ($this->getService(Guard::class)->isAllowed('saisie_formulaire')) {
            $form = $this->formManager->getOne($tag, 'all');
            unset($form['template']);
            unset($form['prepared']);
            if ($form['extralang'] === '') {
                unset($form['extralang']);
            }
            $default_lang = $this->wiki->config['default_language'] ?? 'fr';
            return $this->render('@bazar/forms/form_translate.twig', [
                'form' => $form,
                'defaultLanguage' => $default_lang,
                'langs' => array_filter($this->wiki->config['supported_langs'], function($el) use ($default_lang) {
                    return $el != $default_lang;
                }),
                 'tag' =>  $form['tag'],
                 'translatable_fields' => ['label', 'default', 'helper'],
             ]);
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_NEED_ADMIN_RIGHTS'], false));
        }
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
        } else {
            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'formulaire', 'msg' => 'BAZ_AUTH_NEEDED'], false));
        }
    }

    public function manageAbonnements($id)
    {
        $form = $this->formManager->getOne($id);

        $post = $this->getRequest()->request;
        if ($post->has('actor_handle')) {
            $actorHandle = $post->get('actor_handle');
            $recipientUri = str_starts_with($actorHandle, 'http') ? $actorHandle : $this->webfingerService->getRemoteActor($actorHandle);

            $this->activityPubService->postActivity(["type" => "Follow", "object" => $recipientUri, "to" => $recipientUri], $form);

            return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_ADDED', 'idformulaire' => $id], false));
        }

        $followers = $this->activityPubService->getFollowers($form);
        $following = $this->activityPubService->getFollowing($form);

        $domain = parse_url($this->wiki->GetBaseURL(), PHP_URL_HOST);

        return $this->render('@bazar/forms/abonnements.twig', [
            'message' => $this->getRequest()->query->get('msg'),
            'form' => $form,
            'domain' => $domain,
            'followers' => $followers,
            'following' => $following,
        ]);
    }

    public function addFollowing($id, $actorUri)
    {
        $form = $this->formManager->getOne($id);

        $this->activityPubService->postActivity(["type" => "Follow", "object" => $actorUri, "to" => $actorUri], $form);

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_ADDED', 'idformulaire' => $id], false));
    }

    public function removeFollowing($id, $actorUri)
    {
        $form = $this->formManager->getOne($id);
        $formActorUri = $this->activityPubService->getFormActorUri($form);

        $this->activityPubService->removeFollowing($form, $actorUri);

        $this->activityPubService->postActivity([
            "type" => "Undo",
            "object" => [
                "type" => "Follow",
                "actor" => $formActorUri,
                "object" => $actorUri,
                "to" => $actorUri,
            ],
            "to" => $actorUri,
        ], $form);

        return $this->wiki->redirect($this->wiki->href('', '', ['vue' => 'abonnements', 'action' => 'list', 'msg' => 'BAZ_FOLLOWING_REMOVED', 'idformulaire' => $id], false));
    }

    public function syncActorPosts($id, $actorUri)
    {
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
        $form = $this->formManager->getOne($id);
        $formActorUri = $this->activityPubService->getFormActorUri($form);

        $this->activityPubService->removeFollower($form, $actorUri);

        $this->activityPubService->postActivity([
            "type" => "Undo",
            "object" => [
                "type" => "Accept",
                "actor" => $formActorUri,
                "object" => [
                    "type" => "Follow",
                    "actor" => $formActorUri,
                    "object" => $actorUri,
                    "to" => $actorUri,
                ],
                "to" => $actorUri,
            ],
            "to" => $actorUri,
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
