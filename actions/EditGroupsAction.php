<?php
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Controller\GroupController;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;

class EditGroupsAction extends YesWikiAction
{
    public function run()
    {
        $groupController = $this->getService(GroupController::class);
        $userManager = $this->getService(UserManager::class);
        
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => 'EditGroupsAction : ' . _t('BAZ_NEED_ADMIN_RIGHTS'),
            ]);
        }

        $message = '';
        $type = 'danger';
        $currentGroupAcl = '';
        $selectedGroupName = '';
        $action = '';
        $error_message = '';
        error_log(implode("\n", $_POST));
        
        if(empty($_POST)) {
            error_log('$_POST empty');
        } else {
            if (empty($_POST['groupname'])) {
                $type =  'danger';
                $message =  _t('NO_VAR_GROUP');
            } elseif (!is_string($_POST['groupname'])) {
               $type =  'danger';
               $message =  "Invalid " . _t('GROUP_NOT_STRING');
            } else {
                $selectedGroupName = strval($_POST['groupname']);
                try {
                    $this->confirmToken();
                    if (!empty($_POST['action-view'])) {
                        $currentGroupAcl = $groupController->getMembers($selectedGroupName); 
                    } elseif (!empty($_POST['action-create'])) {
                        $groupController->create($selectedGroupName, array());
                        $type =  'success';
                        $message =  str_replace('{group}',$selectedGroupName, _t('GROUP_CREATED')); 
                    } elseif (!empty($_POST['action-update'])) {
                        $members = array();
                        foreach ($_POST as $key => $value)
                        {
                            if ($value == "1") {
                                $members[] = $key;
                            }
                        }
                        $groupController->update($selectedGroupName, $members);
                        $message = str_replace("{group}",$selectedGroupName, _t('GROUP_SAVED'));
                        $type = 'success';
                    } elseif (!empty($_POST['action-delete'])) {
                        $groupController->delete($selectedGroupName);
                        $message = str_replace("{group}",$selectedGroupName, _t('GROUP_DELETED'));
                        $type = 'success';
                        $selectedGroupName = '';
                    }
                } catch (Throwable $th) {
                    $type = 'danger';
                    $message = _t('ERROR_WHILE_EDITING_GROUP') .':<br/>'. $th->getMessage();
                    if ($wakkaConfig == 'yes') {
                        $message = $message.'\<br>'.$th->getTraceAsString();
                    }
                }
            }
        }
        
        if ($groupController->groupExists($selectedGroupName)) {
            $currentGroupAcl = $groupController->getMembers($selectedGroupName); 
        }
        
        if (!empty($message)) {
            $error_message = [ 'type' => $type, 'message' => $message ];
        }

        // retrieves an array of group names from table 'triples' (content of 'resource' starts with 'ThisWikiGroup' and content of 'property' equals  'http://www.wikini.net/_vocabulary/acls')
        $list = $groupController->getAll();
        sort($list);
        $users = array_map(function ($user){ return $user['name'];}, $userManager->getAll());
        sort($users);
        $merged_list = array_merge(array_map( function($el) { return '@'.$el;}, $list), $users);
        
        $field = [ 'name' => '', 'propertyName' => '', 'required'=> false, 'label'=> $selectedGroupName ];
        error_log("render");

        return $this->render(
            '@core/actions/edit-group-action.twig',
            [ 'error_message' => $error_message, 'list' => $list, 'selectedGroupName' => $selectedGroupName, 'field' => $field, 'options' => $merged_list, 'selectedOptionsId' => $currentGroupAcl, 'formName' => _t('USERS_GROUPS_LIST'), 'name'=> _t('GROUP_SELECTION')]);
    }

    protected function confirmToken()
    {
        $this->getService(CsrfTokenController::class)->checkToken('main', 'POST', 'confirmToken', false);
    }
}
