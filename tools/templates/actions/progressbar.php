<?php
if (!defined("WIKINI_VERSION"))
{
        die ("acc&egrave;s direct interdit");
}

// valeur de la progressbar
$val = $this->GetParameter('val');
if (empty($val)) {
	$error = ' param&egrave;tre "val" obligatoire.';
} elseif (!is_numeric($val) || $val<0 || $val > 100) {
	$error = ' le param&egrave;tre "val" doit étre un chiffre entre 0 et 100.';
}

// classe css supplémentaire pour changer le look
$class = $this->GetParameter('class');
$class = 'progressbar progress '.$class;


if (isset($error)) {
        echo '<div class="alert alert-danger">
        <strong>Action {{progressbar ..}}</strong> : '.$error.'
      </div>'."\n";
}
else {
	echo '<div class="'.$class.'">
    <div class="bar"
    style="width: '.$val.'%;"></div>
    </div>'."\n";
}
?>
