<?php

namespace YesWiki\Test\Content\Field;

use YesWiki\Content\Field\PasswordField;
use YesWiki\Content\Service\FieldFactory;
use YesWiki\Test\Core\YesWikiTestCase;

require_once 'tests/YesWikiTestCase.php';

/**
 * `mot_de_passe` form fields used to store `md5($password)` -- unsalted, and fast enough that a commodity GPU walks the whole plausible keyspace.
 */
class PasswordFieldHashingTest extends YesWikiTestCase
{
    private function field(): PasswordField
    {
        $wiki = $this->getWiki();

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

        $this->assertNotSame($field->hash('same password'), $field->hash('same password'));
    }

    public function testANewHashVerifies(): void
    {
        $field = $this->field();
        $hashed = $field->hash('s3cret-passphrase');

        $this->assertTrue($field->verify($hashed, 's3cret-passphrase'));
        $this->assertFalse($field->verify($hashed, 'not the password'));
    }

    /** md5 is out. */
    public function testALegacyMd5HashIsRefusedEvenWithTheRightPassword(): void
    {
        $field = $this->field();
        $legacy = md5('old-stored-password');

        $this->assertFalse($field->verify($legacy, 'old-stored-password'));
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
