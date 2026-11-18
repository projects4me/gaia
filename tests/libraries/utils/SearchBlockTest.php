<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Core\MVC\Models\Query;
use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Search\SearchableRegistry;
use Gaia\Libraries\Search\SearchBlock;
use Gaia\MVC\Models\Behaviors\auditBehavior;
use Phalcon\Mvc\Model\MetaData;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the search block of model metadata: its validation, the system columns and indexes it adds, and
 * the registry that looks blocks up by type and by model.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SearchBlockTest extends TestCase
{
    const MODEL = 'Agaia4655Widget';
    const PLAIN_MODEL = 'Agaia4655Plain';
    const TWIN_MODEL = 'Agaia4655Twin';

    /**
     * Columns a block with semantic fields adds.
     *
     * @var array
     */
    const SEARCH_COLUMNS = [
        'searchVector',
        'searchSemanticText',
        'searchSemanticHash',
        'searchEmbedding',
        'searchEmbeddingHash',
        'searchEmbeddedAt',
    ];

    /**
     * @var \Phalcon\Di\FactoryDefault
     */
    private $di;

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }

        $this->di = new \Phalcon\Di\FactoryDefault();
        $this->di->set('fileHandler', new class {
            public function readFile($path)
            {
                $model = basename($path, '.php');
                $metadata = SearchBlockTest::metadata();
                if ($model === SearchBlockTest::PLAIN_MODEL) {
                    unset($metadata['search']);
                }

                return [$model => $metadata];
            }
        });
        $this->di->setShared('metaManager', new Manager($this->di));
    }

    /**
     * The fixture model's metadata, with a search block.
     *
     * @return array
     */
    public static function metadata()
    {
        return [
            'tableName' => 'agaia4655widgets',
            'fields' => [
                'id' => ['name' => 'id', 'type' => 'char', 'length' => 36, 'null' => false],
                'subject' => ['name' => 'subject', 'type' => 'varchar', 'length' => 255, 'null' => true],
                'description' => ['name' => 'description', 'type' => 'text', 'null' => true],
                'number' => ['name' => 'number', 'type' => 'int', 'null' => true],
                'internal' => ['name' => 'internal', 'type' => 'text', 'null' => true, 'system' => true],
            ],
            'indexes' => ['id' => 'primary', 'number' => 'unique'],
            'search' => [
                'type' => 'widget',
                'fts' => ['subject' => 'A', 'description' => 'B'],
                'fuzzy' => ['subject'],
                'semantic' => ['subject', 'description'],
                'exact' => ['number'],
                'display' => ['title' => 'subject', 'snippet' => 'description'],
            ],
        ];
    }

    public function testMetadataWithoutASearchBlockIsUnchanged()
    {
        $metadata = self::metadata();
        unset($metadata['search']);

        $this->assertSame($metadata, SearchBlock::apply(self::MODEL, $metadata));
    }

    public function testTheBlockAddsSystemColumnsAndTheirIndexes()
    {
        $metadata = SearchBlock::apply(self::MODEL, self::metadata());

        $this->assertSame(
            array_merge(['id', 'subject', 'description', 'number', 'internal'], self::SEARCH_COLUMNS),
            array_keys($metadata['fields'])
        );
        foreach (self::SEARCH_COLUMNS as $column) {
            $this->assertTrue($metadata['fields'][$column]['system'], "{$column} is a system field");
            $this->assertTrue($metadata['fields'][$column]['null'], "{$column} is nullable");
            $this->assertSame($column, $metadata['fields'][$column]['name']);
        }
        $this->assertSame(
            ['type' => 'tsvector', 'generated' => ['sources' => ['subject' => 'A', 'description' => 'B']]],
            array_intersect_key($metadata['fields']['searchVector'], ['type' => 1, 'generated' => 1])
        );
        $this->assertSame('text', $metadata['fields']['searchSemanticText']['type']);
        $this->assertSame(['varchar', '64'], [
            $metadata['fields']['searchSemanticHash']['type'],
            $metadata['fields']['searchSemanticHash']['length'],
        ]);
        $this->assertSame(['vector', 768], [
            $metadata['fields']['searchEmbedding']['type'],
            $metadata['fields']['searchEmbedding']['dimensions'],
        ]);
        $this->assertSame('64', $metadata['fields']['searchEmbeddingHash']['length']);
        $this->assertSame('datetime', $metadata['fields']['searchEmbeddedAt']['type']);
        $this->assertSame(
            [
                'id' => 'primary',
                'number' => 'unique',
                'searchVector' => 'gin',
                'searchEmbedding' => 'hnsw_cosine',
                'subject' => 'gin_trgm',
            ],
            $metadata['indexes']
        );
    }

    public function testWithoutSemanticFieldsOnlyTheTsvectorIsAdded()
    {
        $metadata = self::metadata();
        unset($metadata['search']['semantic'], $metadata['search']['fuzzy']);

        $metadata = SearchBlock::apply(self::MODEL, $metadata);

        $this->assertSame(
            ['id', 'subject', 'description', 'number', 'internal', 'searchVector'],
            array_keys($metadata['fields'])
        );
        $this->assertSame(['id' => 'primary', 'number' => 'unique', 'searchVector' => 'gin'], $metadata['indexes']);
        $this->assertSame([], $metadata['search']['semantic']);
        $this->assertSame([], $metadata['search']['fuzzy']);
        $this->assertSame(['number'], $metadata['search']['exact']);
    }

    public function testTheBlockIsNormalized()
    {
        $metadata = self::metadata();
        unset($metadata['search']['display']['snippet'], $metadata['search']['exact']);

        $block = SearchBlock::apply(self::MODEL, $metadata)['search'];

        $this->assertSame([
            'type' => 'widget',
            'fts' => ['subject' => 'A', 'description' => 'B'],
            'fuzzy' => ['subject'],
            'semantic' => ['subject', 'description'],
            'exact' => [],
            'display' => ['title' => 'subject', 'snippet' => null],
        ], $block);
    }

    public function testANullSnippetIsAccepted()
    {
        $metadata = self::metadata();
        $metadata['search']['display']['snippet'] = null;

        $block = SearchBlock::apply(self::MODEL, $metadata)['search'];

        $this->assertSame(['title' => 'subject', 'snippet' => null], $block['display']);
    }

    /**
     * @dataProvider invalidBlocks
     */
    public function testAnInvalidBlockFailsWithAClearMessage(callable $break, $message)
    {
        $metadata = self::metadata();
        $break($metadata);

        $this->expectException(\Gaia\Exception\Exception::class);
        $this->expectExceptionMessage('Search block of model ' . self::MODEL . ' ' . $message);

        SearchBlock::apply(self::MODEL, $metadata);
    }

    public function invalidBlocks()
    {
        return [
            'not an array' => [function (&$m) {
                $m['search'] = 'widget';
            }, 'needs an array'],
            'unknown key' => [function (&$m) {
                $m['search']['boost'] = 2;
            }, "has unknown key 'boost'"],
            'missing type' => [function (&$m) {
                unset($m['search']['type']);
            }, "needs a 'type'"],
            'upper case type' => [function (&$m) {
                $m['search']['type'] = 'Widget';
            }, "needs a 'type'"],
            'missing fts' => [function (&$m) {
                unset($m['search']['fts']);
            }, "needs 'fts' => [field => weight]"],
            'fts as a list' => [function (&$m) {
                $m['search']['fts'] = ['subject'];
            }, "needs 'fts' => [field => weight]"],
            'fts unknown field' => [function (&$m) {
                $m['search']['fts']['title'] = 'A';
            }, "names unknown field 'title' in fts"],
            'fts bad weight' => [function (&$m) {
                $m['search']['fts']['subject'] = 'E';
            }, "gives fts field 'subject' weight 'E'; use A, B, C or D"],
            'fts lower case weight' => [function (&$m) {
                $m['search']['fts']['subject'] = 'a';
            }, "gives fts field 'subject' weight 'a'"],
            'fts non-text field' => [function (&$m) {
                $m['search']['fts']['number'] = 'C';
            }, "names 'number' of type 'int' in fts; only text fields"],
            'fts system field' => [function (&$m) {
                $m['search']['fts']['internal'] = 'C';
            }, "names system field 'internal' in fts"],
            'fuzzy unknown field' => [function (&$m) {
                $m['search']['fuzzy'] = ['title'];
            }, "names unknown field 'title' in fuzzy"],
            'fuzzy non-text field' => [function (&$m) {
                $m['search']['fuzzy'] = ['number'];
            }, "names 'number' of type 'int' in fuzzy"],
            'fuzzy not a list' => [function (&$m) {
                $m['search']['fuzzy'] = 'subject';
            }, "needs 'fuzzy' as a list of fields"],
            'fuzzy on an indexed field' => [function (&$m) {
                $m['search']['fuzzy'] = ['number'];
                $m['fields']['number']['type'] = 'varchar';
            }, "fuzzy field 'number' already has a 'unique' index"],
            'semantic keyed' => [function (&$m) {
                $m['search']['semantic'] = ['subject' => 1];
            }, "needs 'semantic' as a list of fields"],
            'semantic unknown field' => [function (&$m) {
                $m['search']['semantic'][] = 'body';
            }, "names unknown field 'body' in semantic"],
            'semantic twice' => [function (&$m) {
                $m['search']['semantic'][] = 'subject';
            }, "lists a field twice in 'semantic'"],
            'exact mixed-type entries' => [function (&$m) {
                $m['search']['exact'] = ['0', 0];
            }, "names unknown field '0' in exact"],
            'exact unknown field' => [function (&$m) {
                $m['search']['exact'] = ['code'];
            }, "names unknown field 'code' in exact"],
            'missing display' => [function (&$m) {
                unset($m['search']['display']);
            }, "needs 'display' => ['title' => field"],
            'display without title' => [function (&$m) {
                unset($m['search']['display']['title']);
            }, "needs 'display' => ['title' => field"],
            'display unknown key' => [function (&$m) {
                $m['search']['display']['icon'] = 'subject';
            }, "has unknown display key 'icon'"],
            'display unknown field' => [function (&$m) {
                $m['search']['display']['snippet'] = 'body';
            }, "names unknown field 'body' in display snippet"],
            'declared search column' => [function (&$m) {
                $m['fields']['searchVector'] = ['name' => 'searchVector', 'type' => 'text', 'null' => true];
            }, "declares field 'searchVector', which its search block adds"],
        ];
    }

    public function testTheMetaManagerSeesTheSearchColumnsAsSystemFields()
    {
        $manager = $this->di->get('metaManager');
        $meta = $manager->getModelMeta(self::MODEL);

        $this->assertSame(
            array_merge(['internal'], self::SEARCH_COLUMNS),
            $manager->getSystemFields(self::MODEL)
        );
        foreach (self::SEARCH_COLUMNS as $column) {
            $this->assertContains($column, $meta[MetaData::MODELS_ATTRIBUTES]);
        }
        $this->assertSame('widget', $meta['search']['type']);
        // The generated tsvector is never written by an INSERT or UPDATE.
        $this->assertSame(['searchVector' => true], $meta[MetaData::MODELS_AUTOMATIC_DEFAULT_INSERT]);
        $this->assertSame(['searchVector' => true], $meta[MetaData::MODELS_AUTOMATIC_DEFAULT_UPDATE]);
    }

    public function testAModelWithoutABlockHasNoSearchEntry()
    {
        $meta = $this->di->get('metaManager')->getModelMeta(self::PLAIN_MODEL);

        $this->assertNull($meta['search']);
        $this->assertSame([], $meta[MetaData::MODELS_AUTOMATIC_DEFAULT_INSERT]);
    }

    public function testTheDefaultSelectLeavesTheSearchColumnsOut()
    {
        $query = new Query($this->di, self::MODEL, null);

        $this->assertSame(
            [self::MODEL . '.id', self::MODEL . '.subject', self::MODEL . '.description', self::MODEL . '.number'],
            $query->getModelFields()
        );
    }

    public function testAChangeToSearchColumnsOnlyIsNotAudited()
    {
        $model = new Agaia4655Widget($this->di, self::SEARCH_COLUMNS);

        $method = new \ReflectionMethod(auditBehavior::class, 'beforeUpdate');
        $method->setAccessible(true);
        $method->invokeArgs(new auditBehavior(), [&$model]);

        $this->assertNull($model->isChanged);
        $this->assertNull($model->audit);
    }

    public function testTheRegistryFindsABlockByTypeAndByModel()
    {
        $registry = new SearchableRegistry($this->di->get('metaManager'), [self::PLAIN_MODEL, self::MODEL]);

        $this->assertSame(['widget'], $registry->types());
        $this->assertSame(self::MODEL, $registry->forType('widget')['model']);
        $this->assertSame(['subject', 'description'], $registry->forType('widget')['semantic']);
        $this->assertSame($registry->forType('widget'), $registry->forModel(self::MODEL));
        $this->assertNull($registry->forType('issue'));
        $this->assertNull($registry->forModel(self::PLAIN_MODEL));
    }

    public function testTheRegistryRejectsATypeUsedByTwoModels()
    {
        $registry = new SearchableRegistry($this->di->get('metaManager'), [self::MODEL, self::TWIN_MODEL]);

        $this->expectException(\Gaia\Exception\Exception::class);
        $this->expectExceptionMessage(
            "Search type 'widget' is used by models " . self::MODEL . ' and ' . self::TWIN_MODEL . '; it must be unique.'
        );

        $registry->types();
    }
}

/**
 * Fixture model for auditBehavior: the given fields changed from 'Old' to 'New'.
 */
class Agaia4655Widget
{
    public $isChanged;
    public $audit;
    public $searchVector = 'New';
    public $searchSemanticText = 'New';
    public $searchSemanticHash = 'New';
    public $searchEmbedding = 'New';
    public $searchEmbeddingHash = 'New';
    public $searchEmbeddedAt = 'New';
    private $di;
    private $changedFields;

    public function __construct($di, array $changedFields)
    {
        $this->di = $di;
        $this->changedFields = $changedFields;
    }

    public function getDI()
    {
        return $this->di;
    }

    public function getChangedFields()
    {
        return $this->changedFields;
    }

    public function getSnapshotData()
    {
        return array_fill_keys(SearchBlockTest::SEARCH_COLUMNS, 'Old');
    }
}
