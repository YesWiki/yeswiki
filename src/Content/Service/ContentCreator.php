<?php

namespace YesWiki\Content\Service;

use Psr\Container\ContainerInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Entity\PageBody;
use YesWiki\Content\Field\BazarField;
use YesWiki\Content\Field\FileContentField;
use YesWiki\Identity\Service\UserManager;
use YesWiki\Kernel\Service\CurrentRequest;
use YesWiki\Search\Service\TagsManager;

/**
 * Creates a Content from a submitted form, whatever kind of Content the form describes
 * (ticket 13).
 *
 * Ticket 10 made Page, User and File forms; this is what lets you fill one in. The
 * *form* half is identical for every type -- run each field's formatValuesBeforeSave,
 * compute the title from `entry_title_template`, slug a free tag from it -- so it stays
 * where it already lived, in EntryManager::formatDataBeforeSave(). What differs is
 * persistence, and it differs enough that it cannot be a parameter: an entry gets a
 * `fiche_bazar` triple and keeps its `form_id`, a page gets **no triple at all** (that
 * absence is what makes it a page) and its keywords reindexed, an account has to go
 * through UserManager or it comes out without the owner, ACL and uniqueness guarantees
 * signup gives it.
 *
 * EntryManager::create() still refuses a built-in form, and must: that refusal is what
 * stops a `fiche_bazar` row from being written carrying the Pages form's id, which is a
 * row belonging to no list at all. This service is the way past it, not a hole in it.
 */
class ContentCreator
{
    /** Built-in types a form can create. */
    private const CREATABLE_BUILT_IN = [
        ContentTypeSchema::TYPE_PAGE,
        ContentTypeSchema::TYPE_USER,
        ContentTypeSchema::TYPE_FILE,
    ];

    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /** Whether a form describing this Content type can create one. */
    public static function supports(?string $contentType): bool
    {
        return !ContentTypeSchema::isBuiltIn($contentType)
            || in_array($contentType, self::CREATABLE_BUILT_IN, true);
    }

    /**
     * @param array<string, mixed> $data the submitted values
     *
     * @return array<string, mixed> the created Content, carrying at least its `tag`
     */
    public function create(string $formId, array $data, ?string $sourceUrl = null): array
    {
        $form = $this->container->get(FormManager::class)->getOne($formId);
        if (empty($form)) {
            throw new \Exception('No form with id: ' . $formId);
        }

        $contentType = $form[ContentTypeSchema::CONTENT_TYPE] ?? null;
        if (!ContentTypeSchema::isBuiltIn($contentType)) {
            return $this->container->get(EntryManager::class)->create($formId, $data, false, $sourceUrl);
        }

        $entryManager = $this->container->get(EntryManager::class);
        $data['form_id'] = $formId;
        $entryManager->validate($data, EntryManager::VALIDATE_FLAG_ANTISPAM);

        switch ($contentType) {
            case ContentTypeSchema::TYPE_PAGE:
                return $this->createPage($form, $entryManager->formatDataBeforeSave($data));
            case ContentTypeSchema::TYPE_USER:
                return $this->createUser($form, $data);
            case ContentTypeSchema::TYPE_FILE:
                return $this->createFile($form, $data);
            default:
                throw new \Exception("Creating a '{$contentType}' Content from its form is not supported yet");
        }
    }

    /**
     * A page is a row with no type triple, a body holding the form's fields, and its
     * keywords mirrored into `triples` -- the derived index the page editor maintains.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $data already through the form pipeline: title computed, tag generated
     *
     * @return array<string, mixed>
     */
    private function createPage(array $form, array $data): array
    {
        $tag = (string)$data['tag'];
        $body = $this->bodyFromFields($form, $data);

        // the page editor stores keywords as a list; the tags field submits the comma
        // string a bazar entry keeps (ADR-0009's shapes differ per Content type)
        if (isset($body[PageBody::KEYWORDS]) && !is_array($body[PageBody::KEYWORDS])) {
            $body[PageBody::KEYWORDS] = TagsManager::parseList((string)$body[PageBody::KEYWORDS]);
        }
        $body[PageBody::TITLE] = (string)($data[PageBody::TITLE] ?? '');

        $saved = $this->container->get(PageManager::class)->save($tag, $body, '', true);
        if ($saved !== 0) {
            throw new \Exception("Could not save the new page '$tag'.");
        }

        $this->container->get(TagsManager::class)->reindex($tag, TagsManager::keywordsOf(['body' => $body]));
        $this->applyFormProperties($form, $data);

        return $this->created($form, $body, $tag);
    }

