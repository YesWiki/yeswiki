<?php
/*
Une simple action pour tester le fonctionnement des triplets
*/

// TODO remove this action from the official package

if (!function_exists('test')) {
    // fonction récupérée de /setup/header.php
    /**
     * Communique le résultat d'un test :
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
            echo '<span class="ok">' . _t('OK') . "</span><br />\n";

            return 0;
        } else {
            echo '<span class="failed">' . _t('FAIL') . '</span>';
            if ($errorText) {
                echo ': ',$errorText;
            }
            echo "<br />\n";
            if ($stopOnError) {
                echo _t('END_OF_EXEC') . ".<br />\n";
                echo "</body>\n</html>\n";
                exit;
            }

            return 1;
        }
    }
}

$res = $this->InsertTriple('PagePrincipale', 'testproperty', 'testvalue');
test('Insert triple ...', $res == 0, "res = $res", false);
echo $this->GetTripleValue('PagePrincipale', 'testproperty') . '<br />';
$res = $this->UpdateTriple('PagePrincipale', 'testproperty', 'testvalue', 'new value');
test('Update triple ...', $res == 0, "res = $res", false);
echo $this->GetTripleValue('PagePrincipale', 'testproperty') . '<br />';
test('Triple exists (... new value)...', $this->TripleExists('PagePrincipale', 'testproperty', 'new value'), '', false);
test('Triple exists (... testvalue)...', $this->TripleExists('PagePrincipale', 'testproperty', 'testvalue'), '', false);
// misterproper
test('Delete triple ...', !$this->DeleteTriple('PagePrincipale', 'testproperty'), '', false);
