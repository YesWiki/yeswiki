<?php

use YesWiki\Core\Service\UserManager;
use YesWiki\Core\YesWikiAction;

class ListusersAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return [
            'period' => $arg['period'] ?? '',
            'last' => $arg['last'] ?? '',
        ];
    }

    public function run()
    {
        $request = $this->getRequest();

        // a day/week/month preset, set via the request query string (e.g. an interactive
        // filter link on the same page) takes priority over a fixed tag attribute
        $requestPeriod = $request->query->get('period', '');
        if (in_array($requestPeriod, ['day', 'week', 'month'], true)) {
            $dateMin = date('Y-m-d H:i:s', strtotime('-1 ' . $requestPeriod));
        } else {
            $dateMin = $this->arguments['period'];
        }

        $users = array_map(function ($user) {
            return ['name' => $user['name'], 'signuptime' => $user['signuptime']];
        }, $this->getService(UserManager::class)->getAll());

        if (!empty($dateMin)) {
            $users = array_values(array_filter($users, function ($user) use ($dateMin) {
                return $user['signuptime'] >= $dateMin;
            }));
        }

        $output = '';
        $last = $this->arguments['last'];
        if ($last !== '') {
            $last = ($last === 'last') ? 150 : (int)$last;
            if ($last) {
                $sortedUsers = $users;
                usort($sortedUsers, function ($a, $b) {
                    return strcmp($b['signuptime'], $a['signuptime']);
                });
                $lastUsers = array_slice($sortedUsers, 0, $last);
                $curday = '';
                foreach ($lastUsers as $user) {
                    // day header
                    list($day, $time) = explode(' ', $user['signuptime']);
                    if ($day != $curday) {
                        if ($curday) {
                            $output .= "<br>\n";
                        }
                        $output .= '<strong>' . date('d.m.Y', strtotime($day)) . '&nbsp;:</strong><br>' . "\n";
                        $curday = $day;
                    }
                    $output .= '<small>' . $time . '</small> ' . $user['name'] . "<br>\n";
                }
            } else {
                $output .= _t('LOGIN_NO_SIGNUP_IN_THIS_PERIOD');
            }
        } else {
            // $users is already name-sorted (UserManager::getAll() orders by name)
            if (!empty($users)) {
                $output .= '<ol class="list-users">';
                foreach ($users as $user) {
                    list($day, $time) = explode(' ', $user['signuptime']);
                    $output .= '<li>' . $user['name'] . ' - <small>' . date('d.m.Y', strtotime($day)) . ' ' . $time . '</small> ' . "</li>\n";
                }
                $output .= '</ol>';
            } else {
                $output .= _t('LOGIN_NO_SIGNUP_IN_THIS_PERIOD');
            }
        }

        return $output;
    }
}
