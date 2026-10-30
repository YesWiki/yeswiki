<?php

namespace YesWiki\Admin\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use YesWiki\Admin\Exception\StopArchiveException;
use YesWiki\Admin\Message\CreateArchive;
use YesWiki\Admin\Message\RestoreArchive;
use YesWiki\Files\Service\LocalFiles;
use YesWiki\Files\Service\Storage;
use YesWiki\Kernel\Database\DumpRewriter;
use YesWiki\Kernel\Database\SqlStatementSplitter;
use YesWiki\Kernel\Entity\Event;
use YesWiki\Kernel\Queue\QueueConsumerMode;
use YesWiki\Kernel\Service\ConfigurationFileProvider;
use YesWiki\Kernel\Service\ConfigurationLiteral;
use YesWiki\Kernel\Service\ConfigurationService;
use YesWiki\Kernel\Service\ConsoleService;
use YesWiki\Kernel\Service\DbService;
use YesWiki\Kernel\Service\HibernationService;
use YesWiki\Kernel\Service\Queue\QueueConsumer;
use YesWiki\Kernel\Service\Queue\QueueFallback;
use YesWiki\Kernel\Service\Queue\QueueRecorder;
use YesWiki\Kernel\Service\Queue\QueueRequestTrigger;
use YesWiki\Kernel\Service\Queue\QueueSettings;
use YesWiki\Kernel\Service\Queue\QueueStatistics;
use YesWiki\Kernel\Service\UrlFormatter;

class ArchiveService implements EventSubscriberInterface
{
    /** The folders an archive takes, so that subwikis and unrelated folders stay out. */
    public const FOLDERS_TO_INCLUDE = [
        'actions',
        'custom',
        'docs',
        'files',
        'handlers',
        'javascripts',
        'lang',
        'private',
        'src',
        'styles',
        'templates',
        'tests',
        'extensions',
        'themes',
        'vendor',
    ];

    /** The folders an archive leaves out, the existing backups among them. */
    public const FOLDERS_TO_EXCLUDE = [
        '.git',
        'node_modules',
        'private/backups',
    ];

    public const DEFAULT_PARAMS_TO_ANONYMIZE = [
        'db_host' => '',
        'db_database' => '',
        'db_user' => '',
        'db_password' => '',
        'contact_smtp_host' => '',
        'contact_smtp_user' => '',
        'contact_smtp_pass' => '',
        'api_allowed_keys' => [],
    ];
    public const PARAMS_KEY_IN_WAKKA = 'archive';
    public const KEY_FOR_FOLDERS_TO_INCLUDE = 'foldersToInclude';
    public const KEY_FOR_FOLDERS_TO_EXCLUDE = 'foldersToExclude';
    public const KEY_FOR_HIDE_CONFIG_VALUES = 'hideConfigValues';
    protected const CODE_SIZE_ALLOWANCE = 300 * 1024 * 1024;

    /** Where an archive being made reports on itself. */
    public const PROGRESS_FOLDER = 'cache/archive';
    public const ARCHIVE_SUFFIX = '_archive';
    public const ARCHIVE_ONLY_FILES_SUFFIX = '_archive_only_files';
    public const ARCHIVE_ONLY_DATABASE_SUFFIX = '_archive_only_db';
    public const PRIVATE_FOLDER_NAME_IN_ZIP = 'private/backups';
    public const SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP = 'content.sql';
    public const INFO_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP = DumpRewriter::INFO_FILENAME;
    public const PRIVATE_FOLDER_README_DEFAULT_CONTENT = "# Description of the usage of folder private/backups\n\n" .
        "This folder is **reserved to backups**.\n\n" .
        "It **MUST NOT** be accessible from the internet.\n\n" .
        " - On Apache server, check that the file `.htaccess` is taken in count.\n" .
        " - On Nginx server or other, configure the server to **deny all** access on this folder\n";

    /**
     * The modes in which the queue runs only when something calls it: there the admin's own request runs the backup or the restore, once the response has left (spec 9.5).
     *
     * `request-spawn` among them: nothing says before the launch that its process will run, and
     * if it does not, the end of visitors' requests stands in for it, on a request's budget.
     * In `worker` and `binary` a consumer of its own takes the message, while it is seen at work
     * (QueueFallback::consumerIsGone()); otherwise the admin's request runs it too.
     */
    private const RUN_IN_THE_ADMINS_REQUEST = [QueueConsumerMode::Request, QueueConsumerMode::Api, QueueConsumerMode::Command, QueueConsumerMode::RequestSpawn];

    /**
     * How long a run the admin's request is to make waits before any pass may take it instead.
     *
     * The request takes it as soon as the response has left; meanwhile another visitor's
     * end-of-request pass would otherwise take it first, on a request's budget, with PHP's time
     * limit still on. Should the request end without running it, the queue gets it after this.
     */
    private const LEFT_TO_THE_ADMINS_REQUEST_SECONDS = 300;

    protected ConfigurationService $configurationService;
    protected ConsoleService $consoleService;
    protected DbService $dbService;
    protected ParameterBagInterface $params;
    protected HibernationService $hibernationService;

    protected UrlFormatter $urlFormatter;
    protected Storage $storage;

    public function __construct(
        ConfigurationService $configurationService,
        ConsoleService $consoleService,
        DbService $dbService,
        ParameterBagInterface $params,
        HibernationService $hibernationService,
        UrlFormatter $urlFormatter,
        Storage $storage,
        private readonly LocalFiles $localFiles,
        private readonly MessageBusInterface $bus,
        private readonly QueueSettings $queueSettings,
        private readonly QueueRequestTrigger $requestTrigger,
        private readonly QueueStatistics $queueStatistics,
        private readonly QueueFallback $queueFallback,
    ) {
        $this->storage = $storage;
        $this->urlFormatter = $urlFormatter;
        $this->configurationService = $configurationService;
        $this->consoleService = $consoleService;
        $this->dbService = $dbService;
        $this->params = $params;
        $this->hibernationService = $hibernationService;
    }

    /**
     * archive data in zip file.
     *
     * @param string|OutputInterface       &$output
     * @param array<array-key, mixed>      $foldersToInclude
     * @param array<array-key, mixed>      $foldersToExclude
     * @param array<string, mixed>|null    $hideConfigValuesParams the config keys to anonymize, null for the configured ones
     * @param array<array-key, mixed>|null $onlyFolders            the only folders the zip holds, instead of FOLDERS_TO_INCLUDE widened by $foldersToInclude
     *
     * @return string the path of the archive, or '' when the run stopped before writing one
     *
     * @throws \Exception
     */
    public function archive(
        &$output,
        bool $savefiles = true,
        bool $savedatabase = true,
        array $foldersToInclude = [],
        array $foldersToExclude = [],
        ?array $hideConfigValuesParams = null,
        string $uid = '',
        ?array $onlyFolders = null
    ): string {
        $vStatus = $this->getArchivingStatus();

        $inputFile = '';
        $outputFile = '';

        if (!$vStatus['canArchive']) {
            $vMessages = $this->getCannotArchiveDetails($vStatus);

            // The status is not this run's to lift: an admin's hibernation, an update, or another run's `archiving`.
            $this->writeOutput($output, 'STOP', true, $outputFile);

            throw new \Exception(_t('AU_CANNOT_ARCHIVE') . implode(', ', $vMessages));
        }
        $privatePath = $this->getPrivateFolder();

        if (!empty($uid)) {
            $info = $this->getInfoFromFile();
            if (isset($info[$uid])) {
                $inputFile = (string)$info[$uid]['input'];
                $outputFile = (string)$info[$uid]['output'];
            }
        }
        if (!empty($outputFile)) {
            $this->storage->write($outputFile, '');
        }

        $this->storage->write("$privatePath/tmpTestFile000.txt", 'test');
        $error = !$this->localPrivateFolderNotAvailableOnInternet($privatePath, 'tmpTestFile000.txt');
        if ($this->storage->fileExists("$privatePath/tmpTestFile000.txt")) {
            $this->storage->delete("$privatePath/tmpTestFile000.txt");
        }
        if ($error) {
            $this->writeOutput($output, '! Private folder available on the internet', true, $outputFile);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            return '';
        }

        $this->writeOutput($output, '=== Checking free space ===', true, $outputFile);
        $blacklistedRootFolders = $this->generateListRootFolders('black', $foldersToExclude);
        try {
            $this->assertEnoughtSpace($blacklistedRootFolders);
        } catch (\Throwable $th) {
            $this->writeOutput($output, 'There is not enough free space.', true, $outputFile);
            $this->writeOutput($output, "=> {$th->getMessage()}", true, $outputFile);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);
            throw $th;
        }
        $this->writeOutput($output, 'There is enough free space.', true, $outputFile);

        if ($this->checkIfNeedStop($inputFile)) {
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            return '';
        }
        $onlyDb = false;
        if (!$savefiles && !$savedatabase) {
            throw new \Exception("Invalid options : It is not possible to use 'savefiles = false' and 'savedatabase = false' options in same time.");
        } elseif (!$savefiles) {
            $type = 'only_db';
            $onlyDb = true;
        } elseif (!$savedatabase) {
            $type = 'only_files';
        } else {
            $type = 'full';
        }

        $archiveFileName = ArchiveFilename::forNow($type, $this->configString('base_url'));
        $location = $privatePath . '/' . $archiveFileName;
        if ($this->storage->fileExists($location)) {
            throw new \Exception('Zip file already existing !');
        }
        // As the file says it now: a consumer that has run for an hour holds the status it started with.
        if ($this->hibernationService->isWikiHibernatedNow()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        }

