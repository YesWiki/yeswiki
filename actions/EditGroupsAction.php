<?php

use YesWiki\Core\Service\DbService;
use YesWiki\Core\YesWikiAction;

class EditGroupsAction extends YesWikiAction
{
    public function run()
    {
        if (!$this->wiki->UserIsAdmin()) {
            return $this->render('@templates/alert-message.twig', [
                'type'=>'danger',
                'message'=> "EditGroupsAction : " . _t('BAZ_NEED_ADMIN_RIGHTS')
            ]) ;
        }

        $message = '';
        $type = 'danger';
        $currentGroupAcl = '';
        $selectedGroupName = '';
        $action = '';
        if (!empty($_POST['groupname'])){
            if (!is_string($_POST['groupname'])){
                $message = 'Action not possible because \'groupname\' should be a string !';
            } else if (preg_match('/[^A-Za-z0-9]/', $_POST['groupname'])){
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
                // TODO manage anti-csrf token
                if ($action === 'save'){
                    list('message' => $message, 'type' => $type) = $this->saveAcl($selectedGroupName);
                } else if ($action === 'delete') {
                    list('message' => $message, 'type' => $type) = $this->deleteGroup($selectedGroupName);
                }
            }
        }

        // retrieves an array of group names from table 'triples' (content of 'resource' starts with 'ThisWikiGroup' and content of 'property' equals  'http://www.wikini.net/_vocabulary/acls')
        $list = $this->wiki->GetGroupsList();
        sort($list);

        if (!empty($selectedGroupName) && in_array($selectedGroupName, $list)){
            $currentGroupAcl = $this->wiki->GetGroupACL($selectedGroupName);
        }

        return $this->render(
            '@core/actions/edit-group-action.twig',
            compact(['list','message','type','currentGroupAcl','selectedGroupName','action'])
        );
    }

    protected function saveAcl(string $selectedGroupName): array
    {
        $message = '';
        $type = 'danger';

        if (!isset($_POST['acl']) || !is_string($_POST['acl'])){
            $message = '$_POST[\'acl\'] must be a string';
        } else {
            $newacl = strval($_POST['acl']);
            if (strtolower($selectedGroupName) == ADMIN_GROUP && !$this->wiki->CheckACL($newacl)) {
                $message = _t('YOU_CANNOT_REMOVE_YOURSELF');
            } else {
                $result = $this->wiki->SetGroupACL($selectedGroupName, $newacl);
                
                if ($result) {
                    if ($result == 1000) {
                        $message = _t('ERROR_RECURSIVE_GROUP').' !';
                    } else {
                        $message = _t('ERROR_WHILE_SAVING_GROUP') . ' ' . ucfirst($selectedGroupName) . ' ('._t('ERROR_CODE').' ' . $result . ')';
                    }
                } else {
                    //
                    $this->wiki->LogAdministrativeAction($this->wiki->GetUserName(), _t('NEW_ACL_FOR_GROUP')." " . ucfirst($selectedGroupName) . ' : ' . $newacl . "\n");
                    $message = _t('NEW_ACL_SUCCESSFULLY_SAVED_FOR_THE_GROUP').' ' . ucfirst($selectedGroupName);
                    $type = 'success';
                }
            }
        }

        return compact(['message','type']);
    }

    
    protected function deleteGroup(string &$selectedGroupName): array
    {
        $message = '';
        $type = 'danger';

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
                $message = _t('ONLY_NO_PAGES_GROUP_FOR_DELETION').'<br/>';
                $message .= implode('<br/>',array_map(function($acl){
                    return "<a href=\"{$this->wiki->Href('',$acl['page_tag'])}\">{$acl['page_tag']}</a>";
                },$ownedPages));
            } else {
                // Group is empty AND is not alone having privileges on any page
                $sql = <<<SQL
                UPDATE {$dbService->prefixTable($vocAcsl)}
                    SET `list` = REPLACE(REPLACE (`list`, '@{$dbService->escape($selectedGroupName)}\\n', ''),'\\n@{$dbService->escape($selectedGroupName)}','')
                    WHERE `list` LIKE '%@{$dbService->escape($selectedGroupName)}%'
                SQL;

                $dbService->query($sql);
                $groupName = GROUP_PREFIX . $selectedGroupName;
                $sql = <<<SQL
                DELETE FROM {$dbService->prefixTable('triples')} WHERE `resource` = '{$dbService->escape($groupName)}' AND `value` = '';
                SQL;
                
                $dbService->query($sql);
                // TODO manage result of query
                $message = "groupe $selectedGroupName supprimé";
                $type = 'success';
                $selectedGroupName = '';
            }
        }

        return compact(['message','type']);
    }
}
