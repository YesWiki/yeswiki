<?php

namespace YesWiki\Admin\Api;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\RemoteBackupService;
use YesWiki\Core\YesWikiController;
use YesWiki\Identity\Service\RequestGuard;
use YesWiki\Kernel\Service\UrlFormatter;

/**
 * The backups screen's parts as HTML for htmx: making a backup, fetching one from another wiki, restoring one, and the list.
 * A part that follows a job asks for itself again every second until the job ends; the list reloads on `ywBackupsChanged`.
 */
class BackupsScreenController extends YesWikiController
{
    private const ADMIN_ACL = ['@admins'];
    private const CHANGED = 'ywBackupsChanged';
    private const TYPES = ['full' => [true, true], 'only_files' => [true, false], 'only_db' => [false, true]];
    private const REMOTE_STEPS = [
        'checking' => 'ADMIN_BACKUPS_REMOTE_STEP_CHECKING',
        'starting' => 'ADMIN_BACKUPS_REMOTE_STEP_STARTING',
        'archiving' => 'ADMIN_BACKUPS_REMOTE_STEP_ARCHIVING',
        'identifying' => 'ADMIN_BACKUPS_REMOTE_STEP_IDENTIFYING',
        'downloading' => 'ADMIN_BACKUPS_REMOTE_STEP_DOWNLOADING',
        'cleaning' => 'ADMIN_BACKUPS_REMOTE_STEP_CLEANING',
    ];

    #[Route('/api/admin/backups/list', methods: ['GET'], options: ['acl' => self::ADMIN_ACL])]
    public function list(): Response
    {
        $this->denyAccessUnlessAdmin();

        return $this->html($this->listHtml());
    }

    #[Route('/api/admin/backups/delete', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function delete(): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }
        $names = array_values(array_filter(array_map('strval', (array)($this->getRequest()->request->all()['filenames'] ?? []))));
        if ($names === []) {
            return $this->html($this->listHtml(_t('ADMIN_BACKUPS_NO_ARCHIVE_TO_DELETE'), 'info'));
        }
        $results = $this->getService(ArchiveService::class)->deleteArchives($names);
        $listed = htmlspecialchars(implode(', ', $names));

