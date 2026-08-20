<?php

use YesWiki\Core\Service\ArchiveFilename;

/**
 * Communique le resultat d'un test :
 * -- affiche OK si elle l'est
 * -- affiche un message d'erreur dans le cas contraire.
 *
 * @param string $text        Label du test
 * @param bool   $condition   Résultat de la condition testée
 * @param string $errortext   Message en cas d'erreur
 * @param string $stopOnError Si positionnée é 1 (par défaut), termine le
 *                            script si la condition n'est pas vérifiée
 *
 * @return int 0 si la condition est vraie et 1 si elle est fausse
 */
function test($text, $condition, $errorText = '', $stopOnError = 1)
{
    echo "$text ";
    if ($condition) {
        echo '<span class="text-success">' . _t('OK') . "</span><br />\n";

        return 0;
    } else {
        echo '<span class="text-danger">' . _t('FAIL') . '</span>';
        if ($errorText) {
            echo ': ',$errorText;
        }
        echo "<br />\n";
        if ($stopOnError) {
            echo "<br />\n<div class=\"alert alert-danger alert-error\"><strong>" . _t('END_OF_INSTALLATION_BECAUSE_OF_ERRORS') . ".</strong></div>\n";
            echo "<script>
                document.write('<div class=\"form-actions\"><a class=\"btn btn-large btn-primary revenir\" href=\"javascript:history.go(-1);\">" . _t('GO_BACK') . "</a></div>');
                </script>\n";
            echo "</body>\n</html>\n";
            exit;
        }

        return 1;
    }
}

/**
 * Where the backup archives are looked for, as read from the configuration being installed.
 */
function backupsFolder($config)
{
    $folder = $config['archive']['privatePath'] ?? '';
    if (!is_string($folder) || $folder === '' || $folder === '%TMP') {
        $folder = 'private/backups';
    }

    return rtrim($folder, '/\\');
}

function humanFileSize($bytes)
{
    foreach (['', 'K', 'M', 'G'] as $unit) {
        if ($bytes < 1024 || $unit === 'G') {
            return round($bytes) . " $unit";
        }
        $bytes = $bytes / 1024;
    }
}

/**
 * The archives that can be restored, most recent first.
 */
function availableBackups($config)
{
    $folder = backupsFolder($config);
    if (!is_dir($folder)) {
        return [];
    }

    $backups = [];
    foreach (scandir($folder) as $filename) {
        $parts = ArchiveFilename::parse($filename);
        if (!empty($parts)) {
            list($hours, $minutes) = explode('-', $parts['time']);
            $backups[] = [
                'filename' => $filename,
                'date' => "{$parts['date']} $hours:$minutes",
                'type' => $parts['type'],
                'source' => $parts['source'],
                'size' => humanFileSize(filesize("$folder/$filename")),
            ];
        }
    }
    usort($backups, function ($a, $b) {
        return strcmp($b['filename'], $a['filename']);
    });

    return $backups;
}

function myLocation()
{
    list($url) = explode('?', $_SERVER['REQUEST_URI']);

    return $url;
}

function querySqlFile($dblink, $sqlFile, $replacements = [])
{
    if ($sql = file_get_contents($sqlFile)) {
        foreach ($replacements as $keyword => $replace) {
            $sql = str_replace(
                '{{' . $keyword . '}}',
                mysqli_real_escape_string($dblink, $replace),
                $sql
            );
        }
        // echo '<hr><pre>';var_dump($sql);echo '</pre><hr>'; # DEBUG SQL
        if (!mysqli_multi_query($dblink, $sql)) {
            return false;
        }
        while (mysqli_more_results($dblink)) {
            if (!mysqli_next_result($dblink)) {
                return false;
            }
        }

        return true;
    } else {
        exit(_t('SQL_FILE_NOT_FOUND') . ' "' . $sqlFile . '".');
    }
}
