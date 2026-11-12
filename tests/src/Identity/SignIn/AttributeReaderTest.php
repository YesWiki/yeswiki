<?php

namespace YesWiki\Test\Identity\SignIn;

use PHPUnit\Framework\TestCase;
use YesWiki\Identity\SignIn\AttributeReader;

class AttributeReaderTest extends TestCase
{
    public function testOneTakesTheFirstUsableValue(): void
    {
        $this->assertSame('a@x', AttributeReader::one(['mail' => ['a@x', 'b@x']], 'mail'));
        $this->assertSame('42', AttributeReader::one(['uid' => 42], 'uid'));
        $this->assertNull(AttributeReader::one(['mail' => ['', null]], 'mail'));
        $this->assertNull(AttributeReader::one([], 'mail'));
        $this->assertNull(AttributeReader::one(['mail' => ['nested' => ['x']]], 'mail'));
    }

    public function testManyAcceptsAStringOrAList(): void
    {
        $this->assertSame(['a'], AttributeReader::many(['g' => 'a'], 'g'));
        $this->assertSame(['a', 'b'], AttributeReader::many(['g' => ['a', '', 'b']], 'g'));
        $this->assertSame([], AttributeReader::many([], 'g'));
    }

    // Review Focus: binary and oversized attributes.
    public function testBinaryValuesAreDescribedNotCopied(): void
    {
        $logged = AttributeReader::forLog(['jpegPhoto' => "\xFF\xD8\xFF\xE0binary", 'mail' => 'a@x']);
        $this->assertSame('(binary, 10 bytes)', $logged['jpegPhoto']);
        $this->assertSame('a@x', $logged['mail']);
        $this->assertNotFalse(json_encode($logged));
        $this->assertNull(AttributeReader::one(['photo' => "\xFF\xD8"], 'photo'));
    }

    public function testLogSizeIsCapped(): void
    {
        $logged = AttributeReader::forLog(['groups' => array_fill(0, 5000, str_repeat('x', 40))], 1000);
        $this->assertLessThanOrEqual(1000, strlen((string)json_encode($logged)));
        $this->assertArrayHasKey('_truncated', $logged);
    }
}
