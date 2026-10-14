<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Libraries\Security\SecureSettingMasker;
use Gaia\Libraries\Security\SettingsCipher;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for hiding secure setting values in API documents.
 */
class SecureSettingMaskerTest extends TestCase
{
    public function testSecureValueIsReplacedAndIsSetIsTrue()
    {
        $resource = SecureSettingMasker::maskResource($this->setting('email.smtpPassword', 'sbx1:abc', true));

        $this->assertNull($resource['attributes']['value']);
        $this->assertTrue($resource['attributes']['isSet']);
    }

    public function testSecureSettingWithoutAValueReportsNotSet()
    {
        $resource = SecureSettingMasker::maskResource($this->setting('email.smtpPassword', null, true));

        $this->assertNull($resource['attributes']['value']);
        $this->assertFalse($resource['attributes']['isSet']);
    }

    public function testNonSecureValuesPassThrough()
    {
        $resource = SecureSettingMasker::maskResource($this->setting('email.smtpHost', 'smtp.example.com', false));

        $this->assertSame('smtp.example.com', $resource['attributes']['value']);
        $this->assertArrayNotHasKey('isSet', $resource['attributes']);
    }

    public function testCipherTextIsMaskedEvenWhenTheSecureFlagIsNotReturned()
    {
        $resource = $this->setting('email.smtpPassword', SettingsCipher::PREFIX . 'abc', true);
        unset($resource['attributes']['secure']);

        $resource = SecureSettingMasker::maskResource($resource);

        $this->assertNull($resource['attributes']['value']);
    }

    public function testListTypeAliasIsMaskedToo()
    {
        $resource = $this->setting('email.smtpPassword', 'sbx1:abc', true);
        $resource['type'] = 'Setting';

        $this->assertNull(SecureSettingMasker::maskResource($resource)['attributes']['value']);
    }

    public function testOtherResourceTypesAreUntouched()
    {
        $user = ['type' => 'user', 'id' => '1', 'attributes' => ['value' => 'sbx1:abc', 'secure' => 1]];

        $this->assertSame($user, SecureSettingMasker::maskResource($user));
    }

    public function testDocumentWithSingleResourceListAndIncludedIsMasked()
    {
        $single = SecureSettingMasker::maskDocument(['data' => $this->setting('a', 'sbx1:x', true)]);
        $this->assertNull($single['data']['attributes']['value']);

        $list = SecureSettingMasker::maskDocument([
            'data' => [$this->setting('a', 'sbx1:x', true), $this->setting('b', 'plain', false)],
            'included' => [$this->setting('c', 'sbx1:y', true)],
            'meta' => ['count' => 2],
        ]);
        $this->assertNull($list['data'][0]['attributes']['value']);
        $this->assertSame('plain', $list['data'][1]['attributes']['value']);
        $this->assertNull($list['included'][0]['attributes']['value']);
        $this->assertSame(['count' => 2], $list['meta']);
    }

    private function setting($name, $value, $secure)
    {
        return [
            'type' => 'setting',
            'id' => $name,
            'attributes' => ['name' => $name, 'value' => $value, 'secure' => $secure],
        ];
    }
}
