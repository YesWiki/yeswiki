<?php
public function getUserTablePrefix()
{
    if (isset($this->config['user_table_prefix']) && !empty($this->config['user_table_prefix'])) {
        return $this->config['user_table_prefix'];
    } else {
        return $this->config["table_prefix"];
    }
}

public function LoadUser($name, $password = 0)
{
    return $this->LoadSingle("select * from " . $this->getUserTablePrefix() . "users where name = '" . mysqli_real_escape_string($this->dblink, $name) . "' " . ($password === 0 ? "" : "and password = '" . mysqli_real_escape_string($this->dblink, $password) . "'") . " limit 1");
}

public function LoadUsers()
{
    return $this->LoadAll("select * from " . $this->getUserTablePrefix() . "users order by name");
}
