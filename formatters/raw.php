<?php

$rawInclusionLines = false;

try {
    $rawInclusionPin = (new YesWiki\Bazar\Service\SsrfUrlValidator())->curlPin($text, ['http', 'https']);
    $rawInclusionHandle = curl_init($text);
    curl_setopt_array($rawInclusionHandle, $rawInclusionPin + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ]);
    $rawInclusionBody = curl_exec($rawInclusionHandle);
    curl_close($rawInclusionHandle);
    if (is_string($rawInclusionBody) && $rawInclusionBody !== '') {
        $rawInclusionLines = preg_split('/(?<=\n)/', $rawInclusionBody);
    }
} catch (Throwable $rawInclusionError) {
    $rawInclusionLines = false;
}

if ($rawInclusionLines) {
    foreach ($rawInclusionLines as $line) {
        if (!preg_match("/\[\[\|(\S*)(\s+(.+))?\]\]/", $line, $matches)) {
            echo $line;
        }
    }
}
