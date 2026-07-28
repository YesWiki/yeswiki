<?php

namespace YesWiki\Core\Field;

use Field;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Core\Attach;
use YesWiki\Identity\Service\InputFilter;
use YesWiki\Core\Service\AssetsManager;
use YesWiki\Core\Service\EntryDateService;
use YesWiki\Core\Service\EntryManager;
use YesWiki\Core\Service\EventDispatcher;
use YesWiki\Identity\Service\Guard;
use YesWiki\Core\Service\HibernationService;
use YesWiki\Core\Service\HtmlPurifierService;

#[\Field(['fichier'])]
class FileField extends BazarField
{
    protected $readLabel;
    protected const FIELD_MAX_SIZE = 14;
    protected const FIELD_READ_LABEL = 6;
    protected const FIELD_AUTHORIZED_EXTS_LABEL = 7;

    protected $attach;
    protected $maxSize;
    protected $authorizedExts;

    /**
     * Check if a value is a URL.
     */
    protected function isUrl(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_URL) !== false;
    }

    public function __construct(array $values, ContainerInterface $services)
    {
        parent::__construct($values, $services);

        $this->propertyName = $this->type . $this->name;
        $this->readLabel = empty(trim($values[self::FIELD_READ_LABEL])) ? _t('BAZ_FILEFIELD_FILE') : $values[self::FIELD_READ_LABEL];
        $this->attach = null;
        $exts = $values[self::FIELD_AUTHORIZED_EXTS_LABEL] ?? '';
        $exts = is_string($exts) && !empty(trim($exts))
            ? explode(',', trim($exts))
            : [];
        $exts = array_map('trim', $exts);
        $this->authorizedExts = array_filter($exts, function ($ext) {
            return preg_match('/^\.[a-z0-9]{1,4}+$/', $ext);
        });
        $maxFieldSize = $values[self::FIELD_MAX_SIZE]
            ? $this->getWiki()->parse_size($values[self::FIELD_MAX_SIZE])
            : 0;

        // take the min size limit, excluding 0 values that mean no limit
        $this->maxSize = min(array_filter(
            [
                $maxFieldSize,
                $this->getService(ParameterBagInterface::class)->get('max-upload-size'), ],
        ));
    }

    protected function renderInput($entry)
    {
        $wiki = $this->getWiki();
        $value = $this->getValue($entry);
        $deletedFile = false;
        $isUrl = $this->isUrl($value);
        $wiki->services->get(AssetsManager::class)->AddJavascriptFile('javascripts/inputs/file-field.js');

        if (!empty($value) && !$isUrl) {
            if (!empty($entry) && isset($_GET['delete_file']) && $_GET['delete_file'] === $value) {
                if ($this->isAllowedToDeleteFile($entry, $value)) {
                    if (substr($value, 0, strlen($this->defineFilePrefix($entry))) == $this->defineFilePrefix($entry)) {
                        $attach = $this->getAttach();
                        $rawFileName = $this->getService(InputFilter::class)->filterInput(INPUT_GET, 'delete_file', FILTER_SANITIZE_FULL_SPECIAL_CHARS, false, 'string');
                        if (!empty($rawFileName)) {
                            $attach->fmDelete($rawFileName);
                        }
                    } else {
                        // do not delete file if not same entry name (only remove from this entry)
                        $deletedFile = true;
                        $this->updateEntryAfterFileDelete($entry);
                    }
                } else {
                    $alertMessage = '<div class="alert alert-info">' . _t('BAZ_DROIT_INSUFFISANT') . '</div>' . "\n";
                }
            }
        }

        return ($alertMessage ?? '') . $this->render(
            '@core/inputs/file.twig',
            empty($value) || !file_exists($this->getBasePath() . $value) || $deletedFile
            ? [
                'maxSize' => $this->maxSize,
                'isUrl' => false,
            ]
            : [
                'value' => $value,
                'maxSize' => $this->maxSize,
                'isUrl' => false,
                'shortFileName' => $this->getShortFileName($value),
                'fileUrl' => $this->getBasePath() . $value,
                'deleteUrl' => empty($entry) ? '' : $this->getWiki()->href('edit', $entry['tag'], ['delete_file' => $value], false),
                'isAllowedToDeleteFile' => empty($entry) ? false : $this->isAllowedToDeleteFile($entry, $value),
            ]
        );
    }

    /*
    *	indicates if tag must be set before to format the value
    */

    public function requireIDFiche()
    {
        return true;
    }

    public function formatValuesBeforeSave($entry)
    {
        $value = $this->getValue($entry);

        // Check if a URL was submitted
        $urlPropertyName = $this->propertyName . '_url';
        $urlValue = $entry[$urlPropertyName] ?? null;
        if (!empty($urlValue) && $this->isUrl($urlValue)) {
            return [
                $this->propertyName => $urlValue,
                'fields-to-remove' => [$urlPropertyName],
            ];
        }

        // Check if the current value is a URL (keep it if no new file uploaded)
        if ($this->isUrl($value) && empty($_FILES[$this->propertyName]['name'])) {
            return [$this->propertyName => $value];
        }

        $params = $this->getService(ParameterBagInterface::class);
        if (!empty($_FILES[$this->propertyName]['name']) && !empty($entry['tag'])) {
            $rawFileName = filter_var($_FILES[$this->propertyName]['name'], FILTER_UNSAFE_RAW);
            $rawFileName = in_array($rawFileName, [false, null], true) ? '' : htmlspecialchars(strip_tags($rawFileName));
            $sanitizedFilename = $this->sanitizeFilename($rawFileName);
            $fileName = "{$this->getPropertyName()}_$sanitizedFilename";
            $filePath = $this->getFullFileName($fileName, $entry['tag'], true);

            $pathinfo = pathinfo($filePath);
            $extension = strtolower($pathinfo['extension']);
            $extension = preg_replace('/_$/', '', $extension);
            if ($extension != '' && in_array($extension, array_keys($params->get('authorized-extensions')))) {
                if (!file_exists($filePath)) {
                    if ($_FILES[$this->propertyName]['size'] > $this->maxSize) {
                        throw new \Exception(_t('BAZ_FILEFIELD_TOO_LARGE_FILE', ['fileMaxSize' => $this->maxSize]));
                    }
                    move_uploaded_file($_FILES[$this->propertyName]['tmp_name'], $filePath);
                    chmod($filePath, 0755);

                    if (in_array($extension, ['svg', 'html', 'htm'])) {
                        $purifier = $this->getService(HtmlPurifierService::class);
                        $purifier->cleanFile($filePath, $extension);
                    }
                } else {
                    echo _t('BAZ_FILE_ALREADY_EXISTING') . '<br />';
                }
            } else {
                echo _t('BAZ_NOT_AUTHORIZED_FILE') . '<br />';

                return [$this->propertyName => ''];
            }

            return [$this->propertyName => basename($filePath)];
        } elseif (!empty($value)) {
            return [$this->propertyName => file_exists($this->getBasePath() . $value) ? $value : ''];
        }

        return [$this->propertyName => ''];
    }

    protected function renderStatic($entry)
    {
        $value = $this->getValue($entry);

        // Handle URL value
        if ($this->isUrl($value)) {
            return $this->render('@core/fields/file.twig', [
                'value' => $value,
                'fileUrl' => $value,
                'shortFileName' => basename(parse_url($value, PHP_URL_PATH)) ?: $value,
                'isUrl' => true,
            ]);
        }

        $basePath = $this->getBasePath();
        if (!empty($value) && file_exists($basePath . $value)) {
            $shortFileName = $this->getShortFileName($value);

            return $this->render('@core/fields/file.twig', [
                'value' => $value,
                'fileUrl' => ($shortFileName == $value)
                    ? $this->getWiki()->getBaseUrl() . '/' . $basePath . $value
                    : $this->getWiki()->Href('download', $entry['tag'] . '_' . $this->getPropertyName(), ['file' => $value], false),
                'shortFileName' => $shortFileName,
                'isUrl' => false,
            ]);
        }

        return '';
    }

    /**
     * check if user is allowed to delete file.
     */
    protected function isAllowedToDeleteFile(array $entry, string $fileName): bool
    {
        return !$this->getService(HibernationService::class)->isWikiHibernated()
            && $this->getService(Guard::class)->isAllowed('supp_fiche', $entry['owner'] ?? '');
    }

    /**
     * define file prefix.
     *
     * @return string $prefixFileName
     */
    protected function defineFilePrefix(array $entry)
    {
        return $entry['tag'] . '_' . $this->getPropertyName() . '_';
    }

    /**
     * method to get the filename from the value.
     *
     * @return string $shortFileName
     */
    protected function getShortFileName(string $longFileName): string
    {
        $attach = $this->getAttach();

        $fullFileName = "{$this->getBasePath()}$longFileName";
        $fileNameInfos = file_exists($fullFileName) ? $attach->decodeLongFilename($fullFileName) : [];

        unset($attach);

        $shortFileName = (empty($fileNameInfos['name']))
            ? $longFileName
            : (
                (preg_match("/^{$this->getPropertyName()}_(.*)$/m", "{$fileNameInfos['name']}.{$fileNameInfos['ext']}", $match)
                && !empty($match[1]))
                ? $match[1]
                : "{$fileNameInfos['name']}.{$fileNameInfos['ext']}"
            );

        return $shortFileName;
    }

    public function getReadLabel(): string
    {
        return $this->readLabel;
    }

    public function getAuthorizedExts(): array
    {
        return $this->authorizedExts;
    }

    // change return of this method to keep compatible with php 7.3 (mixed is not managed)
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return array_merge(
            parent::jsonSerialize(),
            [
                'readLabel' => $this->getReadLabel(),
                'authorizedExts' => $this->getAuthorizedExts(),
            ],
        );
    }

    protected function getFullFileName(string $fileName, string $tag, bool $newName = false): string
    {
        $wiki = $this->getWiki();
        $attach = $this->getAttach();
        // adjust $params
        $attach->file = $fileName;

        // current page
        $previousTag = $wiki->tag;
        $previousPage = $wiki->page;
        // fake page
        $wiki->tag = $tag;
        $wiki->page = [
            'tag' => $wiki->tag,
            'body' => '{##}',
            'time' => date('YmdHis'),
            'owner' => '',
            'user' => '',
        ];
        $fullFileName = $attach->GetFullFilename($newName);

        // reset params
        unset($attach);
        $wiki->tag = $previousTag;
        $wiki->page = $previousPage;

        return $fullFileName;
    }

    /**
     * sanitize filename.
     *
     * @return string $sanitizedFilename
     */
    protected function sanitizeFilename(string $filename): string
    {
        $attach = $this->getAttach();
        // Remove accents and spaces
        $sanitizedFilename = $attach->sanitizeFilename($filename);

        return $sanitizedFilename;
    }

    protected function getBasePath(): string
    {
        $attach = $this->getAttach();
        $basePath = $attach->GetUploadPath();

        return $basePath . (substr($basePath, -1) != '/' ? '/' : '');
    }

    protected function getAttach(): Attach
    {
        if (is_null($this->attach)) {
            $wiki = $this->getWiki();

            $this->attach = new Attach($wiki);
        }

        return $this->attach;
    }

    protected function updateEntryAfterFileDelete($entry)
    {
        $entryManager = $this->services->get(EntryManager::class);

        // unset value in entry from db without modifier from GET
        $entryFromDb = $entryManager->getOne($entry['tag']);
        if (!empty($entryFromDb)) {
            $previousGet = $_GET;
            $_GET = ['wiki' => $previousGet['wiki']];
            $previousPost = $_POST;
            $_POST = [];
            $previousRequest = $_REQUEST;
            $_REQUEST = [];

            // remove current field
            unset($entryFromDb[$this->propertyName]);

            // be careful to recurrence
            if (isset($entryFromDb['bf_date_fin_evenement_data']) && is_string($entryFromDb['bf_date_fin_evenement_data'])) {
                unset($entryFromDb['bf_date_fin_evenement_data']); // remove links to parent
            }

            $entryFromDb['antispam'] = 1;
            $entryFromDb['updated_at'] = date('Y-m-d H:i:s', time());
            $newEntry = $entryManager->update($entryFromDb['tag'], $entryFromDb, false, true);

            $_GET = $previousGet;
            $_POST = $previousPost;
            $_REQUEST = $previousRequest;

            // be careful to recurrence

            if (!empty($newEntry['tag'])
                && is_string($newEntry['tag'])
                && isset($newEntry['bf_date_fin_evenement'])) {
                $this->getService(EntryDateService::class)->followId($newEntry['tag']);
            }

            $errors = $this->services->get(EventDispatcher::class)->yesWikiDispatch('entry.updated', [
                'id' => $newEntry['tag'],
                'data' => $newEntry,
            ]);
        }
    }
}
