<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Security;

use Gaia\Core\MVC\Models\Query;
use Gaia\Core\MVC\Models\Query\WhereFieldValidator;
use Gaia\Exception\BadRequest;
use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Security\Acl;
use Gaia\Libraries\Security\AclMapCatalog;
use Gaia\MVC\Models\Behaviors\auditBehavior;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for fields flagged `'system' => true` in model metadata (D-071): they are never selected, serialized,
 * written from a request body, usable in a query or sort, audited or listed in the permission catalog. The fixture
 * model Agaia4653Widget has one ordinary field (`name`) and two system fields, one of them Id-suffixed so it would
 * otherwise count as structural.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class SystemFieldTest extends TestCase
{
    const MODEL = 'Agaia4653Widget';

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
                return [SystemFieldTest::MODEL => SystemFieldTest::metadata()];
            }
        });
        $this->di->setShared('metaManager', new Manager($this->di));
    }

    /**
     * The fixture model's metadata.
     *
     * @return array
     */
    public static function metadata()
    {
        return [
            'tableName' => 'agaia4653widgets',
            'fields' => [
                'id' => ['type' => 'char', 'null' => false],
                'name' => ['type' => 'varchar', 'null' => true],
                'searchText' => ['type' => 'text', 'null' => true, 'system' => true],
                'embeddingModelId' => ['type' => 'varchar', 'null' => true, 'system' => true],
            ],
            'indexes' => ['id' => 'primary'],
            'relationships' => [
                'hasOne' => [
                    'parent' => [
                        'primaryKey' => 'id',
                        'relatedKey' => 'id',
                        'relatedModel' => '\\Gaia\\MVC\\Models\\' . self::MODEL,
                    ],
                ],
            ],
        ];
    }

    public function testMetaManagerListsTheSystemFields()
    {
        $metaManager = $this->di->get('metaManager');

        $this->assertSame(['searchText', 'embeddingModelId'], $metaManager->getSystemFields(self::MODEL));
        $this->assertTrue($metaManager->isSystemField(self::MODEL, 'searchText'));
        $this->assertFalse($metaManager->isSystemField(self::MODEL, 'name'));
        $this->assertSame([], Manager::systemFieldsOf([]));
    }

    public function testOnlyALiteralTrueFlagsASystemField()
    {
        $this->assertSame(['flagged'], Manager::systemFieldsOf(['fields' => [
            'flagged' => ['system' => true],
            'quoted' => ['system' => 'false'],
            'numeric' => ['system' => 1],
            'plain' => ['type' => 'varchar'],
        ]]));
    }

    public function testAnUnreadableMetadataDeniesTheField()
    {
        $this->di->setShared('metaManager', new class {
            public function isSystemField($modelName, $fieldName)
            {
                throw new \RuntimeException('metadata unavailable');
            }
        });

        $this->assertFalse($this->acl()->isFieldActionAllowed(self::MODEL, 'name', 'get'));
    }

    public function testNoActionIsAllowedOnASystemField()
    {
        $acl = $this->acl();

        foreach (['get', 'create', 'update'] as $action) {
            $this->assertTrue($acl->isFieldActionAllowed(self::MODEL, 'name', $action));
            $this->assertFalse($acl->isFieldActionAllowed(self::MODEL, 'searchText', $action));
            $this->assertFalse($acl->isFieldActionAllowed(self::MODEL, 'embeddingModelId', $action));
        }
    }

    public function testDefaultSelectLeavesSystemFieldsOut()
    {
        $query = new Query($this->di, self::MODEL, null);

        $this->assertSame(
            [self::MODEL . '.id', self::MODEL . '.name'],
            $query->getModelFields()
        );
    }

    public function testRequestedSystemFieldsAreOmittedFromTheSelect()
    {
        $fields = $this->acl()->filterAuthorizedFields(self::MODEL, [
            self::MODEL . '.id',
            self::MODEL . '.name',
            self::MODEL . '.searchText',
            'parent.embeddingModelId AS parent_embeddingModelId',
        ]);

        $this->assertSame([self::MODEL . '.id', self::MODEL . '.name'], $fields);
    }

    public function testSystemFieldsOfTheRecordAndItsIncludeAreNotSerialized()
    {
        $acl = $this->acl();
        $acl->applyACLOnFields(
            [
                'id' => 'w1',
                'name' => 'Widget',
                'searchText' => 'secret',
                'parent' => ['id' => 'w0', 'name' => 'Parent', 'searchText' => 'secret'],
            ],
            self::MODEL,
            []
        );

        $allowed = array_values($acl->getAllowedFields());
        sort($allowed);
        $this->assertSame(['id', 'name', 'parent.id', 'parent.name'], $allowed);
    }

    public function testSystemFieldsInAWriteBodyAreDiscarded()
    {
        $acl = $this->acl();

        foreach (['create', 'update'] as $action) {
            $payload = $acl->authorizeWritableFields(self::MODEL, [
                'id' => 'w1',
                'name' => 'Widget',
                'searchText' => 'forged',
                'embeddingModelId' => 'forged',
            ], $action);

            $this->assertSame(['id' => 'w1', 'name' => 'Widget'], $payload);
        }
    }

    /**
     * @dataProvider systemFieldClauses
     */
    public function testASystemFieldInAClauseIsAnUnknownField(array $clauses)
    {
        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Invalid query: unknown field');

        $this->acl()->authorizeClauseUsage(self::MODEL, 'list', $clauses);
    }

    public function systemFieldClauses()
    {
        return [
            'query' => [['query' => '((' . self::MODEL . '.searchText : x))']],
            'sort' => [['sort' => self::MODEL . '.searchText']],
            'descending sort' => [['sort' => '-' . self::MODEL . '.embeddingModelId']],
            'group' => [['groupBy' => [self::MODEL . '.searchText']]],
        ];
    }

    public function testOrdinaryFieldsInAClauseStillPass()
    {
        $this->acl()->authorizeClauseUsage(self::MODEL, 'list', [
            'query' => '((' . self::MODEL . '.name : x))',
            'sort' => self::MODEL . '.name',
        ]);

        $this->addToAssertionCount(1);
    }

    public function testTheWhereValidatorRejectsASystemField()
    {
        $validator = new WhereFieldValidator($this->di);
        $validator->assertValid(self::MODEL . '.name', self::MODEL);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Invalid query: unknown field "searchText".');

        $validator->assertValid('searchText', self::MODEL);
    }

    public function testSystemFieldsAreNotInThePermissionCatalog()
    {
        $fields = self::metadata()['fields'];

        $this->assertTrue(AclMapCatalog::isFieldCatalogEligible('name', $fields['name'], []));
        $this->assertFalse(AclMapCatalog::isFieldCatalogEligible('searchText', $fields['searchText'], []));
    }

    public function testAuditLeavesSystemFieldsOut()
    {
        $model = new Agaia4653Widget($this->di, ['name', 'searchText']);

        $this->audit($model);

        $this->assertTrue($model->isChanged);
        $this->assertSame(['name' => ['old' => 'Old', 'new' => 'New']], $model->audit);
    }

    public function testAChangeToSystemFieldsOnlyIsNotAudited()
    {
        $model = new Agaia4653Widget($this->di, ['searchText', 'embeddingModelId']);

        $this->audit($model);

        $this->assertNull($model->isChanged);
        $this->assertNull($model->audit);
    }

    /**
     * Run auditBehavior's beforeUpdate on the model.
     *
     * @param  Agaia4653Widget $model
     * @return void
     */
    private function audit($model)
    {
        $method = new \ReflectionMethod(auditBehavior::class, 'beforeUpdate');
        $method->invokeArgs(new auditBehavior(), [&$model]);
    }

    /**
     * An Acl that allows every module action and field grant, so only the system flag can deny.
     *
     * @return Acl
     */
    private function acl()
    {
        $acl = $this->getMockBuilder(Acl::class)
            ->setConstructorArgs([$this->di, Acl::RESOLUTION_PERMISSIVE])
            ->onlyMethods(['isModelActionAllowed', 'isResourceAllowed'])
            ->getMock();
        $acl->method('isModelActionAllowed')->willReturn(true);
        $acl->method('isResourceAllowed')->willReturn(true);

        return $acl;
    }
}

/**
 * Fixture model for auditBehavior: the given fields changed from 'Old' to 'New'.
 */
class Agaia4653Widget
{
    public $isChanged;
    public $audit;
    public $name = 'New';
    public $searchText = 'New';
    public $embeddingModelId = 'New';
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
        return ['name' => 'Old', 'searchText' => 'Old', 'embeddingModelId' => 'Old'];
    }
}
