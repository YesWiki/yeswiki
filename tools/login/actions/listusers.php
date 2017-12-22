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

// si une date est indiquée
if (isset($_GET['period']) && in_array($_GET['period'], array('day', 'week', 'month'))) {
    switch ($_GET['period']) {
        case 'day':
            $d = strtotime("-1 day");
            $dateMin = date("Y-m-d H:i:s", $d);
            break;
        case 'week':
            $d = strtotime("-1 week");
            $dateMin = date("Y-m-d H:i:s", $d);
            break;
        case 'month':
            $d = strtotime("-1 month");
            $dateMin = date("Y-m-d H:i:s", $d);
            break;
    }
} else {
    $dateMin = $this->GetParameter('period');
}

if ($last = $this->GetParameter('last')) {
    if ($last == 'last') {
        $last = 150;
    } else {
        $last = (int) $last;
    }
    if ($last) {
        $curday = '';
        $sql = 'SELECT name, signuptime FROM '.$this->config['table_prefix'].'users';

        if (!empty($dateMin)) {
            $sql .= ' WHERE signuptime >= "'.$dateMin.'"';
        }
        $sql .= ' ORDER BY signuptime DESC LIMIT '.$last;
        $last_users = $this->LoadAll($sql);
        foreach ($last_users as $user) {
            // day header
            list($day, $time) = explode(' ', $user['signuptime']);
            if ($day != $curday) {
                if ($curday) {
                    echo "<br>\n";
                }
                echo '<strong>'.date('d.m.Y', strtotime($day)).'&nbsp;:</strong><br>'."\n";
                $curday = $day;
            }
            // echo entry
            echo '<small>'.$time.'</small> '.$user['name']."<br>\n";
        }
    } else {
        echo _t('LOGIN_NO_SIGNUP_IN_THIS_PERIOD');
    }
} else {
    $sql = 'SELECT name, signuptime FROM '.$this->config['table_prefix'].'users';

    if (!empty($dateMin)) {
        $sql .= ' WHERE signuptime >= "'.$dateMin.'"';
    }
    $sql .= ' ORDER BY name ASC';
    $curday = '';
    if ($last_users = $this->LoadAll($sql)) {
        foreach ($last_users as $user) {
            // day header
            list($day, $time) = explode(' ', $user['signuptime']);
            if ($day != $curday) {
                if ($curday) {
                    echo "<br>\n";
                }
                echo '<strong>'.date('d.m.Y', strtotime($day)).'&nbsp;:</strong><br>'."\n";
                $curday = $day;
            }
            // echo entry
            echo '<small>'.$time.'</small> '.$user['name']."<br>\n";
        }
    } else {
        echo _t('LOGIN_NO_SIGNUP_IN_THIS_PERIOD');
    }
}
