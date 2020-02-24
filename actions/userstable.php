<?php
/*
* userstable.php
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
foreach ($last_users as $user) {
	if (!empty($_GET['delete_'.$user['name']])) {
		// The form returns a username to delete, therefore
		// There is a request to delete the user
		// require_once 'includes/WikiUser.class.php';
		$rowUser = new \YesWiki\User($wiki->config, $wiki->queryLog, $wiki->CookiePath);
		$OK= $rowUser->loadByNameFromDB($user['name']);
		if (!$OK) {
			die ($rowUser->error);
		}
		$OK = $rowUser->delete();
		if (!$OK) {
			die ($rowUser->error);
		}
		echo "<meta http-equiv='refresh' content='0'>";
	}
}


$this->addJavascriptFile('tools/templates/libs/vendor/datatables/jquery.dataTables.min.js');
$this->addJavascriptFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.js');
$this->addCSSFile('tools/templates/libs/vendor/datatables/dataTables.bootstrap.min.css');
echo '<table class="table table-striped">', "\n";
echo '<thead>', "\n";
echo '<tr>
	<th>Nom</th>
	<th>Groupe(s)</th>
	<th>Email</th>
	<th>Inscription</th>
	<th> </th>
</tr>';
echo '</thead>', "\n";
echo '<tbody>', "\n";
foreach ($last_users as $user) {
	$ug = array();
	foreach ($groups as $group) {
		if ($wiki->UserIsInGroup($group, $user['name'], true)) {
			$ug[] = $group ;
		}
		//error_log($group.' : '.print_r($acl,true));
	}
	echo '<tr>';
	echo '<td>' . $user['name'] . '</td>';
	echo '<td>', implode(', ', $ug) , '</td>';
	echo '<td>', $user['email'] , '</td>';
	echo '<td>', $user['signuptime'] , '</td>';
	echo '<td>';
	$loggedUser = $this->GetUser();
	if (($loggedUser != "") && ($loggedUser['name'] == $user['name'])){ // current user
		echo '<a href="'.$this->config["base_url"].'ParametresUtilisateur" class="btn btn-sm btn-info" role="button">'._t('USER_MODIFY').'</a>';
	} else { // not the current user, then can be deleted (at least try)
		if ($isAdmin) {
			echo '<a href="'.$this->config["base_url"].'ParametresUtilisateur&user='.$user['name'].'&from='.$this->tag.'" class="btn btn-sm btn-danger " role="button">'._t('USER_MODIFY').'</a>';
		}
	}
	echo '</td>';
	echo '</tr>', "\n";
}
echo '</tbody>', "\n";
echo '</table>', "\n";