    /**
     * An account is created by UserManager, because everything that makes a row an
     * account rather than a page -- the `user` triple and its tag cache, owner = itself,
     * the `%\n@admins` write ACL, name and email uniqueness -- lives there. What this
     * adds is the rest of the form: the tag is settled first so a field needing it
     * (a profile picture) formats against the account's real tag, and the fields
     * UserManager's own body shape does not name are carried through.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $data raw submission: the password must still be plaintext here
     *
     * @return array<string, mixed>
     */
    private function createUser(array $form, array $data): array
    {
        $nameField = ContentTypeSchema::tagMirrorField(ContentTypeSchema::TYPE_USER) ?? 'username';
        $username = trim((string)($data[$nameField] ?? ''));
        if ($username === '') {
            throw new \Exception(_t('BAZ_CHAMPS_REQUIS') . ':' . $nameField);
        }

        // PasswordField hashes on save and UserManager::create() hashes again: read the
        // plaintext before the form pipeline runs, and let UserManager be the one to hash
        $plainPassword = (string)($data['password'] ?? '');

        $data['tag'] = $this->container->get(PageManager::class)->suggestFreeTag($username);
        $data = $this->container->get(EntryManager::class)->formatDataBeforeSave($data);

        $body = $this->bodyFromFields($form, $data);
        // the account name is the tag; storing it again is a second copy that can drift
        unset($body[$nameField], $body['password']);

        $user = $this->container->get(UserManager::class)->create(
            array_merge($body, ['name' => $username, 'password' => $plainPassword]),
            (string)($body['email'] ?? ''),
            $plainPassword,
            (string)$data['tag'],
        );
        if ($user === null) {
            throw new \Exception("Could not save the new account '$username'.");
        }

        $this->applyFormProperties($form, $data);

        return $this->created($form, array_merge($body, [$nameField => $user['name']]), (string)$user['name']);
    }

    /**
     * A file is its bytes: the upload has to arrive, and everything the File type stores
     * about it -- the two filenames, the size, the mime type -- is derived from it rather
     * than typed in. FileManager owns that, exactly as it does for the upload API route.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $data raw submission; the upload is in $_FILES, not here
     *
     * @return array<string, mixed>
     */
    private function createFile(array $form, array $data): array
    {
        // checked before the form pipeline runs, not after: with no upload there is no
        // filename, so the pipeline would fail on the *title* it computes from one and
        // report a missing title to someone who is missing a file
        if (!$this->hasUpload($form)) {
            throw new \Exception(_t('ERROR_NO_FILE_UPLOADED'));
        }

        $data = $this->container->get(EntryManager::class)->formatDataBeforeSave($data);
        if (empty($data['stored_filename'])) {
            throw new \Exception(_t('ERROR_NO_FILE_UPLOADED'));
        }

        $file = $this->container->get(FileManager::class)->create(
            (string)($data['original_filename'] ?? $data['stored_filename']),
            (string)$data['stored_filename'],
            (string)($data['uploaded_from'] ?? ''),
            (int)($data['size'] ?? 0),
            (string)($data['mime_type'] ?? ''),
        );

        $this->applyFormProperties($form, $file);

        return $this->created($form, $file, (string)$file['tag']);
    }

    /**
     * The created Content in the shape everything downstream expects an entry in.
     *
     * `form_id` above all: `entry.created` listeners read it -- the webhook dispatcher
     * refuses a payload without one -- and a page, an account and a file do not store it,
     * because which form describes them is their type triple (ticket 10).
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function created(array $form, array $body, string $tag): array
    {
        return array_merge($body, [
            'tag' => $tag,
            'form_id' => $form['id'],
        ]);
    }

    /**
     * Whether the submission actually carries bytes for one of the form's file-content
     * fields. PHP puts uploads in $_FILES, so they are not in the posted data at all.
     *
     * @param array<string, mixed> $form
     */
    private function hasUpload(array $form): bool
    {
        $files = $this->container->get(CurrentRequest::class)->get()->files;
        foreach ($form['prepared'] ?? [] as $field) {
            if ($field instanceof FileContentField && !empty($files->get($field->getName()))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The submitted values a form actually declares, and nothing else: the entry
     * bookkeeping formatDataBeforeSave() adds (form_id, status, created_at...) describes
     * a bazar entry, not a page or an account.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function bodyFromFields(array $form, array $data): array
    {
        $body = [];
        foreach ($form['prepared'] ?? [] as $field) {
            if (!$field instanceof BazarField) {
                continue;
            }
            $name = $field->getPropertyName();
            if (!empty($name) && array_key_exists($name, $data)) {
                $body[$name] = $data[$name];
            }
        }

        return $body;
    }

    /**
     * The `entry_*` properties that are not entry-only still apply: a webmaster who set
     * default ACLs or presentation metadata on the Pages form meant them for new pages.
     *
     * @param array<string, mixed> $form
     * @param array<string, mixed> $data
     */
    private function applyFormProperties(array $form, array $data): void
    {
        $formProperties = $this->container->get(FormPropertiesService::class);
        $formProperties->applyEntryAcls($form, $data, true);
        $formProperties->applyEntryMetadatas($form, $data);
    }
}
