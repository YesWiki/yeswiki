<?php

use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use YesWiki\Core\Controller\CsrfTokenController;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\TripleStore;
use YesWiki\Core\YesWikiAction;

class EditGroupsAction extends YesWikiAction
{
    public function run()
    {
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
        if (!empty($_POST['groupname'])) {
            if (!is_string($_POST['groupname'])) {
                $message = 'Action not possible because \'groupname\' should be a string !';
            } elseif (preg_match('/[^A-Za-z0-9]/', $_POST['groupname'])) {
                $message = _t('ONLY_ALPHANUM_FOR_GROUP_NAME');
            } else {
                $selectedGroupName = strval($_POST['groupname']);
                $action = !empty($_POST['action-save'])
                    ? 'save'
                    : (
                        !empty($_POST['action-delete'])
                        ? 'delete'
                        : ''
                    );
                try {
                    if ($action === 'save') {
                        list('message' => $message, 'type' => $type) = $this->saveAcl($selectedGroupName);
                    } elseif ($action === 'delete') {
                        list('message' => $message, 'type' => $type) = $this->deleteGroup($selectedGroupName);
                    }
                } catch (TokenNotFoundException $th) {
                    $message = _t('ERROR_WHILE_SAVING_GROUP') . ':<br/>' . $th->getMessage();
                }
            }
        }

        // retrieves an array of group names from table 'triples' (content of 'resource' starts with 'ThisWikiGroup' and content of 'property' equals  'http://www.wikini.net/_vocabulary/acls')
        $list = $this->wiki->GetGroupsList();
        sort($list);

        if (!empty($selectedGroupName) && in_array($selectedGroupName, $list)) {
            $currentGroupAcl = $this->wiki->GetGroupACL($selectedGroupName);
        }

        return $this->render(
            '@core/actions/edit-group-action.twig',
            compact(['list', 'message', 'type', 'currentGroupAcl', 'selectedGroupName', 'action'])
        );
    }

    protected function saveAcl(string $selectedGroupName): array
    {
        $this->confirmToken();

        $message = '';
        $type = 'danger';

        if (!isset($_POST['acl']) || !is_string($_POST['acl'])) {
            $message = '$_POST[\'acl\'] must be a string';
        } else {
            $newacl = strval($_POST['acl']);
            if (strtolower($selectedGroupName) == ADMIN_GROUP && !$this->wiki->CheckACL($newacl)) {
                $message = _t('YOU_CANNOT_REMOVE_YOURSELF');
            } else {
                $result = $this->wiki->SetGroupACL($selectedGroupName, $newacl);

                if ($result) {
                    if ($result == 1000) {
                        $message = _t('ERROR_RECURSIVE_GROUP') . ' !';
                    } else {
                        $message = _t('ERROR_WHILE_SAVING_GROUP') . ' ' . ucfirst($selectedGroupName) . ' (' . _t('ERROR_CODE') . ' ' . $result . ')';
                    }
                } else {
                    $this->wiki->LogAdministrativeAction($this->wiki->GetUserName(), _t('NEW_ACL_FOR_GROUP') . ' ' . ucfirst($selectedGroupName) . ' : ' . $newacl . "\n");
                    $message = _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP') . ' ' . ucfirst($selectedGroupName);
                    $type = 'success';
                }
            }
        }

        return compact(['message', 'type']);
    }

    protected function deleteGroup(string &$selectedGroupName): array
    {
        $message = '';
        $type = 'danger';

        $this->confirmToken();

        if ($this->wiki->GetGroupACL($selectedGroupName) != '') { // The group is not empty
            $message = _t('ONLY_EMPTY_GROUP_FOR_DELETION');
        } else {
            // Check if acls table contains at least one line (page)
            // for which this group is the only one to have some privilege
            $dbService = $this->getService(DbService::class);
            $vocAcsl = WIKINI_VOC_ACLS;
            $sql = "SELECT page_tag FROM {$dbService->prefixTable($vocAcsl)} WHERE list = \"@{$dbService->escape($selectedGroupName)}\"";
            $ownedPages = $dbService->loadAll($sql); // if group owns no pages, then empty
            if (!empty($ownedPages)) {
                // Array is not empty because the query returns at least one page
                $message = _t('ONLY_NO_PAGES_GROUP_FOR_DELETION') . '<br/>';
                $message .= implode('<br/>', array_map(function ($acl) {
                    return "<a href=\"{$this->wiki->Href('', $acl['page_tag'])}\">{$acl['page_tag']}</a>";
                }, $ownedPages));
            } else {
                // Group is empty AND is not alone having privileges on any page
                $sql = <<<SQL
                UPDATE {$dbService->prefixTable($vocAcsl)}
                    SET `list` = REPLACE(REPLACE (`list`, '@{$dbService->escape($selectedGroupName)}\\n', ''),'\\n@{$dbService->escape($selectedGroupName)}','')
                    WHERE `list` LIKE '%@{$dbService->escape($selectedGroupName)}%'
                SQL;

                $dbService->query($sql);

                $tripleStore = $this->getService(TripleStore::class);
                $previous = $tripleStore->getMatching(GROUP_PREFIX . $selectedGroupName, WIKINI_VOC_PREFIX . WIKINI_VOC_ACLS, '', '=');
                $deletionOk = false;
                if (!empty($previous)) {
                    $deletionOk = true;
                    foreach ($previous as $triple) {
                        if (!$tripleStore->delete($selectedGroupName, WIKINI_VOC_ACLS, $triple['value'], GROUP_PREFIX)) {
                            $deletionOk = false;
                        }
                    }
                }

                if ($deletionOk) {
                    $message = "groupe $selectedGroupName supprimé";
                    $type = 'success';
                    $selectedGroupName = '';
                } else {
                    $message = "Une erreur s'est poduite lors de la suppression du groupe $selectedGroupName (triple non supprimé)";
                    $type = 'warning';
                }
            }
        }

        return compact(['message', 'type']);
    }

    protected function confirmToken()
    {
        $this->getService(CsrfTokenController::class)->checkToken('main', 'POST', 'confirmToken', false);
    }
}
