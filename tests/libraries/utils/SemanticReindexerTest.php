<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Utils;

use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Search\SearchableRegistry;
use Gaia\Libraries\Search\SemanticRecords;
use Gaia\Libraries\Search\SemanticReindexer;
use Gaia\Libraries\Search\SemanticText;
use Gaia\MVC\REST\Controllers\Components\Events\SearchLiveEvents;
use Gaia\MVC\REST\Controllers\Components\Events\Support\HermesPublisher;
use PHPUnit\Framework\TestCase;

/**
 * The search reindex (agaia-4658) on in-memory records: it backfills and corrects the stored semantic text, then
 * publishes only the records whose embedding is missing or was made from another text, pausing after each.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SemanticReindexerTest extends TestCase
{
    const MODEL = 'Agaia4658Widget';
    const PLAIN_MODEL = 'Agaia4658Plain';

    /**
     * @var Manager
     */
    private $metaManager;

    /**
     * The envelopes the fake publisher received.
     *
     * @var array
     */
    private $posted = [];

    /**
     * Pauses taken between events.
     *
     * @var int
     */
    private $pauses = 0;

    /**
     * Whether the fake Hermes refuses every envelope.
     *
     * @var bool
     */
    private $hermesDown = false;

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
                $metadata = [
                    'tableName' => 'agaia4658widgets',
                    'fields' => [
                        'id' => ['name' => 'id', 'type' => 'char', 'length' => 36, 'null' => false],
                        'subject' => ['name' => 'subject', 'type' => 'varchar', 'length' => 255, 'null' => true],
                        'description' => ['name' => 'description', 'type' => 'text', 'null' => true],
                    ],
                    'search' => [
                        'type' => 'widget',
                        'fts' => ['subject' => 'A'],
                        'semantic' => ['subject', 'description'],
                        'display' => ['title' => 'subject'],
                    ],
                ];
                if ($model === SemanticReindexerTest::PLAIN_MODEL) {
                    $metadata['tableName'] = 'agaia4658plains';
                    $metadata['search']['type'] = 'plain';
                    unset($metadata['search']['semantic']);
                }

                return [$model => $metadata];
            }
        });
        $this->metaManager = new Manager($di);
        $this->posted = [];
        $this->pauses = 0;
    }

    public function testItBackfillsCorrectsAndPublishesOnlyStaleRecords()
    {
        $current = SemanticText::hashOf("Current\nBody");
        $records = $this->records([
            // No text yet (an existing row): backfilled, and never embedded, so published
            ['id' => 'a', 'subject' => 'Missing', 'description' => null, 'searchDesiredHash' => null,
                'searchActualHash' => null],
            // Up to date and embedded: left alone
            ['id' => 'b', 'subject' => 'Current', 'description' => 'Body', 'searchDesiredHash' => $current,
                'searchActualHash' => $current],
            // Text stored but the embedding is of an older text: published, not rewritten
            ['id' => 'c', 'subject' => 'Current', 'description' => 'Body', 'searchDesiredHash' => $current,
                'searchActualHash' => 'older'],
            // The text rule changed since the hash was stored: corrected, and its embedding is stale
            ['id' => 'd', 'subject' => 'Current', 'description' => '<p>Body</p>', 'searchDesiredHash' => 'old-rule',
                'searchActualHash' => 'old-rule'],
        ]);

        $counts = $this->reindexer($records)->run();

        $this->assertSame(['widget' => ['records' => 4, 'updated' => 2, 'skipped' => 0, 'published' => 3, 'failed' => 0]], $counts);
        $this->assertSame([
            'a' => ['Missing', SemanticText::hashOf('Missing')],
            'd' => ["Current\nBody", $current],
        ], $records->stored);
        $this->assertSame(['a', 'c', 'd'], array_column(array_column($this->posted, 'changes'), 'recordId'));
        $this->assertSame('service:ares', $this->posted[0]['projectId']);
        $this->assertSame(['type' => 'widget', 'resource' => 'agaia4658widget', 'recordId' => 'a'], $this->posted[0]['changes']);
        $this->assertSame(3, $this->pauses);
    }

    public function testAnEventHermesDidNotTakeCountsAsFailed()
    {
        $records = $this->records([
            ['id' => 'a', 'subject' => 'Missing', 'description' => null, 'searchDesiredHash' => null,
                'searchActualHash' => null],
        ]);
        $this->hermesDown = true;

        $counts = $this->reindexer($records)->run('widget');

        // The text is still stored; only the announcement is lost, so the run reports a failure
        $this->assertSame(['records' => 1, 'updated' => 1, 'skipped' => 0, 'published' => 0, 'failed' => 1],
            $counts['widget']);
        $this->assertSame([], $this->posted);
    }

    public function testARecordSavedSinceThePageWasReadIsLeftToTheSave()
    {
        $records = $this->records([
            ['id' => 'a', 'subject' => 'Read text', 'description' => null, 'searchDesiredHash' => null,
                'searchActualHash' => null],
        ]);
        $records->savedMeanwhile = ['a'];

        $counts = $this->reindexer($records)->run('widget');

        $this->assertSame(['records' => 1, 'updated' => 0, 'skipped' => 1, 'published' => 0, 'failed' => 0],
            $counts['widget']);
        $this->assertSame([], $records->stored);
        $this->assertSame([], $this->posted);
    }

    public function testItPagesThroughEveryRecord()
    {
        $rows = [];
        for ($i = 0; $i < SemanticReindexer::PAGE_SIZE + 5; $i++) {
            $rows[] = ['id' => sprintf('r%04d', $i), 'subject' => "S{$i}", 'description' => null,
                'searchDesiredHash' => null, 'searchActualHash' => null];
        }
        $records = $this->records($rows);

        $counts = $this->reindexer($records)->run('widget');

        $this->assertSame(SemanticReindexer::PAGE_SIZE + 5, $counts['widget']['updated']);
        // The short second page is the last one
        $this->assertSame(['', 'r0199'], $records->afterIds);
    }

    public function testATypeWithoutSemanticFieldsOrAnUnknownTypeIsRefused()
    {
        $this->assertSame([], $this->reindexer($this->records([]), [self::PLAIN_MODEL])->run());

        $this->expectException(\Gaia\Exception\Exception::class);
        $this->expectExceptionMessage("Search type 'plain' is unknown or has no semantic fields.");
        $this->reindexer($this->records([]), [self::PLAIN_MODEL])->run('plain');
    }

    /**
     * @param SemanticRecords $records
     * @param array $models
     * @return SemanticReindexer
     */
    private function reindexer(SemanticRecords $records, array $models = [self::MODEL, self::PLAIN_MODEL])
    {
        return new SemanticReindexer(
            new SearchableRegistry($this->metaManager, $models),
            $this->metaManager,
            $records,
            new SearchLiveEvents(new HermesPublisher(function ($envelope) {
                if ($this->hermesDown) {
                    throw new \RuntimeException('Hermes down');
                }
                $this->posted[] = $envelope;
            })),
            function () {
                $this->pauses++;
            }
        );
    }

    /**
     * In-memory records of the widget table, sorted by id; stored holds the texts the reindex wrote.
     *
     * @param array $rows
     * @return SemanticRecords
     */
    private function records(array $rows)
    {
        return new class ($rows) extends SemanticRecords {
            public $rows;
            public $stored = [];
            public $afterIds = [];

            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            public function page($table, array $fields, $afterId, $limit)
            {
                $this->afterIds[] = $afterId;
                $rows = array_filter($this->rows, function ($row) use ($afterId) {
                    return strcmp($row['id'], $afterId) > 0;
                });

                return array_slice(array_values($rows), 0, $limit);
            }

            public $savedMeanwhile = [];

            public function store($table, $id, $text, $hash, $readHash)
            {
                if (in_array($id, $this->savedMeanwhile, true)) {
                    return false;
                }
                $this->stored[$id] = [$text, $hash];

                return true;
            }
        };
    }
}
