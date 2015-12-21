<?php

function loadUserbyEmail($email, $password = 0)
{
    global $wiki;
    return $wiki->LoadSingle(
        "select * from ".$wiki->config["table_prefix"] . "users where email = '".mysqli_real_escape_string($wiki->dblink, $email).
        "' " . ($password === 0 ? "" : "and password = '" . mysqli_real_escape_string($wiki->dblink, $password) . "'") . " limit 1"
    );
}
