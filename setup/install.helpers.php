<?php

/**
 * Communique le resultat d'un test :
 * -- affiche OK si elle l'est
 * -- affiche un message d'erreur dans le cas contraire.
 *
 * @param string $text        Label du test
 * @param bool   $condition   Résultat de la condition testée
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
    }
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

function myLocation()
{
    list($url) = explode('?', $_SERVER['REQUEST_URI']);

    return $url;
}

/**
 * Render a Twig SQL template file.
 *
 * @param string $templateFile Path to the .sql.twig template file
 * @param array  $variables    Variables to pass to the template
 * @return string The rendered SQL content
 */
function renderSqlTwigTemplate($templateFile, $variables = [])
{
    require_once 'vendor/autoload.php';

    $templateDir = dirname($templateFile);
    $templateName = basename($templateFile);

    $loader = new \Twig\Loader\FilesystemLoader($templateDir);
    $twig = new \Twig\Environment($loader, [
        'autoescape' => false, // SQL templates should not be HTML-escaped
    ]);

    return $twig->render($templateName, $variables);
}

/**
 * Execute a SQL file (plain or Twig template) on the database.
 *
 * @param PDO    $dblink       Database connection
 * @param string $sqlFile      Path to .sql or .sql.twig file
 * @param array  $replacements Variables/replacements for the template
 * @param string $driver       Database driver ('mysql', 'sqlite', 'pgsql')
 * @return bool Success status
 */
function querySqlFile($dblink, $sqlFile, $replacements = [], $driver = 'mysql')
{
    // Check if a Twig template version exists
    $twigFile = $sqlFile . '.twig';
    if (file_exists($twigFile)) {
        // Use Twig template
        $variables = array_merge($replacements, ['driver' => $driver]);

        // Escape values for SQL (but not for Twig processing)
        foreach ($variables as $key => $value) {
            if (is_string($value) && $key !== 'driver') {
                $quoted = $dblink->quote($value);
                $variables[$key] = substr($quoted, 1, -1); // Remove surrounding quotes
            }
        }

        return true;
    }
    exit(_t('SQL_FILE_NOT_FOUND') . ' "' . $sqlFile . '".');
}
