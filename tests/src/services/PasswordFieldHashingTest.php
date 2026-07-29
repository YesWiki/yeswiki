<?php

namespace YesWiki\Test\Content\Field;

use YesWiki\Content\Field\PasswordField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `mot_de_passe` form fields used to store `md5($password)` -- unsalted, and fast enough
 * that a commodity GPU walks the whole plausible keyspace. They now go through the same
 * hasher factory as user-account passwords.
 *
 * Values written by older YesWikis are still md5, so the tests that matter are as much
 * about verifying those as about hashing new ones: an upgrade must not lock anyone out.
 */
class PasswordFieldHashingTest extends YesWikiTestCase
{
    private function field(): PasswordField
    {
        $wiki = $this->getWiki();
        // the positional array is the internal wire format the field constructors
        // consume (ADR-0009): slot 0 the type keyword, slot 1 the name, and the rest
        // padded because BazarField reads up to slot 12 unconditionally
        $values = array_fill(0, 16, '');
        $values[0] = 'mot_de_passe';
        $values[1] = 'bf_password';
        $field = $wiki->services->get(FieldFactory::class)->create($values);
        $this->assertInstanceOf(PasswordField::class, $field);

        return $field;
    }

    public function testAStoredValueIsNoLongerAnMd5(): void
    {
        $field = $this->field();
        $plain = 'correct horse battery staple';

        $hashed = $field->hash($plain);

        $this->assertNotSame(md5($plain), $hashed, 'md5 is not a password hash');
        $this->assertGreaterThan(32, strlen($hashed), 'a modern hash carries its algorithm and salt');
    }

    public function testTheSamePasswordHashesDifferentlyEachTime(): void
    {
        $field = $this->field();

        // salting: two accounts with the same password must not share a hash
        $this->assertNotSame($field->hash('same password'), $field->hash('same password'));
    }

    public function testANewHashVerifies(): void
    {
        $field = $this->field();
        $hashed = $field->hash('s3cret-passphrase');

        $this->assertTrue($field->verify($hashed, 's3cret-passphrase'));
        $this->assertFalse($field->verify($hashed, 'not the password'));
    }

    /** The upgrade path: nobody stored before this change is locked out. */
    public function testALegacyMd5HashStillVerifies(): void
    {
        $field = $this->field();
        $legacy = md5('old-stored-password');

        $this->assertTrue($field->verify($legacy, 'old-stored-password'));
        $this->assertFalse($field->verify($legacy, 'wrong'));
    }

    public function testALegacyMd5HashIsReportedAsNeedingRehash(): void
    {
        $field = $this->field();

        $this->assertTrue($field->needsRehash(md5('old-stored-password')));
        $this->assertFalse($field->needsRehash($field->hash('freshly hashed')));
    }

    public function testVerifyingAgainstNoStoredHashIsAlwaysFalse(): void
    {
        $field = $this->field();

        $this->assertFalse($field->verify(null, 'anything'));
        $this->assertFalse($field->verify('', 'anything'));
        $this->assertFalse($field->needsRehash(null));
    }

    /** The field's own save path is what actually writes to an entry body. */
    public function testFormatValuesBeforeSaveStoresAHashNotThePlainPassword(): void
    {
        $field = $this->field();

        $saved = $field->formatValuesBeforeSave(['bf_password' => 'typed in the form']);

        $stored = $saved['bf_password'];
        $this->assertNotSame('typed in the form', $stored);
        $this->assertNotSame(md5('typed in the form'), $stored);
        $this->assertTrue($field->verify($stored, 'typed in the form'));
    }

    public function testAnEmptySubmissionKeepsThePreviousHash(): void
    {
        $field = $this->field();
        $previous = $field->hash('unchanged');

        $saved = $field->formatValuesBeforeSave([
            'bf_password' => '',
            'bf_password-previous' => $previous,
        ]);

        $this->assertSame($previous, $saved['bf_password']);
    }
}
