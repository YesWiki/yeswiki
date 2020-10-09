<?php
switch ($_GET['object']) {
  case 'list':
    $result = baz_valeurs_liste($_GET['id']); break;
  case 'form':
    $result = baz_valeurs_formulaire($_GET['id']); break;
  default:
    $result = "Type is not supported"; break;
}

echo json_encode($result)

?>
