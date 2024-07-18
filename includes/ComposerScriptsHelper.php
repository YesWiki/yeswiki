<?php

namespace YesWiki\Core;

use Composer\Script\Event;

class ComposerScriptsHelper
{
    public static function postInstall(Event $event)
    {
        // clean test files from svg-sanitize
        echo "clean test files from svg-sanitize\n";
        array_map('unlink', glob('vendor/enshrined/svg-sanitize/tests/data/*.svg'));

        // clean example files from zebra_image
        echo "clean example files from zebra_image\n";
        array_map('unlink', glob('vendor/stefangabos/zebra_image/examples/images/*'));
        if (is_dir('vendor/stefangabos/zebra_image/examples/images/')) {
            rmdir('vendor/stefangabos/zebra_image/examples/images/');
        }
        array_map('unlink', glob('vendor/stefangabos/zebra_image/examples/*'));
        if (is_dir('vendor/stefangabos/zebra_image/examples')) {
            rmdir('vendor/stefangabos/zebra_image/examples');
        }
    }

    public static function postUpdate(Event $event)
    {
        self::postInstall($event);

        // update pdfjs-dist
        // Context

        // `pdfjs-dist` is available as javascript library via `yarn` but as pre-build package.
        // To use it as viewer in `<iframe>`, we have to download the official viewer package not available via `yarn`.

        // first get list of files
        $data = self::getPdfJsDistFiles();
        $fileData = self::extractPdfJsDistFileUrl($data);
        self::updatePdfJsDist($fileData);
    }

    private static function getPdfJsDistFiles(): array
    {
        $url = 'https://api.github.com/repos/mozilla/pdf.js/releases/latest';
        try {
            $content = file_get_contents($url, false, stream_context_create([
                'http' => [
                    'user_agent' => 'Composer',
                    'timeout' => 5, // total timeout in seconds
                ],
            ]));
        } catch (\Throwable $th) {
            return [];
        }
        if (!empty($content) && is_string($content)) {
            $data = json_decode($content, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * @return array ['fileName' => string, 'url' => string]
     */
    private static function extractPdfJsDistFileUrl(array $data): array
    {
        if (!empty($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (!empty($asset['name']) && is_string($asset['name'])
                    && !empty($asset['browser_download_url']) && is_string($asset['browser_download_url'])
                    && preg_match("/^pdfjs-(\d+)\.(\d+)\.(\d+)-dist\.zip$/i", $asset['name'], $match)) {
                    return [
                        'fileName' => $asset['name'],
                        'url' => $asset['browser_download_url'],
                        'major_revision' => $match[1],
                        'minor_revision' => $match[2],
                        'buxfix_revision' => $match[3],
                    ];
                }
            }
        }

        return [];
    }

    private static function updatePdfJsDist(array $params)
    {
        if (!empty($params['url'])) {
            if (is_dir('javascripts/vendor/')) {
                if (!is_dir('javascripts/vendor/pdfjs-dist/')
                    || self::pdfJsDistNeedsUpdate('javascripts/vendor/pdfjs-dist/revision.json', $params)) {
                    try {
                        $zipContent = file_get_contents($params['url'], false, stream_context_create([
                            'http' => [
                                'user_agent' => 'Composer',
                                'timeout' => 15, // total timeout in seconds
                            ],
                        ]));
                        if (!empty($zipContent)) {
                            $temp = tmpfile();
                            if ($temp) {
                                $tmpFilename = stream_get_meta_data($temp)['uri'];
                                if (file_put_contents($tmpFilename, $zipContent) !== false) {
                                    $zip = new \ZipArchive();
                                    if ($zip->open($tmpFilename)) {
                                        if (is_dir('javascripts/vendor/pdfjs-dist/')) {
                                            self::deleteFolder('javascripts/vendor/pdfjs-dist/');
                                        }
                                        if (!is_dir('javascripts/vendor/pdfjs-dist/')) {
                                            $zip->extractTo('javascripts/vendor/pdfjs-dist/');
                                            file_put_contents(
                                                'javascripts/vendor/pdfjs-dist/revision.json',
                                                json_encode([
                                                    'major_revision' => $params['major_revision'],
                                                    'minor_revision' => $params['minor_revision'],
                                                    'buxfix_revision' => $params['buxfix_revision'],
                                                ])
                                            );
                                            file_put_contents(
                                                'javascripts/vendor/pdfjs-dist/.htaccess',
                                                <<<TXT
                                                <IfModule mod_mime.c>
                                                  AddType text/javascript .mjs
                                                </IfModule>
                                                TXT
                                            );
                                            if (file_exists('tools/attach/libs/pdf-viewer.php')) {
                                                copy('tools/attach/libs/pdf-viewer.php', 'javascripts/vendor/pdfjs-dist/web/pdf-viewer.php');
                                            }
                                            echo "Pdfjs-dist updated ! \n";
                                            // Clean not needded files
                                            array_map('unlink', glob('javascripts/vendor/pdfjs-dist/web/*.pdf'));
                                            array_map('unlink', glob('javascripts/vendor/pdfjs-dist/web/*.js.map'));
                                            array_map('unlink', glob('javascripts/vendor/pdfjs-dist/build/*.js.map'));
                                        }
                                        $zip->close();
                                    } else {
                                        echo "!! Zip not downloaded : $zipContent\n";
                                    }
                                } else {
                                    echo "erro while putting zip into $tmpFileName\n";
                                }
                            } else {
                                echo "Not possible to create a tempfile !\n";
                            }
                        }
                    } catch (\Throwable $th) {
                        echo "error {$th->getMessage()}\n";
                        if (isset($zipContent)) {
                            echo "zipContent: $zipContent\n";
                        }
                    }
                    if (isset($temp) && $temp) {
                        fclose($temp);
                    }
                }
            }
        }
    }

    private static function pdfJsDistNeedsUpdate(string $filePath, array $params): bool
    {
        if (!is_file($filePath)) {
            return true;
        }
        try {
            $jsonContent = file_get_contents($filePath);
            if (!empty($jsonContent)) {
                $versionsData = json_decode($jsonContent, true);
                if (is_array($versionsData)) {
                    if (empty($versionsData['major_revision'])
                        || $versionsData['major_revision'] < $params['major_revision']
                        || empty($versionsData['minor_revision'])
                        || $versionsData['minor_revision'] < $params['minor_revision']
                        || empty($versionsData['buxfix_revision'])
                        || $versionsData['buxfix_revision'] < $params['buxfix_revision']
                    ) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $th) {
        }

        return false;
    }

    private static function deleteFolder($path)
    {
        $file2ignore = ['.', '..'];
        if (is_link($path)) {
            return unlink($path) !== false;
        } else {
            if ($res = opendir($path)) {
                $continue = true;
                while (($file = readdir($res)) !== false && $continue) {
                    if (!in_array($file, $file2ignore)) {
                        $continue = self::delete($path . '/' . $file);
                    }
                }
                closedir($res);
            }
            if ($continue) {
                return rmdir($path) !== false;
            } else {
                return false;
            }
        }

        return false;
    }

    private static function delete($path)
    {
        if (empty($path)) {
            return false;
        }
        if (is_file($path)) {
            if (unlink($path)) {
                return true;
            }

            return false;
        }
        if (is_dir($path)) {
            return self::deleteFolder($path);
        }
    }
}