        try {
            $this->setWikiStatus();

            $written = $this->storage->withTemporaryFile('sql', function (string $sqlPath) use ($savedatabase, $inputFile, $outputFile, &$output, $location, $foldersToInclude, $blacklistedRootFolders, $onlyDb, $hideConfigValuesParams, $onlyFolders): ?bool {
                if ($savedatabase) {
                    $this->dumpDatabaseInto($sqlPath);
                }

                if ($this->checkIfNeedStop($inputFile)) {
                    return null;
                }

                $this->writeOutput($output, '=== Creating zip archive ===', true, $outputFile);

                return $this->storage->withLocalTarget(
                    $location,
                    fn (string $local) => $this->createZip($local, $foldersToInclude, $blacklistedRootFolders, $output, $savedatabase ? $sqlPath : '', $onlyDb, $hideConfigValuesParams, $inputFile, $outputFile, $onlyFolders)
                );
            });

            if ($written === null) {
                $this->unsetWikiStatus();
                $this->writeOutput($output, 'STOP', true, $outputFile);

                return '';
            }

            if ($written) {
                $this->writeOutput($output, "Archive \"$location\" successfully created !", true, $outputFile);

                $this->cleanOldestFiles();

                $this->unsetWikiStatus();

                $this->writeOutput($output, 'END', true, $outputFile);
            } else {
                throw new StopArchiveException('Stop archive : not saved !');
            }
        } catch (StopArchiveException $ex) {
            $this->forget($location);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            return '';
        } catch (\Throwable $th) {
            $this->forget($location);
            $this->unsetWikiStatus();
            $this->writeOutput($output, 'STOP', true, $outputFile);

            throw $th;
        }

