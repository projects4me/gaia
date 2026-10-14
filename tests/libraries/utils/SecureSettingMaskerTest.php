<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\SecureSettingMasker;
use Gaia\Libraries\Utils\SettingsCipher;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for hiding secure setting values in API documents.
 */
class SecureSettingMaskerTest extends TestCase
{
    public function testSecureValueIsReplacedAndIsSetIsTrue()
    {
        $resource = SecureSettingMasker::maskResource($this->setting('email.smtpPassword', 'sbx1:abc', true));

        $this->assertNull($resource['attributes']['settingValue']);
        $this->assertTrue($resource['attributes']['isSet']);
    }

    public function testSecureSettingWithoutAValueReportsNotSet()
    {
        $resource = SecureSettingMasker::maskResource($this->setting('email.smtpPassword', null, true));

        $this->assertNull($resource['attributes']['settingValue']);
        $this->assertFalse($resource['attributes']['isSet']);
    }

    public function testNonSecureValuesPassThrough()
    {
        $resource = SecureSettingMasker::maskResource($this->setting('email.smtpHost', 'smtp.example.com', false));

        $this->assertSame('smtp.example.com', $resource['attributes']['settingValue']);
        $this->assertArrayNotHasKey('isSet', $resource['attributes']);
    }

    public function testCipherTextIsMaskedEvenWhenTheSecureFlagIsNotReturned()
    {
        $resource = $this->setting('email.smtpPassword', SettingsCipher::PREFIX . 'abc', true);
        unset($resource['attributes']['secure']);

        $resource = SecureSettingMasker::maskResource($resource);

        $this->assertNull($resource['attributes']['settingValue']);
    }

    public function testListTypeAliasIsMaskedToo()
    {
        $resource = $this->setting('email.smtpPassword', 'sbx1:abc', true);
        $resource['type'] = 'Setting';

        $this->assertNull(SecureSettingMasker::maskResource($resource)['attributes']['settingValue']);
    }

    public function testOtherResourceTypesAreUntouched()
    {
        $user = ['type' => 'user', 'id' => '1', 'attributes' => ['settingValue' => 'sbx1:abc', 'secure' => 1]];

        $this->assertSame($user, SecureSettingMasker::maskResource($user));
    }

    public function testDocumentWithSingleResourceListAndIncludedIsMasked()
    {
        $single = SecureSettingMasker::maskDocument(['data' => $this->setting('a', 'sbx1:x', true)]);
        $this->assertNull($single['data']['attributes']['settingValue']);

        $list = SecureSettingMasker::maskDocument([
            'data' => [$this->setting('a', 'sbx1:x', true), $this->setting('b', 'plain', false)],
            'included' => [$this->setting('c', 'sbx1:y', true)],
            'meta' => ['count' => 2],
        ]);
        $this->assertNull($list['data'][0]['attributes']['settingValue']);
        $this->assertSame('plain', $list['data'][1]['attributes']['settingValue']);
        $this->assertNull($list['included'][0]['attributes']['settingValue']);
        $this->assertSame(['count' => 2], $list['meta']);
    }

    private function setting($name, $value, $secure)
    {
        return [
            'type' => 'setting',
            'id' => $name,
            'attributes' => ['name' => $name, 'settingValue' => $value, 'secure' => $secure],
        ];
    }
}
