<?php

if ($last = $this->GetParameter('last')) {
    if ($last == 'last') {
        $last = 150;
    } else {
        $last = (int)$last;
    }
    if ($last) {
        $last_users = $this->LoadAll('select name, signuptime from ' . $this->config['table_prefix'] . "users order by signuptime desc limit $last");
        foreach ($last_users as $user) {
            echo $this->Format($user['name']),' . . . ',$user['signuptime'],"<br />\n";
        }
    }
} else {
    if ($last_users = $this->LoadAll('select name, signuptime from ' . $this->config['table_prefix'] . 'users order by name asc')
    ) {
        foreach ($last_users as $user) {
            echo $this->Format($user['name']),' . . . ',$user['signuptime'],"<br />\n";
        }
    }
}
