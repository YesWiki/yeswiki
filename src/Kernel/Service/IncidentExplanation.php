<?php

namespace YesWiki\Kernel\Service;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use YesWiki\Files\Exception\StorageException;
use YesWiki\Kernel\Exception\CurlTimeoutException;
use YesWiki\Kernel\Exception\SpeaksToTheReader;

/** What a person who is not a technician can be told about a failure: its own sentence when it carries one written for them, a plain cause when its kind is known, else that something went wrong (end-users 01). */
#[Exclude]
final class IncidentExplanation
{
    public static function of(\Throwable $failure): string
    {
        for ($cause = $failure; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof SpeaksToTheReader && $cause->getMessage() !== '') {
                return $cause->getMessage();
            }
            if ($cause->getMessage() === _t('WIKI_IN_HIBERNATION')) {
                return _t('WIKI_IN_HIBERNATION');
            }
            if ($cause instanceof \PDOException) {
                return _t('INCIDENT_DATABASE');
            }
            if ($cause instanceof StorageException) {
                return _t('INCIDENT_STORAGE');
            }
            if ($cause instanceof CurlTimeoutException) {
                return _t('INCIDENT_REMOTE_TIMEOUT');
            }
        }

        return _t('INCIDENT_UNEXPECTED');
    }
}
