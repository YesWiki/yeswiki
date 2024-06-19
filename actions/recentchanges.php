<?php

// Which is the max number of pages to be shown ?
if ($max = $this->GetParameter('max')) {
    if ($max == 'last') {
        $max = 500;
    } else {
        $last = (int)$max;
    }
} elseif ($user = $this->GetUser()) {
    $max = $user['changescount'];
} else {
    $max = 500;
}

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

// Show recently changed pages
if ($pages = $this->LoadRecentlyChanged($max, $dateMin)) {
    $svgIcon = '<img style="vertical-align:baseline;" width="12" src=\'data:image/svg+xml;utf8,<svg height="1792" viewBox="0 0 1792 1792" width="1792" xmlns="http://www.w3.org/2000/svg"><path d="M192 1664h288v-288h-288v288zm352 0h320v-288h-320v288zm-352-352h288v-320h-288v320zm352 0h320v-320h-320v320zm-352-384h288v-288h-288v288zm736 736h320v-288h-320v288zm-384-736h320v-288h-320v288zm768 736h288v-288h-288v288zm-384-352h320v-320h-320v320zm-352-864v-288q0-13-9.5-22.5t-22.5-9.5h-64q-13 0-22.5 9.5t-9.5 22.5v288q0 13 9.5 22.5t22.5 9.5h64q13 0 22.5-9.5t9.5-22.5zm736 864h288v-320h-288v320zm-384-384h320v-288h-320v288zm384 0h288v-288h-288v288zm32-480v-288q0-13-9.5-22.5t-22.5-9.5h-64q-13 0-22.5 9.5t-9.5 22.5v288q0 13 9.5 22.5t22.5 9.5h64q13 0 22.5-9.5t9.5-22.5zm384-64v1280q0 52-38 90t-90 38h-1408q-52 0-90-38t-38-90v-1280q0-52 38-90t90-38h128v-96q0-66 47-113t113-47h64q66 0 113 47t47 113v96h384v-96q0-66 47-113t113-47h64q66 0 113 47t47 113v96h128q52 0 90 38t38 90z"/></svg>\' alt="' . _t('HISTORY') . '">';

    if ($this->GetParameter('max')) {
        foreach ($pages as $i => $page) {
            // echo entry
            echo '<small><a href="' . $this->href('revisions', $page['tag']) . '">' . $svgIcon . '</a>&nbsp;' . $page['time'] . '</small> ', $this->ComposeLinkToPage($page['tag'], '', '', 0),' <small>' . _t('BY') . ' ', $this->Format($page['user']), "</small><br>\n";
        }
    } else {
        $curday = '';
        foreach ($pages as $i => $page) {
            // day header
            list($day, $time) = explode(' ', $page['time']);
            if ($day != $curday) {
                if ($curday) {
                    echo "<br>\n";
                }
                echo '<strong>' . date('d.m.Y', strtotime($day)) . '&nbsp;:</strong><br>' . "\n";
                $curday = $day;
            }
            // echo entry
            echo '<small><a href="' . $this->href('revisions', $page['tag']) . '">' . $svgIcon . '</a>&nbsp;' . $time . '</small> ', $this->ComposeLinkToPage($page['tag'], '', '', 0),' <small>' . _t('BY') . ' ', $this->Format($page['user']), "</small><br>\n";
        }
    }
}
