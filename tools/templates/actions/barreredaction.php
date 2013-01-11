<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

if ($this->HasAccess("write")) {
        // on récupère la page et ses valeurs associées
        $page = $this->GetParameter('page'); 
        if (empty($page)) {
                $page = $this->GetPageTag();
                $time = $this->GetPageTime();
                $content = $this->page;
        } else {
                $content = $this->LoadPage($page);
                $time = $content["time"];
        }
        $barreredactionelements['page'] = $page;
        $barreredactionelements['linkpage'] = $this->href("", $page);

        // on choisit le template utilisé
        $template = $this->GetParameter('template'); 
        if (empty($template)) {
                $template = 'barreredaction_basic.tpl.html';
        }

        // on peut ajouter des classes à la classe par défaut .footer
        $barreredactionelements['class'] = ($this->GetParameter('class') ? 'footer '.$this->GetParameter('class') : 'footer');

	// on ajoute le lien d'édition si l'action est autorisée
	if ( $this->HasAccess("write", $page) ) {
                $barreredactionelements['linkedit'] = $this->href("edit", $page);
	}

        //
	if ( $time ) {
                 $barreredactionelements['linkrevisions'] = $this->href("revisions", $page);
                 $barreredactionelements['time'] = date(TEMPLATE_DATE_FORMAT, strtotime($time));
	}

	// if this page exists
	if ( $content ) {   
                // if owner is current user
                if ($this->UserIsOwner($page) ) {   
                       $barreredactionelements['owner'] = TEMPLATE_OWNER." : ".TEMPLATE_YOU.' - '.TEMPLATE_PERMISSIONS;
                        $barreredactionelements['linkacls'] = $this->href("acls", $page);
                        $barreredactionelements['linkdeletepage'] = $this->href("deletepage", $page);
                }
                else {   
                        if ($owner = $this->GetPageOwner($page)) {
                                $barreredactionelements['owner'] = TEMPLATE_OWNER." : ".$owner;
                                if ($this->UserIsAdmin()) { 
                                        $barreredactionelements['linkacls'] = $this->href("acls", $page);
                                        $barreredactionelements['owner'] .= ' - '.TEMPLATE_PERMISSIONS;
                                }   
                                else {
                                        //$barreredactionelements['linkacls'] = $this->href('', $owner);
                                }             
                        }   
                        else {   
                                $barreredactionelements['owner'] = TEMPLATE_NO_OWNER.($this->GetUser() ? " - ".TEMPLATE_CLAIM : "");
                                if ($this->GetUser()) $barreredactionelements['linkacls'] = $this->href("claim", $page);
                                //else $barreredactionelements['linkacls'] = $this->href("claim", $page);
                        }
                }

      }   
		
        $barreredactionelements['linkreferrers'] = $this->href("referrers", $page);
        $barreredactionelements['linkdiaporama'] = $this->href("diaporama", $page);
	$barreredactionelements['linkshare'] = $this->href("share", $page);
	
        include_once('tools/templates/libs/squelettephp.class.php');
        $barreredactiontemplate = new SquelettePhp('tools/templates/presentation/templates/'.$template);
        $barreredactiontemplate->set($barreredactionelements);
        echo $barreredactiontemplate->analyser();
}

?>
