<?php

function loadUserbyEmail($email, $password = 0)
{
    global $wiki;
    return $wiki->LoadSingle(
        "select * from ".$wiki->config["table_prefix"] . "users where email = '".mysql_real_escape_string($email).
        "' " . ($password === 0 ? "" : "and password = '" . mysql_real_escape_string($password) . "'") . " limit 1"
    );
}
