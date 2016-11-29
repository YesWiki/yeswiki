<?php
/*
listusers.php

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

if ($last = $this->GetParameter('last')) {
    if ($last == 'last') {
        $last = 150;
    } else {
        $last = (int) $last;
    }
    if ($last) {
        $last_users = $this->LoadAll('select name, signuptime from '.$this->getUserTablePrefix()."users order by signuptime desc limit $last");
        foreach ($last_users as $user) {
            echo $this->Format('**""'.$user['name'].'""**'),' . . . ',$user['signuptime'],"<br />\n";
        }
    }
} else {
    if ($last_users = $this->LoadAll('select name, signuptime from '.$this->getUserTablePrefix().'users order by name asc')) {
        foreach ($last_users as $user) {
            echo $this->Format('**""'.$user['name'].'""**'),' . . . ',$user['signuptime'],"<br />\n";
        }
    }
}