        return $location;
    }

    /**
     * @param array<string, mixed> $pStatus as getArchivingStatus() answers it
     *
     * @return list<string> one message per reason this wiki cannot be archived right now
     */
    public function getCannotArchiveDetails($pStatus)
    {
        $vMessages = [];

        if ($pStatus['archiving']) {
            $vMessages[] = _t('AU_ALREADY_ARCHIVING');
        }
        if ($pStatus['hibernated']) {
            $vMessages[] = _t('AU_SITE_IS_HIBERNATED');
        }
        if (!$pStatus['privatePathWritable']) {
            $vMessages[] = _t('AU_PRIVATE_PATH_NOT_WRITABLE');
        }
        if (!$pStatus['notAvailableOnTheInternet']) {
            $vMessages[] = _t('AU_PRIVATE_PATH_AVAILABLE_ON_INTERNET');
        }
        if (!$pStatus['enoughSpace']) {
            $vMessages[] = _t('AU_NOT_ENOUGHT_SPACE');
        }

        return $vMessages;
    }

    /**
     * Get YesWiki status
     * We must use the configuration service to have the value of wiki_status since it can be modified dynamically
     * and that ParameterBag is more static.
     *
     * @return string : the status
     */
    public function getWikiStatus()
    {
        $vConfig = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $vConfig->load();

        if (trim($vConfig['wiki_status'] ?? '') == '') {
            return 'running';
        }

        return trim($vConfig['wiki_status']);
    }

    /**
     * Test if YesWiki is read only
     * We must use the configuration service to have the value of wiki_status since it can be modified dynamically
     * and that ParameterBag is more static.
     *
     * @return bool : is read only
     */
    public function isReadOnly()
    {
        return in_array($this->getWikiStatus(), HibernationService::HIBERNATED, true);
    }

    /** @param mixed $token the token the update flow was handed back, when it was handed one */
    public function hasValidatedBackup($token): bool
    {
        if (empty($token) || !is_string($token)) {
            return false;
        }

        $privatePath = $this->getPrivateFolder();
        $info = $this->getInfoFromFile();
        $result = ($info[$token]['isForcedUpdate'] ?? false) === true;

        foreach ($info as $uid => $data) {
            if (($data['isForcedUpdate'] ?? false) === true) {
                $this->cleanUID($uid);
            }
        }

        return $result;
    }

    /**
     * retrieve the current status to archive.
     *
     * A backup no longer needs a process of its own (it goes through the queue), so nothing here
     * asks whether one can be started. A wiki left `archiving` by a run that died is set back to
     * running first: the browser that would have seen it die may be long gone.
     *
     * @return array<string, mixed> ['canArchive' => bool,'archiving' => bool, 'hibernated' => bool, 'privatePathWritable' => bool, 'notAvailableOnTheInternet' => bool, 'enoughSpace' => bool, 'estimatedSize' => int, 'freeSpace' => ?int]
     */
    public function getArchivingStatus(): array
    {
        $this->releaseWhatADeadRunLeft();

        $archiving = false;
        $hibernated = false;
        $privatePathWritable = true;
        $notAvailableOnTheInternet = true;
        if ($this->isReadOnly()) {
            switch ($this->getWikiStatus()) {
                case 'archiving':
                    $archiving = true;
                    break;
                case 'hibernate':
                    $hibernated = true;
                    break;
                default:
                    break;
            }
        }
        try {
            $privatePath = $this->getPrivateFolder();
        } catch (\Exception $th) {
            $privatePathWritable = false;
            $privatePath = '';
        }
        if (!empty($privatePath)) {
            if (!$this->canWriteFolder($privatePath)) {
                $privatePathWritable = false;
            } else {
                $tmpFileName = "$privatePath/tmp.txt";

                try {
                    $this->storage->write($tmpFileName, 'test');
                    if ($this->storage->read($tmpFileName) !== 'test') {
                        throw new \Exception('Bad content');
                    }
                    $notAvailableOnTheInternet = $this->localPrivateFolderNotAvailableOnInternet($privatePath, basename($tmpFileName));
                } catch (\Throwable $th) {
                    $privatePathWritable = false;
                } finally {
                    $this->forget($tmpFileName);
                }
            }
        }

        $estimatedSize = $this->estimateArchiveSize();
        $freeSpace = $this->freeSpaceForArchives();
        $enoughSpace = $freeSpace === null || $freeSpace >= $estimatedSize;

        $canArchive = (
            !$archiving
            && !$hibernated
            && $privatePathWritable
            && $notAvailableOnTheInternet
            && $enoughSpace
        );

        return compact(['canArchive', 'archiving', 'hibernated', 'privatePathWritable', 'notAvailableOnTheInternet', 'enoughSpace', 'estimatedSize', 'freeSpace']);
    }

    /**
     * get a token to force update.
     *
     * @return string $token
     */
    public function getForcedUpdateToken(): string
    {
        $status = $this->getArchivingStatus();
        $privatePath = $this->getPrivateFolder();
        $uidData = $this->getUID();
        $info = $this->getInfoFromFile();
        $uid = $uidData['uid'];
        if (empty($uid) || !isset($info[$uid])) {
            return '';
        }

        $info[$uid]['isForcedUpdate'] = true;
        $this->setInfoToFile($info);

        return $uid;
    }

    /**
     * Queue a backup (CreateArchive) and say which uid to follow it by.
     *
     * Where the queue runs only when something calls it (`request`, `api`, `command`), this very
     * request runs it once the admin has the response, without a time limit when PHP allows it:
     * the backup before an update, and the one another wiki fetches, must work on a wiki nobody
     * set up a consumer for. Elsewhere the consumer takes it.
     *
     * @param array<array-key, mixed>      $foldersToInclude
     * @param array<array-key, mixed>      $foldersToExclude
     * @param array<array-key, mixed>|null $onlyFolders      the only folders the zip holds, null for the usual ones
     *
     * @return string the uid to poll, empty when the queue could not take the backup
     */
    public function startArchive(
        bool $savefiles = true,
        bool $savedatabase = true,
        array $foldersToInclude = [],
        array $foldersToExclude = [],
        ?array $onlyFolders = null
    ): string {
        $uid = $this->getUID()['uid'];

        return $this->queue(new CreateArchive(
            $uid,
            $savefiles,
            $savedatabase,
            self::folderNames($foldersToInclude),
            self::folderNames($foldersToExclude),
            $onlyFolders === null ? null : self::folderNames($onlyFolders)
        )) ? $uid : '';
    }

    /**
     * @param array<array-key, mixed> $folders
     *
     * @return list<string>
     */
    private static function folderNames(array $folders): array
    {
        return array_values(array_map('strval', array_filter($folders, 'is_scalar')));
    }

    /**
     * Queues the run, notes its message under its uid, and has this request run it after the response where nothing else would soon.
     *
     * @return bool whether the queue took it; when it did not (its tables missing, the database
     *              down: journaled by the bus), the uid is forgotten
     */
    private function queue(CreateArchive|RestoreArchive $run): bool
    {
        $inThisRequest = in_array($this->queueSettings->mode(), self::RUN_IN_THE_ADMINS_REQUEST, true) || $this->queueFallback->consumerIsGone();
        $stamps = $inThisRequest ? [new DelayStamp(self::LEFT_TO_THE_ADMINS_REQUEST_SECONDS * 1000)] : [];
        $id = $this->bus->dispatch($run, $stamps)->last(TransportMessageIdStamp::class)?->getId();
        if ($id === null) {
            $this->cleanUID($run->uid);

            return false;
        }

        $info = $this->getInfoFromFile();
        if (isset($info[$run->uid])) {
            $info[$run->uid]['message'] = (int)$id;
            if ($run instanceof RestoreArchive) {
                // A restore sets no status, so its death lifts none (releaseWhatADeadRunLeft()).
                $info[$run->uid]['restore'] = true;
            }
            $this->setInfoToFile($info);
        }
        if ($inThisRequest) {
            $this->requestTrigger->pin((int)$id);
        }

        return true;
    }

    /**
     * get the list of archives in a array with information for each one.
     *
     * @return list<array<string, mixed>> newest first
     */
    public function getArchives(): array
    {
        $archives = [];
        $privatePath = $this->getPrivateFolder();
        foreach ($this->storage->files($privatePath) as $path) {
            $filename = basename($path);
            $parts = ArchiveFilename::parse($filename);
            if ($parts !== []) {
                [$year, $month, $day] = explode('-', $parts['date']);
                [$hours, $minutes, $seconds] = explode('-', $parts['time']);
                $archives[] = [
                    'filename' => $filename,
                    'date' => "{$parts['date']}T{$parts['time']}",
                    'year' => $year,
                    'month' => $month,
                    'day' => $day,
                    'hours' => $hours,
                    'minutes' => $minutes,
                    'seconds' => $seconds,
                    'type' => $parts['type'],
                    'source' => $parts['source'],
                    'size' => $this->storage->fileSize("$privatePath/$filename"),
                    'link' => $this->urlFormatter->href('', "api/archives/$filename"),
                ];
            }
        }
        usort($archives, function ($a, $b) {
            return strnatcmp($b['date'], $a['date']);
        });

        return $archives;
    }

    /**
     * get the path to an archive filename.
     *
     * @return string $filepath
     */
    public function getFilePath(string $filename): string
    {
        $privatePath = $this->getPrivateFolder();
        $filename = basename($filename);
        if (substr($filename, -4) != '.zip') {
            return '';
        }
        $filePath = "$privatePath/$filename";

        return $this->storage->fileExists($filePath) ? $filePath : '';
    }

    /**
     * Restore a backup archive (database and/or files).
     *
     * @throws \Exception
     */
    public function restoreArchive(string $filename, bool $restoreFiles = true, bool $restoreDatabase = true, bool $rewriteUrls = true, string $uid = ''): void
    {
        $outputFile = $this->outputFileFor($uid);
        $output = '';
        if ($outputFile !== '') {
            $this->storage->write($outputFile, '');
        }

        $filePath = $this->getFilePath($filename);
        if (empty($filePath)) {
            $this->writeOutput($output, "Archive not found: $filename", true, $outputFile);
            $this->writeOutput($output, 'STOP', true, $outputFile);

            throw new \Exception("Archive not found: $filename");
        }

        $this->writeOutput($output, "=== Restoring $filename ===", true, $outputFile);
        try {
            $this->storage->withLocalCopy($filePath, function (string $local) use ($filename, $restoreFiles, $restoreDatabase, $rewriteUrls, &$output, $outputFile): void {
                $this->restoreFromLocalArchive($local, $filename, $restoreFiles, $restoreDatabase, $rewriteUrls, $output, $outputFile);
            });
        } catch (\Throwable $th) {
            $this->writeOutput($output, '! ' . $th->getMessage(), true, $outputFile);
            $this->writeOutput($output, 'STOP', true, $outputFile);

            throw $th;
        }
        $this->writeOutput($output, 'Restore finished successfully', true, $outputFile);
        $this->writeOutput($output, 'END', true, $outputFile);
    }

    /**
     * Queue a restore (RestoreArchive) and say which uid to follow it by.
     *
     * A restore replays a whole database and unpacks a whole zip; on any wiki worth backing up
     * that outlasts a request's time limit, so it runs the way a backup does: in this request
     * after the response, without a time limit, where the queue runs only when called, and by
     * the consumer elsewhere -- reporting into the same progress files getUIDStatus() reads and
     * stopArchive() interrupts.
     *
     * @return string the uid to poll, empty when the queue could not take the restore
     *
     * @throws \Exception when there is no such archive
     */
    public function startRestore(
        string $filename,
        bool $restoreFiles = true,
        bool $restoreDatabase = true,
        bool $rewriteUrls = true
    ): string {
        if ($this->getFilePath($filename) === '') {
            throw new \Exception("Archive not found: $filename");
        }

        $uid = $this->getUID()['uid'];

        return $this->queue(new RestoreArchive($uid, basename($filename), $restoreFiles, $restoreDatabase, $rewriteUrls)) ? $uid : '';
    }

    /** Where a run reports its progress, empty when it was not given a uid to report under. */
    private function outputFileFor(string $uid): string
    {
        if ($uid === '') {
            return '';
        }
        $info = $this->getInfoFromFile();

        return isset($info[$uid]) ? (string)$info[$uid]['output'] : '';
    }

    /**
     * Restore from an archive on this disk, since ZipArchive cannot be handed anything but a real path.
     *
     * @param string|OutputInterface &$output
     */
    private function restoreFromLocalArchive(string $filePath, string $filename, bool $restoreFiles, bool $restoreDatabase, bool $rewriteUrls, &$output, string $outputFile): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new \Exception("Cannot open archive: $filename");
        }

        try {
            $type = self::archiveType($filename);
            $onlyFiles = $type === 'only_files';
            $onlyDb = $type === 'only_db';
            $this->assertRoomToRestore($zip, $restoreFiles && !$onlyDb, $restoreDatabase && !$onlyFiles);
            $stated = $zip->getFromName(basename(ConfigurationFileProvider::getConfigFileFromEnv()));
            if ($restoreFiles && !$onlyDb && $stated !== false) {
                self::archivedSettings($stated);
            }

            if ($restoreDatabase && !$onlyFiles) {
                $this->writeOutput($output, '=== Reading the database backup ===', true, $outputFile);
                $sqlName = self::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP;
                if ($zip->locateName($sqlName) === false) {
                    throw new \Exception('SQL file not found in archive');
                }
                $open = static function () use ($zip, $sqlName) {
                    $stream = $zip->getStream($sqlName);
                    if ($stream === false) {
                        throw new \Exception('Cannot read the SQL file in the archive');
                    }

                    return $stream;
                };
                $rewrite = null;
                if ($rewriteUrls) {
                    $substitutions = $this->urlSubstitutionsFor($zip);
                    if (!empty($substitutions)) {
                        $this->writeOutput($output, 'Pointing the stored addresses at this wiki', true, $outputFile);
                        $rewrite = static fn (string $statement): string => DumpRewriter::rewriteUrls($statement, $substitutions);
                    }
                }
                $this->writeOutput($output, '=== Restoring the database ===', true, $outputFile);
                $this->restoreDatabase($open, $rewrite);
            }

            if ($restoreFiles && !$onlyDb) {
                $this->writeOutput($output, '=== Restoring the files ===', true, $outputFile);
                $this->restoreFilesFromZip($zip);
                if ($this->restoreConfiguration($zip) > 0) {
                    $this->writeOutput($output, 'Settings taken from the backup, keeping this wiki\'s own address, database and mail server', true, $outputFile);
                }
                if (!$onlyFiles) {
                    $removed = $this->removeFilesAbsentFromArchive($zip, YESWIKI_INSTANCE_DIR);
                    $this->writeOutput($output, "Removed $removed file(s) the backup does not hold", true, $outputFile);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** What a backup holds, 'full', 'only_files' or 'only_db', read from its name. */
    public static function archiveType(string $filename): string
    {
        $parts = ArchiveFilename::parse($filename);
        if ($parts !== []) {
            return $parts['type'];
        }
        if (str_ends_with($filename, self::ARCHIVE_ONLY_FILES_SUFFIX . '.zip')) {
            return 'only_files';
        }

        return str_ends_with($filename, self::ARCHIVE_ONLY_DATABASE_SUFFIX . '.zip') ? 'only_db' : 'full';
    }

    /**
     * Take the archived settings over this wiki's own, except those that tie it to its database, address and mail server.
     *
     * @return int how many settings came from the archive
     *
     * @throws \Exception when this wiki's configuration cannot be read or written
     */
    protected function restoreConfiguration(\ZipArchive $zip): int
    {
        $configFile = ConfigurationFileProvider::getConfigFileFromEnv();
        $stated = $zip->getFromName(basename($configFile));
        if ($stated === false) {
            return 0;
        }
        $archived = self::archivedSettings($stated);
        if ($archived === []) {
            return 0;
        }

        $configuration = $this->configurationService->getConfiguration($configFile);
        $configuration->load();
        $before = \count($configuration);
        if ($before === 0) {
            throw new \Exception('this wiki\'s own configuration could not be read, so it was left alone');
        }
        $local = [];
        foreach ($configuration as $key => $value) {
            if (is_string($key)) {
                $local[$key] = $value;
            }
        }
        foreach (self::mergedSettings($local, $archived) as $key => $value) {
            $configuration[$key] = $value;
        }
        if (!$this->configurationService->write($configuration)) {
            throw new \Exception('this wiki\'s configuration file could not be written');
        }

        return \count(array_diff_key($archived, array_flip(self::keptKeys($archived))));
    }

    /**
     * The settings a configuration file states, parsed as literals and never run, since an archive may come from another wiki.
     *
     * @return array<string, mixed>
     *
     * @throws \Exception when the file is anything but a plain `$yeswikiConfig = [...]`
     */
    public static function archivedSettings(string $stated): array
    {
        try {
            $settings = ConfigurationLiteral::parse($stated);
        } catch (\UnexpectedValueException $refused) {
            throw new \Exception('The archived configuration was not read: ' . $refused->getMessage() . '. Only literal values are accepted, as yeswiki.config.php is written.', 0, $refused);
        }

        return array_filter($settings, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Delete what a full backup does not hold from the folders it does, once its files are out: the wiki is never left without its own code.
     *
     * @return int how many files were removed
     */
    public function removeFilesAbsentFromArchive(\ZipArchive $zip, string $wikiRoot): int
    {
        if (!$this->localFiles->isDirectory($wikiRoot)) {
            throw new \Exception("'$wikiRoot' is not a directory to restore into");
        }
        $keep = [];
        $folders = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = rtrim((string)$zip->getNameIndex($i), '/');
            if ($name === '' || str_contains($name, '..')) {
                continue;
            }
            $keep[$name] = true;
            if (str_contains($name, '/')) {
                $folders[(string)strtok($name, '/')] = true;
            }
        }

        $spared = array_merge($this->generateListRootFolders('black', []), ['cache']);
        $removed = 0;
        foreach (array_keys($folders) as $folder) {
            if (!$this->spares($folder, $spared)) {
                $removed += $this->removeAbsentFiles(rtrim($wikiRoot, '/') . "/$folder", $folder, $keep, $spared);
            }
        }

        return $removed;
    }

    /**
     * @param array<string, true> $keep
     * @param list<string>        $spared
     */
    private function removeAbsentFiles(string $path, string $relativePath, array $keep, array $spared): int
    {
        if (!$this->localFiles->isDirectory($path) || $this->localFiles->isLink($path)) {
            return 0;
        }
        $removed = 0;
        foreach ($this->localFiles->entriesIn($path) as $name) {
            $childPath = "$path/$name";
            $childRelativePath = "$relativePath/$name";
            if (isset($keep[$childRelativePath]) && ($this->localFiles->isLink($childPath) || !$this->localFiles->isDirectory($childPath))) {
                continue;
            }
            if ($this->spares($childRelativePath, $spared) || self::isLocalOnly($childRelativePath) || $this->isDatabaseFile($childRelativePath)) {
                continue;
            }
            if ($this->localFiles->isDirectory($childPath) && !$this->localFiles->isLink($childPath)) {
                $removed += $this->removeAbsentFiles($childPath, $childRelativePath, $keep, $spared);
                if (!isset($keep[$childRelativePath])) {
                    $this->localFiles->removeEmptyDirectory($childPath);
                }
            } elseif ($this->localFiles->remove($childPath)) {
                $removed++;
            }
        }

        return $removed;
    }

    /** Whether a path is this wiki's SQLite database, which a restore replays rather than copies. */
    private function isDatabaseFile(string $relativePath): bool
    {
        if ($this->dbService->getDriver() !== 'sqlite') {
            return false;
        }
        $database = str_replace('\\', '/', $this->configString('db_database') ?: 'private/yeswiki.db');
        $root = str_replace('\\', '/', YESWIKI_INSTANCE_DIR) . '/';
        $database = ltrim(str_starts_with($database, $root) ? substr($database, \strlen($root)) : $database, './');
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), './');

        return in_array($relativePath, [$database, "$database-wal", "$database-shm", "$database-journal"], true);
    }

    /** @param list<string> $spared */
    private function spares(string $relativePath, array $spared): bool
    {
        foreach ($spared as $folder) {
            if ($relativePath === $folder || str_starts_with($relativePath, "$folder/") || basename($relativePath) === $folder) {
                return true;
            }
        }

        return false;
    }

    /**
     * How to point the addresses a backup carries at this wiki.
     *
     * A backup restored where it was taken needs none of this; one restored anywhere else -- a clone, a staging copy, a wiki that moved domain -- carries the source's address in every stored link, and leaves them pointing at the wiki it came from unless they are rewritten here.
     *
     * @return array<string, string> empty when the source is unknown, unchanged, or unsafe to write into the dump
     */
    private function urlSubstitutionsFor(\ZipArchive $zip): array
    {
        return DumpRewriter::substitutions(
            (string)($this->archiveInfo($zip)['base_url'] ?? ''),
            $this->configString('base_url')
        );
    }

    /**
     * What the archive records about the wiki it was taken from.
     *
     * A backup written before this file existed simply has none, and is restored as it always was.
     *
     * @return array<string, mixed>
     */
    private function archiveInfo(\ZipArchive $zip): array
    {
        $raw = $zip->getFromName(self::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::INFO_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP);
        if ($raw === false) {
            return [];
        }
        $info = json_decode($raw, true);

        return is_array($info) ? $info : [];
    }

    /** The address of the wiki a stored backup was taken from, empty when it does not say. */
    public function getArchiveSourceBaseUrl(string $filename): string
    {
        $filePath = $this->getFilePath($filename);
        if (empty($filePath)) {
            return '';
        }

        return (string)$this->storage->withLocalCopy($filePath, function (string $local): string {
            $zip = new \ZipArchive();
            if ($zip->open($local) !== true) {
                return '';
            }

            try {
                return (string)($this->archiveInfo($zip)['base_url'] ?? '');
            } finally {
                $zip->close();
            }
        });
    }

    /**
     * Replay the dump beside the wiki's tables and swap it in (ticket 17: DbService owns the replay).
     *
     * @param callable(): resource            $open    a fresh stream on the dump each time it is called
     * @param (callable(string): string)|null $rewrite applied to each statement before it is replayed
     */
    protected function restoreDatabase(callable $open, ?callable $rewrite = null): void
    {
        $this->dbService->restoreStagedFromStream($open, $rewrite);
    }

    /**
     * Files that say where this wiki is and what it may reach.
     *
     * @return list<string>
     */
    public static function localOnlyFiles(): array
    {
        return [ConfigurationFileProvider::getConfigFileFromEnv(), 'private/.env'];
    }

    /**
     * Configuration keys that say where this wiki is, what it may reach, and how it backs itself up.
     *
     * @return list<string>
     */
    public static function localOnlyKeys(): array
    {
        return [
            'base_url',
            'db_driver',
            'db_host',
            'db_port',
            'db_database',
            'db_user',
            'db_password',
            'db_charset',
            'table_prefix',
            'contact_smtp_host',
            'contact_smtp_user',
            'contact_smtp_pass',
            'api_allowed_keys',
            self::PARAMS_KEY_IN_WAKKA,
            'wiki_status',
            // Another wiki's secret would answer 401 to this one's cron, and its mode can name a worker this host does not run.
            'queue_consume_secret',
            'queue_consumer',
        ];
    }

    /**
     * The keys that stay this wiki's own, with those the archive blanked out when it was made.
     *
     * @param array<string, mixed> $remote what the archive holds
     *
     * @return list<string>
     */
    public static function keptKeys(array $remote): array
    {
        $hidden = $remote[self::PARAMS_KEY_IN_WAKKA][self::KEY_FOR_HIDE_CONFIG_VALUES] ?? null;
        if (!is_array($hidden)) {
            $hidden = self::DEFAULT_PARAMS_TO_ANONYMIZE;
        }

        return array_values(array_unique(array_merge(self::localOnlyKeys(), array_map('strval', array_keys($hidden)))));
    }

    /**
     * This wiki's configuration with the remote one's taken over it, except what must stay local.
     *
     * @param array<string, mixed> $local  what this wiki has now
     * @param array<string, mixed> $remote what the archive holds
     *
     * @return array<string, mixed>
     */
    public static function mergedSettings(array $local, array $remote): array
    {
        $keep = self::keptKeys($remote);

        foreach ($remote as $key => $value) {
            if (!in_array($key, $keep, true)) {
                $local[$key] = $value;
            }
        }

        return $local;
    }

    public static function isLocalOnly(string $name): bool
    {
        $name = ltrim(str_replace('\\', '/', $name), './');

        foreach (self::localOnlyFiles() as $local) {
            if ($name === ltrim(str_replace('\\', '/', $local), './') || $name === basename($local)) {
                return true;
            }
        }

        return false;
    }

    /** Extract the wiki's files from the zip, skipping private/backups, the local-only files and the SQLite database. */
    protected function restoreFilesFromZip(\ZipArchive $zip): void
    {
        $wikiRoot = YESWIKI_INSTANCE_DIR;
        $skipPrefix = self::PRIVATE_FOLDER_NAME_IN_ZIP . '/';

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false) {
                continue;
            }
            if (strpos($name, $skipPrefix) === 0 || self::isLocalOnly($name) || $this->isDatabaseFile($name) || strpos($name, '..') !== false) {
                continue;
            }
            if (str_ends_with($name, '/')) {
                $dir = $wikiRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                if (!$this->localFiles->isDirectory($dir)) {
                    $this->localFiles->makeDirectory($dir);
                }
                continue;
            }
            error_clear_last();
            if (!@$zip->extractTo($wikiRoot, $name)) {
                $reason = error_get_last()['message'] ?? $zip->getStatusString();

                throw new \Exception("Cannot extract $name: $reason");
            }
        }
    }

    /** Bytes a restore adds to the disk: the dump it reads out, and each file beyond the one it replaces. */
    public function bytesToRestore(\ZipArchive $zip, bool $restoreFiles, bool $restoreDatabase): int
    {
        $dumpName = self::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP;
        $configName = basename(ConfigurationFileProvider::getConfigFileFromEnv());
        $bytes = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false || str_ends_with($stat['name'], '/')) {
                continue;
            }
            if ($stat['name'] === $dumpName) {
                $bytes += $restoreDatabase ? $stat['size'] : 0;
                continue;
            }
            if (!$restoreFiles || strpos($stat['name'], self::PRIVATE_FOLDER_NAME_IN_ZIP . '/') === 0 || $stat['name'] === $configName
                || self::isLocalOnly($stat['name']) || $this->isDatabaseFile($stat['name']) || strpos($stat['name'], '..') !== false) {
                continue;
            }
            $existing = YESWIKI_INSTANCE_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stat['name']);
            $bytes += max(0, $stat['size'] - $this->localFiles->size($existing));
        }

        return $bytes;
    }

    /** @throws \Exception when what the restore will write does not fit in the room left */
    protected function assertRoomToRestore(\ZipArchive $zip, bool $restoreFiles, bool $restoreDatabase): void
    {
        $needed = $this->bytesToRestore($zip, $restoreFiles, $restoreDatabase);
        $free = $this->freeSpaceForArchives();
        if ($free !== null && $free < $needed) {
            throw new \Exception('Not enough free space to restore this backup' . self::spaceDetail($needed, $free));
        }
    }

    /**
     * delete archives.
     *
     * @param array<array-key, mixed> $filesnames
     *
     * @return array<string, bool> $results = ['filename' => bool]
     */
    public function deleteArchives(array $filesnames): array
    {
        $privatePath = $this->getPrivateFolder();
        $filesnames = array_filter($filesnames, 'is_string');
        $filesnames = array_map('basename', $filesnames);
        $results = [
            'main' => true,
        ];
        foreach ($filesnames as $filename) {
            $results[$filename] = (substr($filename, -4) == '.zip') && $this->storage->fileExists("$privatePath/$filename");
            if ($results[$filename]) {
                $results[$filename] = $this->forget("$privatePath/$filename");
            }
            if (!$results[$filename]) {
                $results['main'] = false;
            }
        }

        return $results;
    }

    /**
     * Where the backup or restore followed by this uid is, from its progress file and from its message in the queue.
     *
     * - `started`: the uid is known and has a message; an uid without one (left by a detached
     *   `core:archive` from before the queue) is forgotten;
     * - `state`: the message's, as QueueStatistics::messageState() says it, a claim past its
     *   limit counting as `killed` even before a pass has reaped it (the next pass may be a
     *   while: a cron, a visit);
     * - `running`: waiting for a consumer or under way;
     * - `failed`: the message failed or was killed, with the queue's `error` when it failed. The
     *   run then reads as `finished` and `stopped`, as a failure always did in its output, and a
     *   wiki left `archiving` is set back to running by the same rule as the backups page's
     *   (releaseWhatADeadRunLeft()): not while another run this wiki follows is waiting or under
     *   way, since the status is then that run's (a remote wiki's `api/archives`, refused because
     *   an admin's backup is running, must not make the wiki writable under that backup);
     * - `output`: what the run wrote so far.
     *
     * Every other uid whose run has ended is forgotten, as it always was: one run is followed at a
     * time. One still waiting or under way is kept, for the browser that follows it.
     *
     * @return array{started: bool, running: bool, finished: bool, stopped: bool, failed: bool, state: string, error: string, output: string}
     */
    public function getUIDStatus(string $uid): array
    {
        $results = [
            'started' => false,
            'running' => false,
            'finished' => false,
            'stopped' => false,
            'failed' => false,
            'state' => 'unknown',
            'error' => '',
            'output' => '',
        ];
        $info = $this->getInfoFromFile();
        foreach ($info as $infoUid => $infoData) {
            if ($infoUid != $uid && !$this->isStillGoing($infoData)) {
                $this->cleanUID((string)$infoUid);
            }
        }
        $info = $this->getInfoFromFile();
        if (!isset($info[$uid])) {
            return $results;
        }
        $messageId = $info[$uid]['message'] ?? null;
        if (!is_int($messageId)) {
            $this->cleanUID($uid);

            return $results;
        }

        $state = $this->queueStatistics->messageState($messageId);
        list(
            'running' => $running,
            'finished' => $finished,
            'stopped' => $stopped,
            'output' => $output
        ) = $this->getRunningUIDdata($uid, $info[$uid]);
        $failed = $state === 'failed' || $state === 'killed';
        if ($failed) {
            $running = false;
            $finished = true;
            $stopped = true;
            $results['error'] = $state === 'failed' ? $this->failureOf($messageId) : '';
            $this->releaseWhatADeadRunLeft();
        } elseif ($state === 'handled') {
            $running = false;
            $finished = true;
        } elseif ($state === 'pending' || $state === 'running') {
            $running = !$finished;
        }

        $results['started'] = true;
        $results['running'] = $running;
        $results['finished'] = $finished;
        $results['stopped'] = $stopped;
        $results['failed'] = $failed;
        $results['state'] = $state;
        if (!$running) {
            $output = (string)preg_replace("/(^Archive \\\")(.*)(\\\" successfully created !(?:\s*END)?\s*$)/m", '$1---$3', $output);
            $this->cleanUID($uid);
        }
        $results['output'] = $output;

        return $results;
    }

    /**
     * Whether the run of that uid is the one being handled now: the uid is still followed, and the message it records, if any yet, is in progress.
     *
     * A backup or restore retried from the jobs page is a copy with an id of its own, while the
     * uid still records the message that failed (or the uid is gone, once the browser saw the
     * failure). Such a copy would run with nothing following it: not pinned to a request, so on a
     * visitor's budget; its `archiving` lifted by the first visit to the backups page, which sees
     * the run it knows of dead; a restore replayed by "retry all". Its handler refuses it instead.
     *
     * An uid that records no message yet is the first run's own: queue() writes the id once the
     * dispatch has returned, and a blocking consumer may take the message in between.
     */
    public function isUnderWay(string $uid): bool
    {
        $info = $this->getInfoFromFile();
        if (!isset($info[$uid])) {
            return false;
        }
        $messageId = $info[$uid]['message'] ?? null;

        return !is_int($messageId) || $this->queueStatistics->messageState($messageId) === 'running';
    }

    /**
     * Whether the run an entry of info.json follows is waiting or under way: its message is in the queue, not yet handled nor failed.
     *
     * @param mixed $data the uid's entry
     */
    private function isStillGoing(mixed $data): bool
    {
        $messageId = is_array($data) ? ($data['message'] ?? null) : null;
        if (!is_int($messageId)) {
            return false;
        }
        $state = $this->queueStatistics->messageState($messageId);

        return $state === 'pending' || $state === 'running';
    }

    /**
     * Why that message failed, as the queue's history recorded it.
     *
     * Its row in `failed` has an id of its own, so the history is where the message's id leads;
     * the browser asks within seconds of the failure, so the latest failures are enough.
     */
    private function failureOf(int $messageId): string
    {
        foreach ($this->queueStatistics->history(50, QueueRecorder::FAILED) as $attempt) {
            if ($attempt['messageId'] === $messageId) {
                return (string)$attempt['error'];
            }
        }

        return '';
    }

    /** Sets the wiki back to running if a backup left it `archiving`, and leaves any other status (an admin's hibernation, an update) alone. */
    public function releaseArchivingStatus(): void
    {
        if ($this->getWikiStatus() === 'archiving') {
            $this->unsetWikiStatus();
        }
    }

    /**
     * Sets the wiki back to running when it is `archiving` and the runs this wiki follows have all ended, one of them a killed backup, and forgets the killed ones.
     *
     * Only a killed backup can have left the status: one that failed lifted it as it failed
     * (archive(), CreateArchiveHandler), and a restore never sets it. So a failed run nobody polled, still in info.json,
     * lifts nothing. A run that is waiting or under way keeps the status, whoever asks; so does a
     * run nothing here follows (a `core:archive` typed in a terminal), since then no backup here died.
     */
    private function releaseWhatADeadRunLeft(): void
    {
        if ($this->getWikiStatus() !== 'archiving') {
            return;
        }
        $killed = [];
        foreach ($this->getInfoFromFile() as $uid => $data) {
            $messageId = is_array($data) ? ($data['message'] ?? null) : null;
            if (!is_int($messageId)) {
                continue;
            }
            $state = $this->queueStatistics->messageState($messageId);
            if ($state === 'pending' || $state === 'running') {
                return;
            }
            if ($state === 'killed' && empty($data['restore'])) {
                $killed[] = (string)$uid;
            }
        }
        if ($killed !== []) {
            // Forgotten too: kept, they would lift the status of the next run nothing here follows (a `core:archive` by hand).
            foreach ($killed as $uid) {
                $this->cleanUID($uid);
            }
            $this->unsetWikiStatus();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [QueueConsumer::MESSAGE_KILLED => 'releaseWhatAKilledRunLeft'];
    }

    /**
     * The queue found a message a dead consumer left (R18): when it is a backup or a restore this wiki follows, the read-only status that run left is lifted, and its uid forgotten.
     *
     * The same rule as releaseWhatADeadRunLeft(), which the backups page applies, without waiting
     * for anyone to open that page: nobody may, for a backup before an update, one another wiki
     * fetched, or one whose request died before it ran it. The status stays when another run this
     * wiki follows is waiting or under way (it is that run's), and when the killed message is no
     * run of this wiki's (a `core:archive` typed by hand holds it). The browser that followed the
     * dead run, if any, then finds its uid gone rather than failed.
     */
    public function releaseWhatAKilledRunLeft(Event $event): void
    {
        $messageId = $event->getData()['messageId'] ?? null;
        $followedBy = null;
        foreach ($this->getInfoFromFile() as $uid => $data) {
            if (is_array($data) && ($data['message'] ?? null) === $messageId) {
                $followedBy = (string)$uid;
            }
        }
        if ($followedBy === null) {
            return;
        }

        $this->releaseWhatADeadRunLeft();
        $this->cleanUID($followedBy);
    }

    /**
     * put data in file to stop archive.
     */
    public function stopArchive(string $uid): bool
    {
        if (empty($uid)) {
            return false;
        }
        $info = $this->getInfoFromFile();
        if (
            !isset($info[$uid])
            || empty($info[$uid]['input'])
            || !$this->storage->fileExists((string)$info[$uid]['input'])
        ) {
            return false;
        }
        $this->storage->write((string)$info[$uid]['input'], 'STOP');

        return true;
    }

    /**
     * check if need to stop archive.
     */
    protected function checkIfNeedStop(string $inputFile = ''): bool
    {
        if (empty($inputFile) || !$this->storage->fileExists($inputFile)) {
            return false;
        }
        $content = $this->storage->read($inputFile);

        if (empty($content)) {
            return false;
        }

        return preg_match('/^STOP.*/', $content) === 1;
    }

    /**
     * Refuse to archive something that is not a wiki, asking each root for what it owns.
     *
     * @throws \Exception
     */
    protected function assertArchivableFrom(string $instanceDir, string $programDir): void
    {
        if (!$this->localFiles->exists($instanceDir . '/index.php')) {
            throw new \Exception("no index.php in \"$instanceDir\": that is not a wiki instance");
        }
        if (!$this->storage->exists(ConfigurationFileProvider::getConfigFileFromEnv())) {
            throw new \Exception('the wiki has no configuration file: nothing to archive');
        }
        foreach (['composer.json', 'composer.lock'] as $manifest) {
            if (!$this->localFiles->exists($programDir . '/' . $manifest)) {
                throw new \Exception("no $manifest in \"$programDir\": that is not a yeswiki program tree");
            }
        }
    }

    /**
     * create the zip file.
     *
     * @param array<array-key, mixed>      $foldersToInclude
     * @param list<string>                 $blacklistedRootFolders
     * @param string|OutputInterface       &$output
     * @param string                       $sqlPath                the dump to add, empty for none
     * @param array<string, mixed>|null    $hideConfigValuesParams
     * @param array<array-key, mixed>|null $onlyFolders
     *
     * @return bool : true on success, false on failure
     */
    protected function createZip(
        string $zipPath,
        array $foldersToInclude,
        array $blacklistedRootFolders,
        &$output,
        string $sqlPath,
        bool $onlyDb = false,
        ?array $hideConfigValuesParams = null,
        string $inputFile = '',
        string $outputFile = '',
        ?array $onlyFolders = null
    ) {
        $this->assertArchivableFrom(YESWIKI_INSTANCE_DIR, YESWIKI_PROGRAM_DIR);
        $pathToArchive = YESWIKI_INSTANCE_DIR;
        $dirs = [$pathToArchive];
        $dirnamePathLen = strlen($pathToArchive);

        $whitelistedRootFolders = $this->generateListRootFolders('white', $foldersToInclude, $onlyFolders);

        $zip = new \ZipArchive();

        $vCanceled = false;

        $resource = $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($resource !== true) {
            return false;
        }

        if (method_exists($zip, 'registerCancelCallback')) {
            $zip->registerCancelCallback(function () use ($inputFile, &$vCanceled) {
                $vNeedStop = $this->checkIfNeedStop($inputFile);

                if ($vNeedStop) {
                    $vCanceled = true;

                    return -1;
                }

                return 0;
            });
        }

        if (method_exists($zip, 'registerProgressCallback')) {
            $zip->registerProgressCallback(0.1, function ($r) use (&$output, $outputFile) {
                $this->writeOutput($output, 'Zip file creation : ' . strval(round($r * 100, 0)) . ' %', true, $outputFile);
            });
        }

        if (!$vCanceled && !$onlyDb) {
            $zip->addEmptyDir('cache');

            while (count($dirs)) {
                $dir = current($dirs);
                $dir = (string)preg_replace("/(?:\/|\\\\|([^\/\\\\]))$/", '$1', $dir);
                $baseDirName = (string)preg_replace('/\\\\/', '/', substr($dir, $dirnamePathLen));
                $baseDirName = (string)preg_replace("/^\//", '', $baseDirName);
                if (empty($baseDirName) || $this->shouldIncludeFolder($baseDirName, $whitelistedRootFolders, $blacklistedRootFolders)) {
                    if (!empty($baseDirName)) {
                        $this->writeOutput($output, "Adding folder \"$baseDirName\"", true, $outputFile);
                        $zip->addEmptyDir($baseDirName);
                    }

                    foreach ($this->localFiles->entriesIn($dir) as $file) {
                        $localName = $dir . DIRECTORY_SEPARATOR . $file;
                        $relativeName = (empty($baseDirName) ? '' : "$baseDirName/") . $file;
                        if (empty($baseDirName) && $file == ConfigurationFileProvider::getConfigFileFromEnv()) {
                            $zip->addFromString($relativeName, $this->getWakkaConfigSanitized($whitelistedRootFolders, $blacklistedRootFolders, $hideConfigValuesParams));
                        } elseif ($this->isDatabaseFile($relativeName)) {
                            continue;
                        } elseif ($this->localFiles->isFile($localName)) {
                            $zip->addFile($localName, $relativeName);
                        } elseif ($this->localFiles->isDirectory($localName)) {
                            if ($this->shouldIncludeFolder($relativeName, $whitelistedRootFolders, $blacklistedRootFolders)) {
                                $dirs[] = $dir . DIRECTORY_SEPARATOR . $file;
                            }
                            if ($this->checkIfNeedStop($inputFile)) {
                                $this->writeOutput($output, '== The archive processus need to be stopped ==', true, $outputFile);
                                $vCanceled = true;
                                break;
                            }
                        }
                    }
                }

                if ($vCanceled) {
                    break;
                }

                array_shift($dirs);
            }
        }

        if (!$vCanceled && $sqlPath !== '') {
            $this->writeOutput($output, 'Adding SQL file', true, $outputFile);
            $zip->addEmptyDir(self::PRIVATE_FOLDER_NAME_IN_ZIP);
            $zip->addFile(
                $sqlPath,
                self::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::SQL_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP
            );
            $this->writeOutput($output, 'Adding .htaccess file in folder ' . self::PRIVATE_FOLDER_NAME_IN_ZIP, true, $outputFile);

            $zip->addFromString(
                self::PRIVATE_FOLDER_NAME_IN_ZIP . '/.htaccess',
                "DENY FROM ALL\n"
            );

            $zip->addFromString(
                self::PRIVATE_FOLDER_NAME_IN_ZIP . '/README.md',
                self::PRIVATE_FOLDER_README_DEFAULT_CONTENT
            );

            $this->writeOutput($output, 'Adding ' . self::INFO_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP, true, $outputFile);
            $zip->addFromString(
                self::PRIVATE_FOLDER_NAME_IN_ZIP . '/' . self::INFO_FILENAME_IN_PRIVATE_FOLDER_IN_ZIP,
                (string)json_encode([
                    'base_url' => $this->configString('base_url'),
                    'table_prefix' => $this->configString('table_prefix'),
                    'archived_at' => date('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );
        }

        $vClosed = false;
        $vError = false;

        if (!$vCanceled) {
            $this->writeOutput($output, 'Generating zip file', true, $outputFile);

            $vResult = $zip->close();

            $vClosed = true;

            if ($vResult) {
                $this->writeOutput($output, 'Archive was created successfully', true, $outputFile);

                return true;
            } elseif (!$vCanceled) {
                $this->writeOutput($output, 'There was a problem closing archive', true, $outputFile);
            }
        }

        if ($vCanceled) {
            $this->writeOutput($output, 'Archive creation canceled', true, $outputFile);
        }

        if (!$vClosed) {
            $zip->unchangeAll();

            if ($zip->close()) {
                $this->writeOutput($output, 'Archive was closed successfully', true, $outputFile);
            } else {
                $this->writeOutput($output, 'There was a problem closing archive', true, $outputFile);
            }
        }

        $this->localFiles->remove($zipPath);

        return false;
    }

    /**
     * test if folder should be included.
     *
     * @param list<string> $whitelistedRootFolders
     * @param list<string> $blacklistedRootFolders
     */
    protected function shouldIncludeFolder(
        string $relativeFolderName,
        array $whitelistedRootFolders,
        array $blacklistedRootFolders
    ): bool {
        if (
            in_array($relativeFolderName, $blacklistedRootFolders)
            || in_array(basename($relativeFolderName), $blacklistedRootFolders)
        ) {
            return false;
        }

        return count(array_filter($whitelistedRootFolders, function ($folder) use ($relativeFolderName) {
            return $relativeFolderName === $folder || str_starts_with($relativeFolderName, $folder . '/');
        })) > 0;
    }

    /**
     * @param array<array-key, mixed> $list
     *
     * @return list<string> the relative paths of $list, without duplicates
     */
    private function sanitizeFileList(array $list): array
    {
        $outputList = [];
        foreach ($list as $filePath) {
            if (is_string($filePath)) {
                $filePath = trim($filePath);
                if (!empty($filePath) && !preg_match('/^(?:\\/|\\\\)|[A-Za-z]:\\\\|(?:\\/|\\\\|^)\\.\\.(?:\\/|\\\\|$)/', $filePath)) {
                    $formattedFilePath = (string)preg_replace("/(\/|\\\\)$/", '', $filePath);
                    if (!in_array($formattedFilePath, $outputList)) {
                        $outputList[] = $formattedFilePath;
                    }
                }
            }
        }

        return $outputList;
    }

    /** Where a wiki's archives are, which is not a setting: one place, Protected, and in the bucket when there is one. */
    public function getPrivateFolder(): string
    {
        return self::PRIVATE_FOLDER_NAME_IN_ZIP;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArchiveParams(): array
    {
        if ($this->params->has(self::PARAMS_KEY_IN_WAKKA)) {
            $archiveParams = $this->params->get(self::PARAMS_KEY_IN_WAKKA);
        }

        return (empty($archiveParams) || !is_array($archiveParams)) ? [] : $archiveParams;
    }

    private function canWriteFolder(string $path): bool
    {
        return $this->storage->isWritable($path);
    }

    /** Add a line to a progress log, which is the one thing Storage has no verb for. */
    private function append(string $path, string $text): bool
    {
        try {
            $kept = $this->storage->fileExists($path) ? $this->storage->read($path) : '';
            $this->storage->write($path, $kept . $text);
        } catch (\Throwable $th) {
            return false;
        }

        return true;
    }

    /** Remove a file if it is there, and say whether it is gone. */
    private function forget(string $path): bool
    {
        try {
            if ($this->storage->fileExists($path)) {
                $this->storage->delete($path);
            }
        } catch (\Throwable $th) {
            return false;
        }

        return true;
    }

    /** Whether the backups are out of a reader's reach. */
    private function localPrivateFolderNotAvailableOnInternet(string $localPath, string $testFileName): bool
    {
        if ($this->storage->isRemote($localPath)) {
            return true;
        }
        $isAbsolutePath = (
            in_array(substr($localPath, 0, 1), ['/', DIRECTORY_SEPARATOR])
            || (
                DIRECTORY_SEPARATOR == '\\'

                    && preg_match('/^[A-Za-z]:.*$/', $localPath)
            )
        );
        $basePath = YESWIKI_INSTANCE_DIR;
        $realLocalPath = $isAbsolutePath
            ? $this->localFiles->realPath($localPath)
            : $this->localFiles->realPath($basePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $localPath));

        if ($realLocalPath === false) {
            return true;
        }
        if (!str_starts_with($realLocalPath, $basePath)) {
            return true;
        }
        if (!$this->storage->fileExists("$localPath/$testFileName")) {
            throw new \Exception("\"$localPath/$testFileName\" must exist for tests !");
        }
        $url = (string)preg_replace("/\??$/", '', $this->configString('base_url'));
        $url .= str_replace(DIRECTORY_SEPARATOR, '/', "$localPath/$testFileName");
        $ct = stream_context_set_default([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'method' => 'HEAD',
            ],
        ]);
        $headers = @get_headers($url, true, $ct);

        $status = $headers === false ? null : ($headers[0] ?? null);

        return !is_string($status) || !strstr($status, '200 OK');
    }

    /** A configuration value as a string: the parameter bag types every value as a union. */
    private function configString(string $key): string
    {
        $value = $this->params->get($key);

        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * write text to the output.
     *
     * @param string|OutputInterface &$output
     */
    private function writeOutput(&$output, string $text, bool $newline = true, string $outputFile = ''): void
    {
        if (!empty($outputFile) && $this->storage->fileExists($outputFile)) {
            if (!$this->append($outputFile, $text . ($newline ? "\n" : ''))) {
                throw new \Exception('Cannot write to output file. Please check file system access rights');
            }
        }
        if ($output instanceof OutputInterface) {
            $output->write($text, $newline);
        } elseif (is_string($output)) {
            $output .= $text . ($newline ? "\n" : '');
        } else {
            throw new \Exception('"$output" should be string or OutputInterface !');
        }
    }

    /**
     * sanitize yeswiki.config.php before saving it.
     *
     * @param array<array-key, mixed>   $foldersToInclude
     * @param array<array-key, mixed>   $foldersToExclude
     * @param array<string, mixed>|null $hideConfigValuesParams
     */
    private function getWakkaConfigSanitized(array $foldersToInclude, array $foldersToExclude, ?array $hideConfigValuesParams = null): string
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        if (
            !isset($config[self::PARAMS_KEY_IN_WAKKA])
            || !is_array($config[self::PARAMS_KEY_IN_WAKKA])
        ) {
            $data = [];
        } else {
            $data = $config[self::PARAMS_KEY_IN_WAKKA];
        }
        if (!empty($foldersToInclude)) {
            $data[self::KEY_FOR_FOLDERS_TO_INCLUDE] = $foldersToInclude;
        }
        if (!empty($foldersToExclude)) {
            $data[self::KEY_FOR_FOLDERS_TO_EXCLUDE] = $foldersToExclude;
        }
        if (!is_null($hideConfigValuesParams)) {
            $data[self::KEY_FOR_HIDE_CONFIG_VALUES] = $hideConfigValuesParams;
        } elseif (!isset($data[self::KEY_FOR_HIDE_CONFIG_VALUES]) || !is_array($data[self::KEY_FOR_HIDE_CONFIG_VALUES])) {
            $data[self::KEY_FOR_HIDE_CONFIG_VALUES] = self::DEFAULT_PARAMS_TO_ANONYMIZE;
        }
        $config[self::PARAMS_KEY_IN_WAKKA] = $data;

        $config = $this->setDefaultValuesRecursive($config[self::PARAMS_KEY_IN_WAKKA][self::KEY_FOR_HIDE_CONFIG_VALUES], $config);
        unset($config['wiki_status']);
        // Whatever the hide list says: whoever holds the backup could consume this wiki's queue with it.
        unset($config['queue_consume_secret']);

        return $this->configurationService->getContentToWrite($config);
    }

    /**
     * @param array<array-key, mixed> $defaultValues
     *
     * @return mixed $values with every key $defaultValues names reset to its default
     */
    private function setDefaultValuesRecursive(array $defaultValues, mixed $values)
    {
        foreach ($defaultValues as $key => $value) {
            if (is_scalar($value)) {
                if (isset($values[$key])) {
                    $values[$key] = $value;
                }
            } elseif (is_array($value)) {
                if (isset($values[$key])) {
                    $values[$key] = $this->setDefaultValuesRecursive($value, $values[$key]);
                }
            }
        }

        return $values;
    }

    protected function setWikiStatus(): void
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        $config['wiki_status'] = 'archiving';
        if (!$this->configurationService->write($config)) {
            throw new \Exception('Cannot write the configuration file, the disk or the quota may be full');
        }
    }

    protected function unsetWikiStatus(): void
    {
        $config = $this->configurationService->getConfiguration(ConfigurationFileProvider::getConfigFileFromEnv());
        $config->load();
        unset($config['wiki_status']);
        $this->configurationService->write($config);
    }

    /**
     * test db export connection.
     */
    protected function testDb(): bool
    {
        try {
            $results = $this->consoleService->startConsoleSync('core:exportdb', [
                '--test',
            ]);
            if (empty($results)) {
                return false;
            }
            $result = $results[array_key_first($results)];

            return preg_match('/^OK\s*$/im', $result['stdout']) === 1;
        } catch (\Throwable $th) {
        }

        return false;
    }

    /**
     * Write the database dump to a local file, through mysqldump when the server has it, through SqlDumper otherwise.
     *
     * @throws \Exception when neither produced a dump
     */
    protected function dumpDatabaseInto(string $sqlPath): void
    {
        $errorMessage = '';
        if ($this->testDb()) {
            $results = $this->consoleService->startConsoleSync('core:exportdb', [
                "--filepath=$sqlPath",
            ]);

            if ($this->dumpHoldsEveryTable($sqlPath)) {
                return;
            }

            if (!empty($results)) {
                $result = $results[array_key_first($results)];
                if (!empty($result['stderr'])) {
                    $errorMessage .= "Error using mysqldump :\n{$result['stderr']}\n";
                }
            }
        }

        $handle = $this->localFiles->openForWriting($sqlPath);
        if ($handle === null) {
            throw new \Exception($errorMessage . "Cannot write the database dump to $sqlPath");
        }
        try {
            $this->dbService->dumper()->dumpTo($handle);
        } catch (\Throwable $th) {
            throw new \Exception($errorMessage . $th->getMessage(), 0, $th);
        } finally {
            fclose($handle);
        }
    }

    /** Whether a dump written by another tool creates every table of this wiki, since a restore drops the ones it lacks. */
    private function dumpHoldsEveryTable(string $sqlPath): bool
    {
        $handle = $this->localFiles->isFile($sqlPath) ? $this->localFiles->openForReading($sqlPath) : null;
        if ($handle === null) {
            return false;
        }
        $created = [];
        try {
            foreach (SqlStatementSplitter::fromStream($handle) as $statement) {
                foreach (DumpRewriter::tables($statement) as $table) {
                    $created[$table] = true;
                }
            }
        } finally {
            fclose($handle);
        }
        $own = DumpRewriter::contentTables($this->dbService->schema()->getTables(), trim($this->dbService->prefixTable('')));

        return $own !== [] && array_diff($own, array_keys($created)) === [];
    }

    /**
     * @param list<string> $blacklistedRootFolders
     *
     * @throws \Exception when the backups folder cannot hold a new archive
     */
    protected function assertEnoughtSpace(array $blacklistedRootFolders = []): void
    {
        $freeSpace = $this->freeSpaceForArchives();
        $needed = $this->estimateArchiveSize($blacklistedRootFolders);
        if ($freeSpace !== null && $freeSpace < $needed) {
            throw new \Exception('Not enough free space for a new archive!' . self::spaceDetail($needed, $freeSpace));
        }
    }

    /**
     * Bytes a new archive is expected to need: the uploaded files, the customisations, the database, plus a fixed allowance for the code.
     *
     * @param list<string> $blacklistedRootFolders
     */
    public function estimateArchiveSize(array $blacklistedRootFolders = []): int
    {
        if (empty($blacklistedRootFolders)) {
            $blacklistedRootFolders = self::FOLDERS_TO_EXCLUDE;
        }
        $estimate = self::CODE_SIZE_ALLOWANCE + $this->databaseSize();
        foreach (['files', 'custom'] as $folder) {
            if (!in_array($folder, $blacklistedRootFolders, true)) {
                $estimate += $this->folderSize($folder);
            }
        }

        return $estimate;
    }

    /** How much is needed against how much is free, as a parenthesis for an error message. */
    public static function spaceDetail(int $needed, mixed $free): string
    {
        if ($needed <= 0 || !is_numeric($free)) {
            return '';
        }

        return ' (' . self::humanSize($needed) . ' needed, ' . self::humanSize((int)$free) . ' free)';
    }

    private static function humanSize(int $bytes): string
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < \count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        return ($unit === 0 ? (string)$bytes : number_format($value, 1)) . ' ' . $units[$unit];
    }

    /** Free bytes on the disk an archive is built on, null when the host will not say. */
    public function freeSpaceForArchives(): ?int
    {
        $folder = $this->getPrivateFolder();
        $local = $this->storage->isRemote($folder) ? sys_get_temp_dir() : $this->storage->absolutePath($folder);
        if (!$this->localFiles->isDirectory($local)) {
            $local = YESWIKI_INSTANCE_DIR;
        }
        $free = $this->localFiles->freeSpace($local);

        return $free === null ? null : (int)$free;
    }

    /** Bytes of data in this wiki's own tables, as the server counts them; 0 when it will not say. */
    protected function databaseSize(): int
    {
        try {
            $own = DumpRewriter::ownTables($this->dbService->schema()->getTables(), trim($this->dbService->prefixTable('')));
            if ($own === []) {
                return 0;
            }
            $names = implode(', ', array_fill(0, \count($own), '?'));

            return (int)match ($this->dbService->getDriver()) {
                'sqlite' => $this->dbService->scalar('SELECT page_count * page_size FROM pragma_page_count(), pragma_page_size()', 0),
                'pgsql' => $this->dbService->scalar("SELECT COALESCE(SUM(pg_total_relation_size(c.oid)), 0) FROM pg_class c WHERE c.relkind = 'r' AND pg_table_is_visible(c.oid) AND c.relname IN ($names)", 0, $own),
                default => $this->dbService->scalar("SELECT COALESCE(SUM(data_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name IN ($names)", 0, $own),
            };
        } catch (\Throwable $th) {
            return 0;
        }
    }

    /**
     * recursive method.
     *
     * @return int $bytes
     */
    private function folderSize(string $folderPath): int
    {
        $bytes = 0;
        foreach ($this->localFiles->entriesIn($folderPath) as $name) {
            if ($this->localFiles->isFile("$folderPath/$name")) {
                $bytes += $this->localFiles->size("$folderPath/$name");
            } elseif ($this->localFiles->isDirectory("$folderPath/$name")) {
                $bytes += $this->folderSize("$folderPath/$name");
            }
        }

        return $bytes;
    }

    /**
     * remove oldest files to keep only 10 files.
     */
    private function cleanOldestFiles(): void
    {
        $archivesToDelete = $this->archivesToDelete();
        if (!empty($archivesToDelete)) {
            $this->deleteArchives($archivesToDelete);
        }
    }

    private function getMaxNbFiles(): int
    {
        $archiveParams = $this->getArchiveParams();

        return (empty($archiveParams['max_nb_files'])
            || !is_scalar($archiveParams['max_nb_files'])
            || intval($archiveParams['max_nb_files']) < 3)
            ? 10
            : intval($archiveParams['max_nb_files']);
    }

    /**
     * The archives the rotation removes: only this wiki's own, since one fetched from another wiki was asked for by hand.
     *
     * @return list<string> $files the filenames to delete
     */
    public function archivesToDelete(bool $beforeArchive = false): array
    {
        $ownSource = ArchiveFilename::slug($this->configString('base_url'));
        $archives = array_values(array_filter(
            $this->getArchives(),
            static fn (array $archive): bool => empty($archive['source']) || $archive['source'] === $ownSource
        ));
        $maxNBFiles = $this->getMaxNbFiles();
        $nbFilesToRemove = count($archives) - $maxNBFiles + ($beforeArchive ? 1 : 0);
        if ($nbFilesToRemove > 0) {
            $indexesToRemove = range($maxNBFiles, count($archives) - 1);
            $archivesIndexesMoreThan2days = $this->getIndexesMoreThanxdays($archives, 2);
            $archivesIndexesMoreThan1day = $this->getIndexesMoreThanxdays($archives, 1);

            $notDeletedArchivesMoreThan2Days = array_diff($archivesIndexesMoreThan2days, $indexesToRemove);
            if (!empty($archivesIndexesMoreThan2days) && empty($notDeletedArchivesMoreThan2Days)) {
                $indexesToRemove = array_diff($indexesToRemove, [min($archivesIndexesMoreThan2days)]);
                if (empty($indexesToRemove)) {
                    $indexesToRemove = [min($archivesIndexesMoreThan2days) - 1];
                } else {
                    array_unshift($indexesToRemove, min($indexesToRemove) - 1);
                }
            }
            $archivesIndexesBetween1and2days = array_diff($archivesIndexesMoreThan1day, $archivesIndexesMoreThan2days);
            $notDeletedArchivesBetween1and2days = array_diff($archivesIndexesBetween1and2days, $indexesToRemove);
            if (!empty($archivesIndexesBetween1and2days) && empty($notDeletedArchivesBetween1and2days)) {
                $indexesToRemove = array_diff($indexesToRemove, [min($archivesIndexesBetween1and2days)]);
                if (empty($indexesToRemove)) {
                    $indexesToRemove = [min($archivesIndexesBetween1and2days) - 1];
                } else {
                    array_unshift($indexesToRemove, min($indexesToRemove) - 1);
                }
            }
            $archivesToDelete = [];
            foreach ($indexesToRemove as $index) {
                $archivesToDelete[] = $archives[$index]['filename'];
            }

            return $archivesToDelete;
        }

        return [];
    }

    /**
     * @param list<array<string, mixed>> $archives as getArchives() answers them
     *
     * @return list<int> the positions in $archives of the archives older than $days days
     */
    private function getIndexesMoreThanxdays(array $archives, int $days): array
    {
        if ($days < 1) {
            return [];
        }
        $indexes = [];
        $nowMinusXDays = (new \DateTime())->sub(new \DateInterval("P{$days}D"));
        foreach ($archives as $key => $archive) {
            $fileDateTime = (new \DateTime())
                ->setDate($archive['year'], $archive['month'], $archive['day'])
                ->setTime($archive['hours'], $archive['minutes'], $archive['seconds'], 0);
            if (
                $fileDateTime->diff($nowMinusXDays)->invert == 0
            ) {
                $indexes[] = $key;
            }
        }

        return $indexes;
    }

    /**
     * What is running, where each run says so, and the message in the queue that runs it (`message`).
     *
     * @return array<string, mixed> the decoded info.json, keyed by uid
     */
    private function getInfoFromFile(): array
    {
        $file = self::PROGRESS_FOLDER . '/info.json';
        if (!$this->storage->fileExists($file)) {
            return [];
        }
        $content = json_decode($this->storage->read($file), true);

        return (empty($content) || !is_array($content)) ? [] : $content;
    }

    /** @param array<string, mixed> $content */
    private function setInfoToFile(array $content): void
    {
        $this->storage->write(self::PROGRESS_FOLDER . '/info.json', (string)json_encode($content));
    }

    /**
     * A new uid for a run to report under, with its input (STOP) and output (progress) files created.
     *
     * @return array{uid: string, input: string|false, output: string|false}
     */
    private function getUID(): array
    {
        $info = $this->getInfoFromFile();
        $usedIDS = array_keys($info);
        do {
            $uid = uniqid();
        } while (in_array($uid, $usedIDS));

        $input = self::PROGRESS_FOLDER . "/input-$uid.log";
        $output = self::PROGRESS_FOLDER . "/output-$uid.log";
        $this->storage->write($input, '');
        $this->storage->write($output, '');

        $info[$uid] = [
            'input' => $input,
            'output' => $output,
        ];
        $this->setInfoToFile($info);

        return compact(['uid', 'input', 'output']);
    }

    /**
     * clean uid info in info.json.
     */
    private function cleanUID(string $uid): void
    {
        $info = $this->getInfoFromFile();
        if (isset($info[$uid])) {
            foreach (['input', 'output'] as $key) {
                $file = (string)($info[$uid][$key] ?? '');
                if ($file !== '' && $this->storage->fileExists($file)) {
                    $this->storage->delete($file);
                }
            }
            unset($info[$uid]);
            $this->setInfoToFile($info);
        }
    }

    /** check id current uid is running.
     * @param array<string, mixed> $info this uid's entry in info.json
     *
     * @return array<string, mixed> ['running' => bool, 'finished' => bool, 'stopped' => bool,'output' => string]
     */
    private function getRunningUIDdata(string $uid, array $info): array
    {
        if (empty($info['output']) || !$this->storage->fileExists((string)$info['output'])) {
            return ['running' => false, 'finished' => false, 'stopped' => false, 'output' => ''];
        }
        $output = $this->storage->read((string)$info['output']);

        $running = !empty(trim($output));
        $finished = !$running
            ? false
            : (preg_match("/(END|STOP)\s*$/", $output) ? true : false);
        $stopped = preg_match("/(STOP)\s*$/", $output) ? true : false;
        if ($finished) {
            $running = false;
        }

        return compact(['running', 'finished', 'stopped', 'output']);
    }

    /**
     * The folders to archive or leave out: the defaults widened by the params and yeswiki.config.php, or $onlyFolders alone when it names any.
     *
     * @param string                       $type        "white"|"black"
     * @param array<array-key, mixed>      $fromParams
     * @param array<array-key, mixed>|null $onlyFolders
     *
     * @return list<string>
     */
    protected function generateListRootFolders(string $type, array $fromParams, ?array $onlyFolders = null): array
    {
        if ($type === 'white' && !empty($onlyFolders)) {
            $only = $this->sanitizeFileList($onlyFolders);
            if ($only !== []) {
                return $only;
            }
        }

        $list = ($type == 'white') ? self::FOLDERS_TO_INCLUDE : self::FOLDERS_TO_EXCLUDE;
        foreach ($this->sanitizeFileList($fromParams) as $folderName) {
            if (!in_array($folderName, $list)) {
                $list[] = $folderName;
            }
        }
        $archiveParams = $this->getArchiveParams();
        $key = ($type == 'white') ? self::KEY_FOR_FOLDERS_TO_INCLUDE : self::KEY_FOR_FOLDERS_TO_EXCLUDE;
        if (
            !empty($archiveParams[$key])
            && is_array($archiveParams[$key])
        ) {
            foreach ($this->sanitizeFileList($archiveParams[$key]) as $path) {
                if (!in_array($path, $list)) {
                    $list[] = $path;
                }
            }
        }

        return $list;
    }
}