        return $this->html($results['main']
            ? $this->listHtml(_t('ADMIN_BACKUPS_DELETE_ARCHIVE_SUCCESS', ['filename' => $listed]), 'success')
            : $this->listHtml(_t('ADMIN_BACKUPS_DELETE_ARCHIVE_POSSIBLE_ERROR', ['filename' => $listed]), 'warning'));
    }

    #[Route('/api/admin/backups/start', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function start(): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }
        $post = $this->getRequest()->request;
        $asked = (string)$post->get('type', '');
        $type = isset(self::TYPES[$asked]) ? $asked : 'full';
        $package = (string)$post->get('package', '');
        $archives = $this->getService(ArchiveService::class);

        $status = $archives->getArchivingStatus();
        if (!$status['canArchive']) {
            [$key, $level] = self::refusalOf($status);

            return $this->archive(['type' => $type, 'package' => $package, 'level' => $level, 'canForceUpdate' => $package !== '',
                'message' => _t($key, ['helpBaseUrl' => $this->getService(UrlFormatter::class)->href('', 'doc')])]);
        }
        if ($package === '' && !$post->getBoolean('confirmDelete')) {
            $files = $archives->archivesToDelete(true);
            if ($files !== []) {
                return $this->archive(['state' => 'confirm', 'type' => $type, 'files' => $files]);
            }
        }

        [$files, $database] = self::TYPES[$type];
        try {
            $uid = $archives->startArchive($files, $database, [], [], null);
        } catch (\Throwable $error) {
            return $this->archive(['type' => $type, 'package' => $package, 'level' => 'danger', 'canForceUpdate' => $package !== '',
                'message' => _t('ADMIN_BACKUPS_START_BACKUP_ERROR') . ' : ' . htmlspecialchars($error->getMessage())]);
        }
        if ($uid === '') {
            return $this->archive(['type' => $type, 'package' => $package, 'level' => 'danger', 'canForceUpdate' => $package !== '',
                'message' => _t('AU_BACKUP_NOT_QUEUED')]);
        }

        return $this->archive(['state' => 'running', 'uid' => $uid, 'type' => $type, 'package' => $package, 'message' => _t('ADMIN_BACKUPS_STARTED')]);
    }

    #[Route('/api/admin/backups/archive/{uid}', methods: ['GET'], requirements: ['uid' => '[A-Za-z0-9_.-]+'], options: ['acl' => self::ADMIN_ACL])]
    public function follow(string $uid): Response
    {
        $this->denyAccessUnlessAdmin();
        $package = (string)$this->getRequest()->query->get('package', '');
        $stopping = $this->getRequest()->query->getBoolean('stopping');
        $status = $this->getService(ArchiveService::class)->getUIDStatus($uid);
        $base = ['package' => $package, 'output' => self::tail($status['output'])];

        if ($status['failed']) {
            return $this->archive($base + ['level' => 'danger', 'canForceUpdate' => $package !== '', 'message' => $this->failure($status, _t('ADMIN_BACKUPS_UID_STATUS_FAILED'))], true);
        }
        if ($status['stopped']) {
            return $this->archive($base + ['level' => 'success', 'message' => _t('ADMIN_BACKUPS_UID_STATUS_STOP')], true);
        }
        if (!$status['started']) {
            return $this->archive(['package' => $package, 'level' => 'warning', 'canForceUpdate' => $package !== '', 'message' => _t('ADMIN_BACKUPS_UID_STATUS_NOT_FOUND')], true);
        }
        if ($status['finished']) {
            if ($package !== '') {
                return $this->continueUpdate($package, _t('ADMIN_BACKUPS_UID_STATUS_FINISHED') . ' ' . _t('ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING'));
            }

            return $this->archive(['level' => 'success', 'message' => _t('ADMIN_BACKUPS_UID_STATUS_FINISHED')], true);
        }

        return $this->archive($base + [
            'state' => 'running',
            'uid' => $uid,
            'stopping' => $stopping,
            'level' => 'secondary-2',
            'message' => $stopping ? _t('ADMIN_BACKUPS_STOPPING_ARCHIVE') : _t($status['state'] === 'pending' ? 'ADMIN_BACKUPS_UID_STATUS_PENDING' : 'ADMIN_BACKUPS_UID_STATUS_RUNNING'),
        ]);
    }

    #[Route('/api/admin/backups/archive/{uid}/stop', methods: ['POST'], requirements: ['uid' => '[A-Za-z0-9_.-]+'], options: ['acl' => self::ADMIN_ACL])]
    public function stop(string $uid): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }
        $package = (string)$this->getRequest()->request->get('package', '');
        if (!$this->getService(ArchiveService::class)->stopArchive($uid)) {
            return $this->archive(['state' => 'running', 'uid' => $uid, 'package' => $package, 'level' => 'danger', 'message' => _t('ADMIN_BACKUPS_STOP_BACKUP_ERROR')]);
        }

        return $this->archive(['state' => 'running', 'uid' => $uid, 'package' => $package, 'stopping' => true, 'level' => 'warning', 'message' => _t('ADMIN_BACKUPS_STOPPING_ARCHIVE')]);
    }

    /** Before an update: go on without the backup, stopping the one under way first. */
    #[Route('/api/admin/backups/update', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function update(): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }
        $post = $this->getRequest()->request;
        $uid = (string)$post->get('uid', '');
        if ($uid !== '') {
            $this->getService(ArchiveService::class)->stopArchive($uid);
        }

        return $this->continueUpdate((string)$post->get('package', ''), _t('ADMIN_BACKUPS_UID_STATUS_FINISHED_THEN_UPDATING'));
    }

    #[Route('/api/admin/backups/restore', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function restore(): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }
        $file = basename((string)$this->getRequest()->request->get('file', ''));
        try {
            $uid = $this->getService(ArchiveService::class)->startRestore($file, true, true, true);
        } catch (\Throwable) {
            $uid = '';
        }
        if ($uid === '') {
            return $this->restoreHtml(['file' => $file, 'level' => 'danger', 'message' => _t('ADMIN_BACKUPS_RESTORE_ARCHIVE_ERROR', ['filename' => htmlspecialchars($file)])]);
        }

        return $this->restoreHtml(['file' => $file, 'uid' => $uid, 'level' => 'info', 'message' => _t('ADMIN_BACKUPS_RESTORE_ARCHIVE', ['filename' => htmlspecialchars($file)])]);
    }

    #[Route('/api/admin/backups/restore/{uid}', methods: ['GET'], requirements: ['uid' => '[A-Za-z0-9_.-]+'], options: ['acl' => self::ADMIN_ACL])]
    public function followRestore(string $uid): Response
    {
        $this->denyAccessUnlessAdmin();
        $file = basename((string)$this->getRequest()->query->get('file', ''));
        $status = $this->getService(ArchiveService::class)->getUIDStatus($uid);
        if ($status['failed'] || $status['stopped'] || !$status['started']) {
            return $this->restoreHtml(['file' => $file, 'level' => 'danger', 'output' => self::tail($status['output']),
                'message' => $this->failure($status, _t('ADMIN_BACKUPS_RESTORE_ARCHIVE_ERROR', ['filename' => htmlspecialchars($file)]))]);
        }
        if ($status['finished']) {
            return $this->restoreHtml(['file' => $file, 'level' => 'success', 'reload' => true,
                'message' => _t('ADMIN_BACKUPS_RESTORE_ARCHIVE_SUCCESS', ['filename' => htmlspecialchars($file)])]);
        }

        return $this->restoreHtml(['file' => $file, 'uid' => $uid, 'level' => 'info', 'output' => self::tail($status['output']),
            'message' => _t('ADMIN_BACKUPS_RESTORE_ARCHIVE', ['filename' => htmlspecialchars($file)])]);
    }

    #[Route('/api/admin/backups/remote', methods: ['GET'], options: ['acl' => self::ADMIN_ACL])]
    public function remote(): Response
    {
        $this->denyAccessUnlessAdmin();

        return $this->remoteHtml($this->getService(RemoteBackupService::class)->status(), $this->getRequest()->query->getBoolean('following'));
    }

    #[Route('/api/admin/backups/remote', methods: ['POST'], options: ['acl' => self::ADMIN_ACL])]
    public function remoteAction(): Response
    {
        if (($refused = $this->refusal()) !== null) {
            return $refused;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $post = $this->getRequest()->request;
        $form = ['url' => trim((string)$post->get('url', '')), 'username' => trim((string)$post->get('username', ''))];
        $service = $this->getService(RemoteBackupService::class);
        try {
            if ($post->get('op') === 'cancel') {
                $service->cancel();

                return $this->remoteHtml(['running' => false], false, _t('ADMIN_BACKUPS_REMOTE_CANCELLED'), 'warning');
            }

            return $this->remoteHtml($service->start($form['url'], $form['username'], (string)$post->get('password', '')), true, '', '', $form);
        } catch (\Throwable $error) {
            return $this->remoteHtml(['running' => false], false, htmlspecialchars($error->getMessage()), 'danger', $form);
        }
    }

    /**
     * The archive list, with what was just done said above it.
     */
    public function listHtml(string $notice = '', string $level = 'info'): string
    {
        $archives = $this->getService(ArchiveService::class)->getArchives();
        foreach ($archives as &$archive) {
            $archive['readableSize'] = self::readableSize((int)$archive['size']);
            $archive['confirm'] = _t(match ($archive['type']) {
                'only_db' => 'ADMIN_BACKUPS_RESTORE_CONFIRM_ONLY_DB',
                'only_files' => 'ADMIN_BACKUPS_RESTORE_CONFIRM_ONLY_FILES',
                default => 'ADMIN_BACKUPS_RESTORE_CONFIRM_FULL',
            }, ['filename' => (string)$archive['filename']]);
        }

        return $this->render('@core/actions/admin-backups/_list.twig', ['archives' => $archives, 'notice' => $notice, 'level' => $level]);
    }

    /**
     * The remote part as the fetch stands: the form, or where the fetch is; how it ended is said only to a page that was following it.
     *
     * @param array<string, mixed>  $status as RemoteBackupService::status() answers it
     * @param array<string, string> $form   what was typed, given back after a refusal
     */
    public function remoteHtml(array $status, bool $following, string $notice = '', string $level = '', array $form = []): Response
    {
        $running = (bool)($status['running'] ?? false);
        $step = (string)($status['step'] ?? '');
        $changed = false;
        if ($notice === '' && !empty($status['error'])) {
            [$notice, $level] = [htmlspecialchars((string)$status['error']), 'danger'];
        } elseif ($notice === '' && $following && !$running) {
            [$notice, $level, $changed] = $step === 'idle'
                ? [_t('ADMIN_BACKUPS_REMOTE_CANCELLED'), 'warning', false]
                : [_t('ADMIN_BACKUPS_REMOTE_FINISHED', ['filename' => htmlspecialchars((string)($status['filename'] ?? ''))]), 'success', true];
        }
        $total = (int)($status['total'] ?? 0);
        $response = $this->html($this->render('@core/actions/admin-backups/_remote.twig', [
            'running' => $running,
            'step' => self::REMOTE_STEPS[$step] ?? null,
            'rawStep' => $step,
            'bytes' => (int)($status['bytes'] ?? 0),
            'total' => $total,
            'progress' => $step === 'downloading' && $total > 0 ? self::readableSize((int)($status['bytes'] ?? 0)) . ' / ' . self::readableSize($total) : '',
            'warning' => (string)($status['warning'] ?? ''),
            'output' => self::tail((string)($status['output'] ?? '')),
            'notice' => $notice,
            'level' => $level,
            'form' => $form,
        ]));
        if ($changed) {
            $response->headers->set('HX-Trigger', self::CHANGED);
        }

        return $response;
    }

    /**
     * The making of a backup as it stands, for the screen's first drawing and for the pre-update one.
     *
     * @param array<string, mixed> $view
     */
    public function archiveHtml(array $view): string
    {
        return $this->render('@core/actions/admin-backups/_archive.twig', $view + [
            'state' => 'idle',
            'type' => 'full',
            'package' => '',
            'canBypass' => $this->canBypass(),
        ]);
    }

    /**
     * The making of a backup as it stands; an ended one tells the list to reload.
     *
     * @param array<string, mixed> $view
     */
    private function archive(array $view, bool $ended = false): Response
    {
        $response = $this->html($this->archiveHtml($view));
        if ($ended && ($view['package'] ?? '') === '') {
            $response->headers->set('HX-Trigger', self::CHANGED);
        }

        return $response;
    }

    /** Whether the webmaster lets an update go on without its backup. */
    private function canBypass(): bool
    {
        $settings = $this->getService(ParameterBagInterface::class)->get('archive');

        return is_array($settings) && (bool)($settings['authorize_bypass_preupdate_backup'] ?? false);
    }

    /** The update the backup was holding back, sent on with the token that says it may go. */
    private function continueUpdate(string $package, string $message): Response
    {
        $token = $package === '' ? '' : $this->getService(ArchiveService::class)->getForcedUpdateToken();
        if ($token === '') {
            return $this->archive(['package' => $package, 'level' => 'danger', 'message' => _t('ADMIN_BACKUPS_FORCED_UPDATE_NOT_POSSIBLE')]);
        }

        return $this->archive(['state' => 'updating', 'package' => $package, 'level' => 'success', 'message' => $message,
            'updateUrl' => $this->getService(UrlFormatter::class)->href('', '', ['action' => 'upgrade', 'package' => $package, 'forcedUpdateToken' => $token], false)]);
    }

    /** @param array<string, mixed> $view */
    private function restoreHtml(array $view): Response
    {
        return $this->html($this->render('@core/actions/admin-backups/_restore.twig', $view + ['uid' => '', 'output' => '', 'reload' => false]));
    }

    /**
     * Why a run the queue reports failed or killed ended, under its heading.
     *
     * @param array<string, mixed> $status
     */
    private function failure(array $status, string $heading): string
    {
        if (($status['state'] ?? '') === 'killed') {
            return $heading . ' ' . _t('ADMIN_BACKUPS_UID_STATUS_KILLED');
        }

        return ($status['error'] ?? '') !== '' ? $heading . ' ' . htmlspecialchars((string)$status['error']) : $heading;
    }

    /**
     * The message key and level that say why no backup can start now.
     *
     * @param array<string, mixed> $status as ArchiveService::getArchivingStatus() answers it
     *
     * @return array{0: string, 1: string}
     */
    private static function refusalOf(array $status): array
    {
        return match (true) {
            ($status['archiving'] ?? false) === true => ['ADMIN_BACKUPS_START_BACKUP_ERROR_ARCHIVING', 'info'],
            ($status['hibernated'] ?? false) === true => ['ADMIN_BACKUPS_START_BACKUP_ERROR_HIBERNATE', 'info'],
            ($status['privatePathWritable'] ?? true) == false => ['ADMIN_BACKUPS_START_BACKUP_PATH_NOT_WRITABLE', 'danger'],
            ($status['notAvailableOnTheInternet'] ?? true) == false => ['ADMIN_BACKUPS_START_BACKUP_FOLDER_AVAILABLE', 'danger'],
            ($status['enoughSpace'] ?? true) == false => ['ADMIN_BACKUPS_START_BACKUP_NOT_ENOUGH_SPACE', 'warning'],
            default => ['ADMIN_BACKUPS_CANNOT_ARCHIVE', 'danger'],
        };
    }

    /** The last five lines a run wrote. */
    private static function tail(string $output): string
    {
        return implode("\n", array_slice(explode("\n", trim($output)), -5));
    }

    public static function readableSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0';
        }
        $units = ['', 'K', 'M', 'G', 'T', 'P'];
        $power = min(count($units) - 1, (int)floor(log($bytes, 1024)));

        return round($bytes / 1024 ** $power) . ' ' . $units[$power];
    }

    private function refusal(): ?Response
    {
        $this->denyAccessUnlessAdmin();
        if (!$this->getService(RequestGuard::class)->carriesToken($this->getRequest())) {
            return $this->html('<p class="yw-alert yw-alert--danger" role="alert">' . htmlspecialchars(_t('CSRF_TOKEN_FAIL_ERROR')) . '</p>', Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    private function html(string $html, int $status = Response::HTTP_OK): Response
    {
        return new Response($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }
}
