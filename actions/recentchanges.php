<?php

/*
recentchanges.php
Copyright (c) 2002, Hendrik Mans <hendrik@mans.de>
Copyright 2002, 2003 David DELON
Copyright 2002, 2003 Charles NEPOTE
Copyright 2002 Patrick PAUL
Copyright  2003  Eric DELORD
All rights reserved.
Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions
are met:
1. Redistributions of source code must retain the above copyright
notice, this list of conditions and the following disclaimer.
2. Redistributions in binary form must reproduce the above copyright
notice, this list of conditions and the following disclaimer in the
documentation and/or other materials provided with the distribution.
3. The name of the author may not be used to endorse or promote products
derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES
OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT,
INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/
// Which is the max number of pages to be shown ?
if ($max = $this->GetParameter('max')) {
    if ($max == 'last') {
        $max = 500;
    } else {
        $last = (int) $max;
    }
} elseif ($user = $this->GetUser()) {
    $max = $user['changescount'];
} else {
    $max = 500;
}

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

// Show recently changed pages
if ($pages = $this->LoadRecentlyChanged($max, $dateMin)) {
    $svgIcon = '<img style="vertical-align:baseline;" width="12" src=\'data:image/svg+xml;utf8,<svg height="1792" viewBox="0 0 1792 1792" width="1792" xmlns="http://www.w3.org/2000/svg"><path d="M192 1664h288v-288h-288v288zm352 0h320v-288h-320v288zm-352-352h288v-320h-288v320zm352 0h320v-320h-320v320zm-352-384h288v-288h-288v288zm736 736h320v-288h-320v288zm-384-736h320v-288h-320v288zm768 736h288v-288h-288v288zm-384-352h320v-320h-320v320zm-352-864v-288q0-13-9.5-22.5t-22.5-9.5h-64q-13 0-22.5 9.5t-9.5 22.5v288q0 13 9.5 22.5t22.5 9.5h64q13 0 22.5-9.5t9.5-22.5zm736 864h288v-320h-288v320zm-384-384h320v-288h-320v288zm384 0h288v-288h-288v288zm32-480v-288q0-13-9.5-22.5t-22.5-9.5h-64q-13 0-22.5 9.5t-9.5 22.5v288q0 13 9.5 22.5t22.5 9.5h64q13 0 22.5-9.5t9.5-22.5zm384-64v1280q0 52-38 90t-90 38h-1408q-52 0-90-38t-38-90v-1280q0-52 38-90t90-38h128v-96q0-66 47-113t113-47h64q66 0 113 47t47 113v96h384v-96q0-66 47-113t113-47h64q66 0 113 47t47 113v96h128q52 0 90 38t38 90z"/></svg>\' alt="'._t('HISTORY').'">';

    if ($this->GetParameter('max')) {
        foreach ($pages as $i => $page) {
            // echo entry
            echo '<small><a href="' . $this->href('revisions', $page['tag']) . '">'.$svgIcon.'</a>&nbsp;'.$page['time'].'</small> ', $this->ComposeLinkToPage($page['tag'], '', '', 0),' <small>par ', $this->Format($page['user']), "</small><br>\n";
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
                echo '<strong>'.date('d.m.Y', strtotime($day)).'&nbsp;:</strong><br>'."\n";
                $curday = $day;
            }
            // echo entry
            echo '<small><a href="' . $this->href('revisions', $page['tag']) . '">'.$svgIcon.'</a>&nbsp;'.$time.'</small> ', $this->ComposeLinkToPage($page['tag'], '', '', 0),' <small>par ', $this->Format($page['user']), "</small><br>\n";
        }
    }
}
