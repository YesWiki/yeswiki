<?php

namespace YesWiki\Import\Service;

use League\HTMLToMarkdown\HtmlConverter;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use YesWiki\Content\Entity\ContentTypeSchema;
use YesWiki\Content\Service\EntryManager;
use YesWiki\Content\Service\FormManager;
use YesWiki\Content\Service\ListManager;

class ImapImporter extends Importer
{
    protected string $source;
    /** @var list<array<string, mixed>> the form this importer installs when it has none */
    protected array $databaseForms;
    protected mixed $mailBox = null;

    public function __construct(
        string $source,
        ParameterBagInterface $params,
        ContainerInterface $services,
        EntryManager $entryManager,
        ImporterManager $importerManager,
        FormManager $formManager,
        ListManager $listManager
    ) {
        $this->source = $source;
        $this->params = $params;
        $this->services = $services;
        $this->entryManager = $entryManager;
        $this->importerManager = $importerManager;
        $this->formManager = $formManager;
        $this->listManager = $listManager;
        $dataSources = $params->has('dataSources') ? $params->get('dataSources') : [];
        $sourceOptions = is_array($dataSources) ? ($dataSources[$this->source] ?? []) : [];
        $this->config = $this->checkConfig(is_array($sourceOptions) ? $sourceOptions : []);
        $this->databaseForms = [
            [
                'id' => null,
                'label' => 'Imports de mails depuis imap',
                'description' => 'Imports de mails depuis imap',
                'condition' => '',
                ContentTypeSchema::CONTENT_TYPE => ContentTypeSchema::TYPE_ENTRY,
                // the legacy `***` template syntax is still read (ADR-0009): FormManager
                // re-encodes it to the stored JSON field objects on the way in
                'template' => <<<EOT
texte***bf_titre***Sujet***255***255*** *** ***text***1*** *** *** * *** * *** *** *** ***
date***bf_date***Date de réception*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***
texte***bf_auteurice***Emeteurice***255***255*** *** *** ***0*** *** *** * *** * *** *** *** ***
email***bf_auteurice_email***Email émeteurice*** *** *** *** *** ***0*** *** *** * *** * *** *** *** ***
textelong***bf_description***Message***80***12*** *** ***wiki***0*** *** *** * *** * *** *** *** ***
texte***message_id***message_id***80***8*** *** ***wiki***0*** *** *** * *** * *** *** *** ***
EOT,
                'lang' => 'fr-FR',
                'only_one_entry' => 'N',
                'only_one_entry_message' => null,
            ],
        ];
    }

    /**
     * Check if config input is good enough to be used by Importer.
     *
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed> checked config
     */
    public function checkConfig(array $config)
    {
        $config = parent::checkConfig($config);
        $this->config['attachments_folder'] = $this->config['attachments_folder'] ?? null;
        if (!empty($this->config['attachments_folder']) && !is_dir($this->config['attachments_folder'])) {
            if (!mkdir($this->config['attachments_folder'], 0755, true)) {
                throw new \Exception("Folder for attachments {$this->config['attachments_folder']} could'nt be created.");
            }
        }

        return $config;
    }

    public static function getAdminFields(): array
    {
        return [
            'imap_server_and_folder' => ['type' => 'text', 'required' => true],
            'imap_user' => ['type' => 'text', 'required' => true],
            'imap_password' => ['type' => 'password', 'required' => true],
            'imap_query' => ['type' => 'text', 'required' => true],
            'attachments_folder' => ['type' => 'text', 'required' => false],
        ];
    }

    // message_id is excluded on purpose: syncData() relies on it verbatim as the entry's dedup/identity key
    public static function getOwnFields(): array
    {
        return [
            ['key' => 'bf_titre', 'label' => 'Sujet'],
            ['key' => 'bf_date', 'label' => 'Date de réception'],
            ['key' => 'bf_auteurice', 'label' => 'Emeteurice'],
            ['key' => 'bf_auteurice_email', 'label' => 'Email émeteurice'],
            ['key' => 'bf_description', 'label' => 'Message'],
        ];
    }

