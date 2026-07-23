<?php

use YesWiki\Core\Service\UserManager;

// si une date est indiquée
if (isset($_GET['period']) && in_array($_GET['period'], ['day', 'week', 'month'])) {
    switch ($_GET['period']) {
        case 'day':
            $d = strtotime('-1 day');
            $dateMin = date('Y-m-d H:i:s', $d);
            break;
        case 'week':
            $d = strtotime('-1 week');
            $dateMin = date('Y-m-d H:i:s', $d);
            break;
        case 'month':
            $d = strtotime('-1 month');
            $dateMin = date('Y-m-d H:i:s', $d);
            break;
    }
} else {
    $dateMin = $this->GetParameter('period');
}

$users = array_map(function ($user) {
    return ['name' => $user['name'], 'signuptime' => $user['signuptime']];
}, $this->services->get(UserManager::class)->getAll());

if (!empty($dateMin)) {
    $users = array_values(array_filter($users, function ($user) use ($dateMin) {
        return $user['signuptime'] >= $dateMin;
    }));
}

if ($last = $this->GetParameter('last')) {
    if ($last == 'last') {
        $last = 150;
    } else {
        $last = (int)$last;
    }
    if ($last) {
        $sortedUsers = $users;
        usort($sortedUsers, function ($a, $b) {
            return strcmp($b['signuptime'], $a['signuptime']);
        });
        $last_users = array_slice($sortedUsers, 0, $last);
        $curday = '';
        foreach ($last_users as $user) {
            // day header
            list($day, $time) = explode(' ', $user['signuptime']);
            if ($day != $curday) {
                if ($curday) {
                    echo "<br>\n";
                }
                echo '<strong>' . date('d.m.Y', strtotime($day)) . '&nbsp;:</strong><br>' . "\n";
                $curday = $day;
            }
            // echo entry
            echo '<small>' . $time . '</small> ' . $user['name'] . "<br>\n";
        }
    } else {
        echo _t('LOGIN_NO_SIGNUP_IN_THIS_PERIOD');
    }
} else {
    // $users is already name-sorted (UserManager::getAll() orders by name)
    if (!empty($users)) {
        echo '<ol class="list-users">';
        foreach ($users as $user) {
            list($day, $time) = explode(' ', $user['signuptime']);
            echo '<li>' . $user['name'] . ' - <small>' . date('d.m.Y', strtotime($day)) . ' ' . $time . '</small> ' . "</li>\n";
        }
        echo '</ol>';
    } else {
        echo _t('LOGIN_NO_SIGNUP_IN_THIS_PERIOD');
    }
}
