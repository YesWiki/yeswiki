<?php
/*
* userstable.php
* Orginally written by Cyrille giquello under the name of listusers2
* Addition of user management (\YesWiki\User class) by Sylvain Boyer
* builds a table of the users
* each line shows, for a user :
* - his/her user name,
* - the group(s) s.he belongs to,
* - his/her email,
* - his/her registration date.
* if parameter "last" is set,
* 		the 12 users with the latest registration date are shown
*	 in chronological reverse order
* else
*		all users are shown in alphabetical order (username)
*
*/

$loggedUser = $this->GetUser();
if ($loggedUser == ""){ // no logged user
	echo '<div class="alert alert-danger alert-error">'._t('LOGGED_USERS_ONLY_ACTION').'</div>';
	return ;
}

require_once 'includes/constants.php';
require_once 'includes/WikiUser.class.php';
global $wiki ;
$groups = $wiki->GetGroupsList();

$isAdmin = $this->UserIsAdmin();

// UserIsInGroup
// UserIsAdmin
$sql = 'SELECT name, email, signuptime FROM '.$this->config['table_prefix'].'users ORDER BY ';
if ($last = $this->GetParameter('last')) {
	$last= (int) $last ;
	if ($last == 0) {
		$last = 12 ;
	}
	$sql .= 'signuptime DESC LIMIT '.$last;
} else {
	$sql .= 'name ASC';
}
$last_users = $this->LoadAll($sql);
if (!empty($_POST['userstable_action'])) { // Check if the page received a post named 'userstable_action'
	$user = $_POST['userstable_action'];
	if (substr($user, 0, 7) == 'delete_') { // Check if $_POST['userstable_action'] starts with  'delete_'
		// The form returns a username to delete, therefore
		// There is a request to delete the user
		$user = substr($user, 7);
		$rowUser = new \YesWiki\User($wiki->config, $wiki->queryLog, $wiki->CookiePath);
		$OK= $rowUser->loadByNameFromDB($user);
		if (!$OK) {
			die ($rowUser->error);
		}
		$OK = $rowUser->delete();
		if (!$OK) {
			die ($rowUser->error);
		}
		$wiki->redirect($wiki->href());
	} else { // We arrived on this page with an unexpected $_POST
		die (_t('USER_USERSTABLE_MISTAKEN_ARGUMENT'));
	}
}

$this->addJavascriptFile('tools/templates/libs/vendor/datatables/jquery.dataTables.min.js');
$this->addJavascriptFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.js');
$this->addCSSFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.css');
echo '<table class="table table-striped">', "\n";
echo '<thead>', "\n";
echo '<tr>', "\n";
echo '	<th>Nom</th>', "\n";
echo '	<th>Groupe(s)</th>', "\n";
if ($isAdmin){ // Emails only shown to admins
	echo '	<th>Email</th>', "\n";
}
echo '	<th>Inscription</th>', "\n";
echo '	<th> </th>', "\n";
if ($isAdmin){ // Delete buttons only shown to admins
	echo '	<th> </th>', "\n";
}
echo '</tr>', "\n";
echo '</thead>', "\n";
echo '<tbody>', "\n";
foreach ($last_users as $user) {
	$ug = array();
	foreach ($groups as $group) {
		if ($wiki->UserIsInGroup($group, $user['name'], true)) {
			$ug[] = $group ;
		}
	}
	echo '<tr>';
	echo '<td>' . $user['name'] . '</td>';
	echo '<td>', implode(', ', $ug) , '</td>';
	if ($isAdmin){  // Email only shown to admins
		echo '<td>', $user['email'] , '</td>';
	}
	echo '<td>', $user['signuptime'] , '</td>';
	echo '<td>';
	if ($loggedUser['name'] == $user['name']) { // $loggedUser fullness allready tested. Current user
		echo '<a href="'.$this->href('', 'ParametresUtilisateur').'" class="btn btn-sm btn-primary" role="button">'._t('USER_MODIFY').'</a>';
	} else { // not the current user, then can be modified
		if ($isAdmin) {
			echo '<a href="'.$this->href('', 'ParametresUtilisateur', 'user='.$user['name'], false).'&from='.$this->tag.'" class="btn btn-sm btn-warning " role="button">'._t('USER_MODIFY').'</a>';
		}
	}
	echo '</td>';
	if ($isAdmin && ($loggedUser['name'] != $user['name'])) { // admin and not the current user, then can be deleted
		echo '<td>';
		echo '<form action="'.$this->href('',$this->tag).'" method="post" class="form-horizontal">';
		echo '<input type="hidden" name="userstable_action" value="delete_'.$user['name'].'" />';
		echo '<input class="btn btn-sm btn-danger" type="submit" value="'._t('USER_DELETE').'" />';
		echo $this->FormClose();
		echo '</td>';
	}
	echo '</tr>', "\n";
}
echo '</tbody>', "\n";
echo '</table>', "\n";
