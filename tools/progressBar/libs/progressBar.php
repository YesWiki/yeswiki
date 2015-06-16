<?php
    header ("Content-type: image/png");
    
    $height = 15;
    $width = 50;
    $percent = 75;
    
    if (!empty($_GET['height']))   
        $height = intval($_GET['height']);
    if (!empty($_GET['width']))   
        $width = intval($_GET['width']);
    if (!empty($_GET['percent']))   
        $percent = intval($_GET['percent']);
    
    if ($percent > 100) $percent = 100;
    
    $image = imagecreate($width,$height);
    $noir = imagecolorallocate($image, 0, 0, 0);
    $blanc = imagecolorallocate($image, 255, 255, 255);
    if ($percent < 50)
        $color = imagecolorallocate($image, 255, 255 * $percent / 50, 0);
    else
        $color = imagecolorallocate($image, 255 * (100 - $percent) / 50 , 255, 0);

    imagefilledrectangle ($image, 0, 0, $width-1, $height-1, $noir);
    imagefilledrectangle ($image, 1, 1, $width-2, $height-2, $blanc);
    imagefilledrectangle ($image, 1, 1, ($width-2) * $percent / 100, $height-2, $color);
    
    
    imagepng($image);
    imagedestroy($image);
