<?php
$result = $this->LoadAll('SHOW TABLES FROM '.$this->config['mysql_database']
  .' LIKE "'.$this->config['table_prefix'].'%"');
$this->query("SET NAMES 'utf8'");
$this->query('ALTER DATABASE `'.$this->config['mysql_database'].'` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci');
$tables = array(
    $this->config['table_prefix'].'acls',
    $this->config['table_prefix'].'links',
    $this->config['table_prefix'].'nature',
    $this->config['table_prefix'].'pages',
    $this->config['table_prefix'].'referrers',
    $this->config['table_prefix'].'triples',
    $this->config['table_prefix'].'users'
);

foreach ($tables as $table) {
    $query="ALTER TABLE `".$table."` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    echo '<hr>'.$query.'<br>';
    $this->query($query);
    $queryConvert="ALTER TABLE `".$table."` CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
    echo '<hr>'.$queryConvert.'<br>';
    $this->query($queryConvert);
    // $result_query=mysql_query($query);

    //Change Field
    // $cols = $this->LoadAll("SHOW COLUMNS FROM ".$table);
    // $dataQuery = "SELECT * FROM ".$table;
    // echo $dataQuery.'<br>';
    // $data = $this->LoadAll($dataQuery);
    // foreach ($cols as $row) {
    //     $colQuery = 'ALTER TABLE '.$table.' MODIFY `'.$row['Field'].'` '.$row['Type'].' CHARACTER SET utf8 COLLATE utf8_unicode_ci';
    //     echo $colQuery.'<br>';
    //     if (($row['Type']=="string") or ($row['Type']=="blob")) {
    //         // Printing results in HTML
    //         foreach ($data as $line) {
    //             //Convert TO String
    //             echo 'Convert Line.... '.$line[$row['Field']];
    //             $transform = $line[$row['Field']];
    //             $updateQuery = 'UPDATE '.$table.' SET `'.$row['Field'].'` = "'.$transform.'" WHERE `'.$row['Field'].'`="'.$line[$row['Field']].'"';

    //             echo $updateQuery.'<br>';
    //         }
    //     }
    // }
    echo "Complete Table: <b>".$table."</b><br>";
}

echo "<h3>Complete ALL</h3>";