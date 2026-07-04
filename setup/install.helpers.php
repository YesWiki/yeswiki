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
 * YesWiki's own template syntax ({{ }} and {# #}) is used verbatim inside the
 * SQL content (default page content), so Twig is configured with different
 * delimiters ([[ ]] / <% %> / <# #>) to avoid parsing that content as Twig.
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
    $twig->setLexer(new \Twig\Lexer($twig, [
        'tag_comment' => ['<#', '#>'],
        'tag_block' => ['<%', '%>'],
        'tag_variable' => ['[[', ']]'],
        'interpolation' => ['#{', '}'],
    ]));

    return $twig->render($templateName, $variables);
}

/**
 * Split a SQL script into individual statements, respecting single-quoted
 * string literals (including '' escaped quotes) so that semicolons inside
 * string values are not mistaken for statement separators.
 *
 * @param string $sql
 * @return string[]
 */
function splitSqlStatements($sql)
{
    $statements = [];
    $current = '';
    $inString = false;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $current .= $char;
        if ($char === "'") {
            if ($inString && ($sql[$i + 1] ?? '') === "'") {
                $current .= "'";
                ++$i;
                continue;
            }
            $inString = !$inString;
            continue;
        }
        if ($char === ';' && !$inString) {
            $statements[] = $current;
            $current = '';
        }
    }
    if (trim($current) !== '') {
        $statements[] = $current;
    }

    return array_values(array_filter(array_map('trim', $statements), function ($statement) {
        return $statement !== '';
    }));
}

/**
 * Render a .sql.twig template for the given driver, substitute the
 * {{keyword}} placeholders (prefix, WikiName, password, email, rootPage,
 * url...) and execute the resulting statements on the database.
 *
 * @param PDO    $dblink       Database connection
 * @param string $sqlFile      Path to the .sql.twig file
 * @param array  $replacements Values for the {{keyword}} placeholders
 * @param string $driver       Database driver ('mysql', 'sqlite', 'pgsql')
 * @return bool Success status
 */
function querySqlFile($dblink, $sqlFile, $replacements = [], $driver = 'mysql')
{
    if (!file_exists($sqlFile)) {
        exit(_t('SQL_FILE_NOT_FOUND') . ' "' . $sqlFile . '".');
    }

    $sql = renderSqlTwigTemplate($sqlFile, ['driver' => $driver]);

    foreach ($replacements as $keyword => $value) {
        $quoted = $dblink->quote((string) $value);
        $sql = str_replace('{{' . $keyword . '}}', substr($quoted, 1, -1), $sql);
    }

    try {
        foreach (splitSqlStatements($sql) as $statement) {
            $dblink->exec($statement);
        }
    } catch (\PDOException $e) {
        return false;
    }

    return true;
}
