<?php
if ($this->userIsAdmin()) {
    include_once 'includes/Encoding.php';
    $output = '';
    $result = $this->LoadAll(
        'SHOW TABLES FROM '.$this->config['mysql_database']
        .' LIKE "'.$this->config['table_prefix'].'%"'
    );
    $this->query('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
    $this->query('ALTER DATABASE `'.$this->config['mysql_database'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
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
        if ($table == $this->config['table_prefix'].'triples') {
            $query = 'ALTER TABLE `'.$this->config['table_prefix'].'triples` CHANGE `resource` `resource` VARCHAR(191), CHANGE `property` `property` VARCHAR(191)';
            $output .=  '<hr>'.$query.'<br>';
            $this->query($query);
        }
        $query="ALTER TABLE `".$table."` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        $output .=  '<hr>'.$query.'<br>';
        $this->query($query);
        $queryConvert="ALTER TABLE `".$table."` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";
        $output .=  '<hr>'.$queryConvert.'<br>';
        $this->query($queryConvert);
        
        //Change Field
        if ($table == $this->config['table_prefix'].'pages') {
            $cols = $this->LoadAll("SHOW COLUMNS FROM ".$table);
            $dataQuery = "SELECT * FROM ".$table;
            $output .=  $dataQuery.'<br>';
            $data = $this->LoadAll($dataQuery);
            foreach ($cols as $row) {
                // $colQuery = 'ALTER TABLE '.$table.' MODIFY `'.$row['Field'].'` '.$row['Type'].' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
                // $output .=  $colQuery.'<br>';
                if ($row['Type']=='mediumtext' or $row['Type']=='text' or $row['Type']=='longtext' or $row['Type']=="blob") {
                    // Printing results in HTML
                    foreach ($data as $line) {
                        //Convert TO String                     
                        $transform = \ForceUTF8\Encoding::toUTF8(utf8_decode($line[$row['Field']]));
                        $transform = mysqli_real_escape_string($this->dblink, $transform);
                        $updateQuery = 'UPDATE '.$table.' SET `'.$row['Field'].'` = "'.$transform.'" WHERE `id`="'.$line['id'].'"';
                        $this->query($updateQuery);
                        //if ($line['id'] == '161') { $output .= '<h1>'.$transform.'</h1>';}
                    }
                }
            }
        }
        if ($table == $this->config['table_prefix'].'nature') {
            $cols = $this->LoadAll("SHOW COLUMNS FROM ".$table);
            $dataQuery = "SELECT * FROM ".$table;
            $output .=  $dataQuery.'<br>';
            $data = $this->LoadAll($dataQuery);
            foreach ($cols as $row) {
                if (strstr($row['Type'], 'varchar') or $row['Type']=='mediumtext' or $row['Type']=='text' or $row['Type']=='longtext' or $row['Type']=="blob") {
                    // Printing results in HTML
                    foreach ($data as $line) {
                        //Convert TO String
                        $transform = \ForceUTF8\Encoding::toUTF8(utf8_decode($line[$row['Field']]));
                        $transform = mysqli_real_escape_string($this->dblink, $transform);
                        $updateQuery = 'UPDATE '.$table.' SET `'.$row['Field'].'` = "'.$transform.'" WHERE `bn_id_nature`="'.$line['bn_id_nature'].'"';
                        $this->query($updateQuery);
                        //if ($line['id'] == '161') { $output .= '<h1>'.$transform.'</h1>';}
                    }
                }
            }
        }

        $output .=  "Complete Table: <b>".$table."</b><br>";

    }

    $output .=  "<h3>Complete ALL</h3>";
    echo  $this->header()
     .'<div class="alert alert-success">'._t('handler dbutf8 : toutes les tables de la base de données ont été transformées en utf8.').'</div>'
     .$output
     .$this->footer();
} else {
    echo $this->header()
     .'<div class="alert alert-danger">'._t('handler dbutf8 : réservé aux administrateurs.').'</div>'
     .$this->footer();
}