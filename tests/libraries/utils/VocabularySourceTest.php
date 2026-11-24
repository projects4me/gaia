<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Search\VocabularySource;
use PHPUnit\Framework\TestCase;

/**
 * Where a search type's vocabulary is read from and kept (agaia-4891, D-010).
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class VocabularySourceTest extends TestCase
{
    public function testAModelWithoutASearchBlockHasNoVocabulary()
    {
        $this->assertNull(VocabularySource::fromMetadata(array('tableName' => 'notes', 'fields' => array())));
    }

    public function testTheSourceIsTheFtsColumnsAndTheProjectGroupKey()
    {
        $source = VocabularySource::fromMetadata($this->metadata(array('subject' => 'A', 'description' => 'B')));

        $this->assertSame('issue', $source->type());
        $this->assertSame('search_vocabulary_issue', $source->table());
        $this->assertSame('issues', $source->recordTable());
        $this->assertSame(array('subject', 'description'), $source->columns());
        $this->assertSame('projectId', $source->groupColumn());
    }

    public function testATypeWithoutAProjectGroupKeyHasNoGroupColumn()
    {
        $metadata = $this->metadata(array('subject' => 'A'));
        unset($metadata['acl']);

        $this->assertNull(VocabularySource::fromMetadata($metadata)->groupColumn());
    }

    public function testTheSignatureChangesWithTheFtsFields()
    {
        $both = VocabularySource::fromMetadata($this->metadata(array('subject' => 'A', 'description' => 'B')));
        $reweighted = VocabularySource::fromMetadata($this->metadata(array('subject' => 'B', 'description' => 'A')));
        $subjectOnly = VocabularySource::fromMetadata($this->metadata(array('subject' => 'A')));

        $this->assertSame($both->signature(), $reweighted->signature());
        $this->assertNotSame($both->signature(), $subjectOnly->signature());
    }

    public function testAnInvalidTypeHasNoTable()
    {
        $this->expectException(\Gaia\Exception\Exception::class);

        VocabularySource::tableFor('issue"; DROP TABLE issues; --');
    }

    /**
     * Metadata of an Issue-like model, as SearchBlock::apply() returns it.
     *
     * @param array $fts
     * @return array
     */
    private function metadata(array $fts)
    {
        return array(
            'tableName' => 'issues',
            'search' => array('type' => 'issue', 'fts' => $fts),
            'fields' => array(
                'subject' => array('name' => 'subject', 'type' => 'varchar'),
                'description' => array('name' => 'description', 'type' => 'text'),
                'projectId' => array('name' => 'projectId', 'type' => 'varchar'),
            ),
            'acl' => array('groups' => array('Project'), 'groupKeys' => array('Project' => 'projectId')),
        );
    }
}
