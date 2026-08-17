<?php

namespace YesWiki\Identity\Action;

use YesWiki\Core\YesWikiAction;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Component\Category;
use YesWiki\Kernel\Component\Component;
use YesWiki\Kernel\Component\ProvidesComponents;
use YesWiki\Kernel\Component\Setting;
use YesWiki\Kernel\Performable\RegisteredAction;

class ListusersAction extends YesWikiAction implements RegisteredAction, ProvidesComponents
{
    /** `{{listusers}}` in page content -- stated, not inferred from the filename. */
    public static function performableName(): string
    {
        return 'listusers';
    }

    public function components(): array
    {
        return [
            Component::for('listusers')
                ->category(Category::Admin)
                ->label(_t('AB_advanced_action_listusers_label'))
                ->icon('users')
                ->previewHeight('200px')
                ->settings(
                    Setting::number('last')
                        ->label(_t('AB_advanced_action_listusers_last_label'))
                        ->hint(_t('AB_advanced_action_listusers_last_hint'))
                        ->default('')
                        ->min(1),
                ),
        ];
    }

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
