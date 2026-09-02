<?php

namespace YesWiki\Content\Service;

use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\FieldRole;
use YesWiki\Files\Service\AttachedFilePaths;
use YesWiki\Files\Service\Storage;
use YesWiki\Identity\Service\AvatarService;
use YesWiki\Kernel\Service\UrlFormatter;

/** The picture a piece of Content is shown with: an entry's image field, an account's profile picture, a file's own bytes when it is an image. */
class ContentImage
{
    public function __construct(
        private readonly FormManager $formManager,
        private readonly FieldRoleResolver $roles,
        private readonly AttachedFilePaths $paths,
        private readonly Storage $storage,
        private readonly UrlFormatter $urlFormatter,
        private readonly AvatarService $avatars,
    ) {
    }

    /**
     * @param array<string, mixed> $page the row, body decoded
     */
    public function urlFor(array $page, string $contentType, string $formId = ''): ?string
    {
        $tag = (string)($page['tag'] ?? '');
        $body = is_array($page['body'] ?? null) ? $page['body'] : [];

        return match ($contentType) {
            ContentTypeSchema::TYPE_USER => $this->avatars->forName($tag)->imageUrl,
            ContentTypeSchema::TYPE_FILE => $this->paths->isPicture((string)($body['original_filename'] ?? ''))
                ? $this->urlFormatter->href('', 'api/files/' . rawurlencode($tag) . '/download', [], false)
                : null,
            ContentTypeSchema::TYPE_ENTRY => $this->fromImageField($body, $formId !== '' ? $formId : (string)($body['form_id'] ?? '')),
            default => null,
        };
    }

    /** @param array<string, mixed> $body */
    private function fromImageField(array $body, string $formId): ?string
    {
        if ($formId === '') {
            return null;
        }
        $property = $this->roles->propertyName($this->formManager->getOne($formId), FieldRole::IMAGE);
        $value = $property === null ? '' : trim((string)($body[$property] ?? ''));
        if ($value === '') {
            return null;
        }
        if (preg_match('#^(https?:)?//#i', $value) === 1) {
            return $value;
        }
        $inUploads = rtrim($this->paths->uploadPath(), '/') . '/' . $value;
        if ($this->storage->exists($inUploads)) {
            return $inUploads;
        }
        $attached = $this->paths->fullFilename($value);

        return $attached === '' ? null : $attached;
    }
}
