<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Utils\TranslationLocales;
use Gaia\Libraries\Utils\ValueLabelRules;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the rules of list values (issue statuses, issue types) and their labels.
 */
class ValueLabelRulesTest extends TestCase
{
    /**
     * @var ValueLabelRules
     */
    private $rules;

    protected function setUp(): void
    {
        $this->rules = new ValueLabelRules(new TranslationLocales(['en-us', 'ur-pk']), 'status');
    }

    public function testAcceptsLowercaseDigitsAndUnderscoresUpToThirtyCharacters()
    {
        $this->assertNull($this->rules->valueError('in_progress'));
        $this->assertNull($this->rules->valueError('stage_2'));
        $this->assertNull($this->rules->valueError(str_repeat('a', 30)));
    }

    public function testRejectsValuesOutsideThePattern()
    {
        foreach (['In Progress', 'in-progress', 'In_progress', '', 'a.b', 'ünder', "in_progress\n", null, 12] as $value) {
            $this->assertNotNull($this->rules->valueError($value), var_export($value, true));
        }
    }

    public function testRejectsValuesLongerThanThirtyCharacters()
    {
        $this->assertSame('Value must be at most 30 characters.', $this->rules->valueError(str_repeat('a', 31)));
    }

    public function testTheValueCannotChange()
    {
        $this->assertNull($this->rules->valueChangeError('in_progress', 'in_progress'));
        $this->assertSame('The value of a status cannot be changed.', $this->rules->valueChangeError('in_progress', 'doing'));
    }

    public function testCreateNeedsTheEnUsLabel()
    {
        $this->assertSame('The en-us label is required.', $this->rules->translationsError([], true));
        $this->assertNotNull($this->rules->translationsError(null, true));
        $this->assertSame('The en-us label is required.', $this->rules->translationsError(['ur-pk' => 'جاری'], true));
        $this->assertNull($this->rules->translationsError(['en-us' => 'In Progress'], true));
        $this->assertNull($this->rules->translationsError(['en-us' => 'In Progress', 'ur-pk' => 'جاری'], true));
    }

    public function testUpdateMayOmitLabelsOrSendOnlySome()
    {
        $this->assertNull($this->rules->translationsError(null, false));
        $this->assertNull($this->rules->translationsError(['ur-pk' => 'جاری'], false));
    }

    public function testRejectsUnsupportedLocales()
    {
        $error = $this->rules->translationsError(['en-us' => 'New', 'xx-yy' => 'New'], true);

        $this->assertSame('Unsupported locale: xx-yy. Supported: en-us, ur-pk.', $error);
    }

    public function testRejectsEmptyOrNonStringLabels()
    {
        $this->assertNotNull($this->rules->translationsError(['en-us' => ''], true));
        $this->assertNotNull($this->rules->translationsError(['en-us' => '   '], true));
        $this->assertNotNull($this->rules->translationsError(['en-us' => ['New']], true));
        $this->assertNotNull($this->rules->translationsError('New', false));
    }

    public function testLabelsHaveNoThirtyCharacterLimit()
    {
        $this->assertNull($this->rules->translationsError(['en-us' => str_repeat('Long label ', 10)], true));
    }

    public function testMessagesNameTheList()
    {
        $rules = new ValueLabelRules(new TranslationLocales(['en-us']), 'type');

        $this->assertSame('The value of a type cannot be changed.', $rules->valueChangeError('bug', 'defect'));
    }
}
