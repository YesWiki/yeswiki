<?php
/*
* userstable.php
* written by Cyrille giquello under the name of listusers2
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

Copyright 2002 Patrick PAUL
Copyright 2003 David DELON
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
global $wiki ;

$groups = $wiki->GetGroupsList();
// UserIsInGroup
// UserIsAdmin
if ($last = $this->GetParameter('last')) {
    $last= (int) $last ;
    if ($last == 0) {
        $last = 12 ;
    }
    $last_users = $this->LoadAll('select name, email, signuptime from '.$this->config['table_prefix'].'users order by signuptime desc limit '.$last);
} else {
    $last_users = $this->LoadAll('select name, email, signuptime from '.$this->config['table_prefix'].'users order by name asc');
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
    //, ' ', ,' . . . ',$user['signuptime'],' ',,"<br />\n" ;
    echo '</tr>', "\n";
}
echo '</tbody>', "\n";
echo '</table>', "\n";
