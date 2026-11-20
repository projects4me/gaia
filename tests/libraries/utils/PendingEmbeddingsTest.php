<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Search\PendingEmbeddings;
use Gaia\Libraries\Search\PendingRequest;
use Gaia\Libraries\Search\SearchableRegistry;
use Gaia\Libraries\Search\SemanticRecords;
use PHPUnit\Framework\TestCase;

/**
 * The pending list (agaia-4764) on in-memory tables: pages are ordered by type then id across every type with
 * semantic fields and continue from a `<type>:<id>` cursor; the cursor and the limit are checked.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PendingEmbeddingsTest extends TestCase
{
    const WIDGET = 'Agaia4764Widget';
    const ARTICLE = 'Agaia4764Article';
    const PLAIN = 'Agaia4764Plain';

    /**
     * @var Manager
     */
    private $metaManager;

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }

        $di = new \Phalcon\Di\FactoryDefault();
        $di->set('fileHandler', new class {
            public function readFile($path)
            {
                $model = basename($path, '.php');
                $names = [
                    PendingEmbeddingsTest::WIDGET => ['widgets', 'widget'],
                    PendingEmbeddingsTest::ARTICLE => ['articles', 'article'],
                    PendingEmbeddingsTest::PLAIN => ['plains', 'plain'],
                ];
                $metadata = [
                    'tableName' => $names[$model][0],
                    'fields' => [
                        'id' => ['name' => 'id', 'type' => 'char', 'length' => 36, 'null' => false],
                        'subject' => ['name' => 'subject', 'type' => 'varchar', 'length' => 255, 'null' => true],
                    ],
                    'search' => [
                        'type' => $names[$model][1],
                        'fts' => ['subject' => 'A'],
                        'semantic' => ['subject'],
                        'display' => ['title' => 'subject'],
                    ],
                ];
                if ($model === PendingEmbeddingsTest::PLAIN) {
                    unset($metadata['search']['semantic']);
                }

                return [$model => $metadata];
            }
        });
        $this->metaManager = new Manager($di);
    }

    public function testAPageListsTypesInOrderThenIdsAndSkipsTypesWithoutSemanticFields()
    {
        $records = $this->records(['widgets' => ['w1', 'w2'], 'articles' => ['a1'], 'plains' => ['p1']]);

        $page = $this->pending($records)->page(new PendingRequest());

        $this->assertSame([
            ['type' => 'article', 'resource' => 'agaia4764article', 'id' => 'a1'],
            ['type' => 'widget', 'resource' => 'agaia4764widget', 'id' => 'w1'],
            ['type' => 'widget', 'resource' => 'agaia4764widget', 'id' => 'w2'],
        ], $page['data']);
        $this->assertNull($page['next']);
        $this->assertNotContains('plains', array_column($records->calls, 0));
    }

    public function testTheCursorContinuesAcrossTypes()
    {
        $records = $this->records(['widgets' => ['w1', 'w2', 'w3'], 'articles' => ['a1', 'a2']]);
        $pending = $this->pending($records);

        $first = $pending->page(new PendingRequest(null, '3'));
        $second = $pending->page(new PendingRequest($first['next'], '3'));

        $this->assertSame(['a1', 'a2', 'w1'], array_column($first['data'], 'id'));
        $this->assertSame('widget:w1', $first['next']);
        $this->assertSame(['w2', 'w3'], array_column($second['data'], 'id'));
        $this->assertNull($second['next']);
        // The second page reads the article table not at all, and the widgets after w1, with what is left to fill
        $this->assertSame([['widgets', 'w1', 3]], array_slice($records->calls, 2));
    }

    public function testAFullLastPageStillGivesACursorAndTheNextPageIsEmpty()
    {
        $pending = $this->pending($this->records(['widgets' => ['w1', 'w2']]));

        $first = $pending->page(new PendingRequest(null, 2));
        $second = $pending->page(new PendingRequest($first['next'], 2));

        $this->assertSame('widget:w2', $first['next']);
        $this->assertSame(['data' => [], 'next' => null], $second);
    }

    public function testACursorOfATypeWithoutSemanticFieldsIsRefused()
    {
        $this->expectException(\Gaia\Exception\BadRequest::class);
        $this->pending($this->records([]))->page(new PendingRequest('plain:p1'));
    }

    public function testTheLimitDefaultsAndIsCapped()
    {
        $this->assertSame(PendingRequest::DEFAULT_LIMIT, (new PendingRequest())->limit());
        $this->assertSame(PendingRequest::DEFAULT_LIMIT, (new PendingRequest('', ''))->limit());
        $this->assertSame(7, (new PendingRequest(null, '7'))->limit());
        $this->assertSame(PendingRequest::MAX_LIMIT, (new PendingRequest(null, '100000'))->limit());
    }

    public function testACursorIsSplitIntoTypeAndId()
    {
        $request = new PendingRequest('widget:0b1c-22');

        $this->assertSame('widget', $request->afterType());
        $this->assertSame('0b1c-22', $request->afterId());
        $this->assertNull((new PendingRequest())->afterType());
        // Only the first colon splits: an id may hold colons and spaces, as imported ids might
        $this->assertSame('a:b c', (new PendingRequest('widget:a:b c'))->afterId());
        $this->assertSame(str_repeat('x', 255), (new PendingRequest('widget:' . str_repeat('x', 255)))->afterId());
    }

    /**
     * @dataProvider badRequests
     */
    public function testAMalformedCursorOrLimitIsRefused($after, $limit)
    {
        $this->expectException(\Gaia\Exception\BadRequest::class);
        new PendingRequest($after, $limit);
    }

    public static function badRequests()
    {
        return [
            'no colon' => ['widget', null],
            'empty id' => ['widget:', null],
            'id too long' => ['widget:' . str_repeat('x', 256), null],
            'tab in id' => ["widget:a\tb", null],
            'newline after id' => ["widget:a\n", null],
            'type not a name' => ['1widget:a', null],
            'cursor array' => [['widget:a'], null],
            'zero limit' => [null, '0'],
            'negative limit' => [null, '-1'],
            'decimal limit' => [null, '1.5'],
            'word limit' => [null, 'all'],
            'limit array' => [null, ['5']],
        ];
    }

    /**
     * @param SemanticRecords $records
     * @return PendingEmbeddings
     */
    private function pending(SemanticRecords $records)
    {
        return new PendingEmbeddings(
            new SearchableRegistry($this->metaManager, [self::WIDGET, self::ARTICLE, self::PLAIN]),
            $this->metaManager,
            $records
        );
    }

    /**
     * In-memory pending ids per table, each list sorted; calls records every query as [table, afterId, limit].
     *
     * @param array $tables
     * @return SemanticRecords
     */
    private function records(array $tables)
    {
        return new class ($tables) extends SemanticRecords {
            public $tables;
            public $calls = [];

            public function __construct(array $tables)
            {
                $this->tables = $tables;
            }

            public function pending($table, $afterId, $limit)
            {
                $this->calls[] = [$table, $afterId, $limit];
                $ids = array_filter($this->tables[$table] ?? [], function ($id) use ($afterId) {
                    return strcmp($id, $afterId) > 0;
                });

                return array_slice(array_values($ids), 0, $limit);
            }
        };
    }
}
