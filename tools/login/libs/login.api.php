<?php

function getUser($username = '')
{
    global $wiki;
    if (!empty($username[0])) {
        if ($wiki->UserIsAdmin() or $wiki->GetUserName() == $username[0]) {
            $user = $wiki->LoadUser($username[0]);
            if ($user) {
                echo json_encode($user);
            } else {
                echo json_encode(
                    array('error' => array('User '.$username[0].' not found.'))
                );
            }
        } else {
            echo json_encode(
                array('error' => array('Unauthorized'))
            );
        }
    } else {
        $users = $wiki->LoadUsers();
        echo json_encode($users);
    }
}