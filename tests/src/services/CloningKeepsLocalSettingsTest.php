<?php

namespace YesWiki\Test\Core\Services;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use YesWiki\Admin\Service\ArchiveService;
use YesWiki\Admin\Service\RemoteWikiArchive;

/** Ticket 06: a clone takes the remote wiki's contents and none of its connections. */
#[CoversMethod(ArchiveService::class, 'isLocalOnly')]
class CloningKeepsLocalSettingsTest extends TestCase
{
    public function testTheConfigurationFileIsNeverRestored(): void
    {
        $this->assertTrue(ArchiveService::isLocalOnly('yeswiki.config.php'));
        $this->assertTrue(ArchiveService::isLocalOnly('./yeswiki.config.php'));
    }

    public function testTheEnvironmentFileIsNeverRestored(): void
    {
        $this->assertTrue(ArchiveService::isLocalOnly('private/.env'));
        $this->assertTrue(ArchiveService::isLocalOnly('./private/.env'));
    }

    public function testEverythingElseCrossesOver(): void
    {
        foreach ([
            'files/photo.jpg',
            'custom/theme.css',
            'private/files/document.pdf',
            'custom/extensions/bazar/config.yaml',
        ] as $content) {
            $this->assertFalse(ArchiveService::isLocalOnly($content), "$content is the wiki's content and should be restored");
        }
    }

    /** The wiki's name, theme and languages are what make a clone a clone; its address, database and prefix are what keep it a separate wiki. */
    public function testTheRemotesSettingsCrossOverExceptTheConnectionOnes(): void
    {
        $local = [
            'base_url' => 'https://new.example.org/?',
            'db_driver' => 'mysql',
            'db_database' => 'wiki_new',
            'db_user' => 'wiki_new',
            'db_password' => 'local-secret',
            'table_prefix' => 'dst_',
            'yeswiki_name' => 'Fresh Target',
            'default_language' => 'en',
        ];
        $remote = [
            'base_url' => 'https://old.example.org/?',
            'db_driver' => 'mysql',
            'db_database' => 'wiki_old',
            'db_user' => 'wiki_old',
            'db_password' => 'remote-secret',
            'table_prefix' => 'src_',
            'yeswiki_name' => 'The Source Wiki',
            'default_language' => 'fr',
            'favorite_theme' => 'margot',
        ];

        $merged = ArchiveService::mergedSettings($local, $remote);

        foreach (ArchiveService::localOnlyKeys() as $mine) {
            if (isset($local[$mine])) {
                $this->assertSame($local[$mine], $merged[$mine], "$mine must stay this wiki's own");
            }
        }

        $this->assertSame('The Source Wiki', $merged['yeswiki_name']);
        $this->assertSame('fr', $merged['default_language']);
        $this->assertSame('margot', $merged['favorite_theme']);
    }

    public function testTheRemotesDatabasePasswordNeverArrives(): void
    {
        $merged = ArchiveService::mergedSettings(
            ['db_password' => 'local-secret', 'db_database' => 'wiki_new'],
            ['db_password' => 'remote-secret', 'db_database' => 'wiki_old']
        );

        $this->assertSame('local-secret', $merged['db_password']);
        $this->assertSame('wiki_new', $merged['db_database']);
    }

    /** A backup blanks the mail server and the API keys it was told to hide, and a restore must not copy the blanks over this wiki's. */
    public function testWhatTheBackupHidIsKept(): void
    {
        $merged = ArchiveService::mergedSettings(
            ['contact_smtp_pass' => 'local-mail-secret', 'my_token' => 'local-token', 'archive' => ['max_nb_files' => 4]],
            [
                'contact_smtp_pass' => '',
                'my_token' => '',
                'archive' => ['hideConfigValues' => ['my_token' => '']],
                'favorite_theme' => 'margot',
            ]
        );

        $this->assertSame('local-mail-secret', $merged['contact_smtp_pass']);
        $this->assertSame('local-token', $merged['my_token']);
        $this->assertSame(['max_nb_files' => 4], $merged['archive'], 'how this wiki backs itself up stays its own');
        $this->assertSame('margot', $merged['favorite_theme']);
    }

    public function testTheWikiIsFoundWhateverPartOfItsAddressWasPasted(): void
    {
        foreach ([
            'https://wiki.example.org' => 'https://wiki.example.org',
            'https://wiki.example.org/' => 'https://wiki.example.org',
            'https://wiki.example.org/?PagePrincipale' => 'https://wiki.example.org',
            'wiki.example.org' => 'https://wiki.example.org',
            'https://example.org/mywiki/' => 'https://example.org/mywiki',
            'https://example.org/mywiki/?PagePrincipale' => 'https://example.org/mywiki',
            'http://localhost:8080/?' => 'http://localhost:8080',
            'https://example.org/mywiki/index.php?wiki=PagePrincipale' => 'https://example.org/mywiki',
            'HTTPS://Example.org/' => 'https://Example.org',
            '' => '',
        ] as $pasted => $expected) {
            $this->assertSame($expected, RemoteWikiArchive::baseUrlOf($pasted), "'$pasted'");
        }
    }
}
