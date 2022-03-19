<?php
/*
claim.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright  2003  Eric FELDSTEIN
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

// Vérification de sécurité
if (!defined("WIKINI_VERSION")) {
    die("acc&egrave;s direct interdit");
}

use YesWiki\Core\Service\AclService;

$tag = $this->getPageTag();
// only do it on existing pages
if ($this->page) {
    $availableActions = ['opencomments', 'closecomments'];
    // check if actions are requested
    if (
        !empty($_GET['action'])
        && in_array($_GET['action'], $availableActions)
        && ($this->UserIsAdmin() || $this->UserIsOwner($tag))
    ) {
        $aclsService = $this->services->get(AclService::class);
        $commentsAcls = $aclsService->load($tag, 'comment');
        $wikiGroups = $this->GetGroupsList();
        switch ($_GET['action']) {
            case 'opencomments':
                if (
                    !empty($_GET['list'])
                    && (in_array($_GET['list'], $wikiGroups) || $_GET['list']=='+')
                ) {
                    $aclsService->save($tag, 'comment', $_GET['list']);
                    $this->SetMessage(_t('YW_COMMENTS_ARE_NOW_OPEN'));
                } else {
                    $this->SetMessage(_t('YW_PROBLEM_WITH_ACLS_LIST'));
                }
                break;
            case 'closecomments':
                if ($commentsAcls != null) {
                    $aclsService->delete($tag, ['comment']);
                    $this->SetMessage(_t('YW_COMMENTS_ARE_NOW_CLOSED'));
                } else {
                    $this->SetMessage(_t('YW_COMMENTS_ALREADY_CLOSED'));
                }
                break;
        }
    }

    // only claim ownership if this page has no owner, and if user is logged in.
    if (!$this->GetPageOwner() && $this->GetUser()) {
        $this->SetPageOwner($tag, $this->GetUserName());
        $this->SetMessage(_t('YW_YOU_ARE_NOW_OWNER_OF_PAGE'));
    }
}

$this->Redirect($this->href());