    public function authenticate(): void
    {
        // php-imap is a suggested dependency, not a required one: it needs ext-imap, which
        // PHP has been moving out of core, and a wiki that imports no mailbox should not
        // have to install either. Say so plainly rather than fataling on a missing class.
        if (!class_exists(\PhpImap\Mailbox::class)) {
            throw new \Exception('L\'import de mails demande la librairie php-imap : "composer require php-imap/php-imap" (et l\'extension php "imap").');
        }
        // Create PhpImap\Mailbox instance for all further actions
        $this->mailBox = new \PhpImap\Mailbox(
            $this->config['imap_server_and_folder'],
            $this->config['imap_user'],
            $this->config['imap_password'],
            $this->config['attachments_folder'],
            'UTF-8', // Server encoding (optional)
            true, // Trim leading/ending whitespaces of IMAP path (optional)
            true // Attachment filename mode (optional; false = random filename; true = original filename)
        );
        // set some connection arguments (if appropriate)
        $this->mailBox->setConnectionArgs(
            CL_EXPUNGE // expunge deleted mails upon mailbox close
            //  | OP_SECURE // don't do non-secure authentication
        );
    }

    /**
     * @return array<string, mixed> the mails found, keyed by message id
     */
    public function getData(): array
    {
        $this->authenticate();
        try {
            // PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php
            $mailsIds = $this->mailBox->searchMailbox($this->config['imap_query']);
        } catch (\PhpImap\Exceptions\ConnectionException $ex) {
            // a sync can run from a page view now (maintenance) -- report, never die()
            throw new \Exception('IMAP connection failed: ' . implode(',', $ex->getErrors('all')));
        }

        // If $mailsIds is empty, no emails could be found
        if (!$mailsIds) {
            echo 'No emails found.' . "\n";

            return [];
        }
        $data = [];
        foreach ($mailsIds as $m) {
            $mail = $this->mailBox->getMail($m, false);
            $data[$mail->messageId] = $mail;
        }

        return $data;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function mapData(mixed $data): array
    {
        $preparedData = [];
        $converter = new HtmlConverter(['strip_tags' => true]); // we will convert html to md, but safe
        foreach ($data as $i => $email) {
            if ($email->textHtml) {
                $message = $email->textHtml;
            } else {
                $message = $email->textPlain;
            }
            $entry = [];
            $entry['bf_titre'] = $email->subject;
            $entry['bf_auteurice'] = (string)($email->fromName ?? $email->fromAddress);
            $entry['bf_auteurice_email'] = (string)$email->fromAddress;
            $entry['bf_description'] = $converter->convert($message);
            $entry['message_id'] = trim($i, '<>');
            $receivedAt = date_create((string)$email->date) ?: new \DateTime();
            $entry['created_at'] = $entry['bf_date'] = date_format($receivedAt, 'Y-m-d H:i:s');
            $preparedData[$i] = $this->applyFieldsMapping($entry);
        }

        return $preparedData;
    }

    /**
     * @param array<mixed> $data
     */
    public function syncData(array $data): void
    {
        $existingEntries = $this->entryManager->search(['formsIds' => [$this->config['formId']]]);
        foreach ($data as $entry) {
            $res = multiArraySearch($existingEntries, 'message_id', $entry['message_id']);
            if (!$res) {
                $entry['antispam'] = 1;
                $this->entryManager->create($this->config['formId'], $entry, false, $entry['message_id']);
                echo 'L\'email "' . ($entry['bf_titre'] ?? $entry['message_id']) . '" a été créé.' . "\n";
            } else {
                echo 'L\'email "' . ($entry['bf_titre'] ?? $entry['message_id']) . '" existe déja.' . "\n";
            }
        }
    }

    public function syncFormModel(): void
    {
        // test if the form exists, if not, install it
        $form = $this->formManager->getOne($this->config['formId']);
        if (empty($form)) {
            $this->databaseForms[0]['id'] = $this->config['formId'];
            $this->formManager->create($this->databaseForms[0]);
        } else {
            echo 'Le formulaire existe déjà.' . "\n";
            // test if compatible
        }
    }
}
