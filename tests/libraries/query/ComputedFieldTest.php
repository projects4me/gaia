<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Libraries\Query;

use Gaia\Core\MVC\Models\Query\ComputedColumns;
use Gaia\Libraries\Meta\Manager;
use Gaia\Libraries\Security\Acl;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for computed fields (metadata key `computed`, Workspaces D-013): read-only values the read query
 * selects as PHQL expressions. They are not columns, are read with the record and are never written.
 *
 * @author Agent Odysseus <agent.odysseus@projects4.me>
 * @package Gaia\Tests
 * @category Tests
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ComputedFieldTest extends TestCase
{
    const MODEL = 'Agaia4801Box';

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
                return [ComputedFieldTest::MODEL => ComputedFieldTest::metadata()];
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
            'tableName' => 'agaia4801boxes',
            'fields' => [
                'id' => ['type' => 'char', 'null' => false],
                'name' => ['type' => 'varchar', 'null' => true],
            ],
            'computed' => self::computed(),
            'indexes' => ['id' => 'primary'],
        ];
    }

    /**
     * @return array
     */
    public static function computed()
    {
        return [
            'itemCount' => ['type' => 'int', 'expression' => 'SELECT COUNT(I.id) FROM Item I WHERE I.boxId = ' . self::MODEL . '.id'],
            'ownerCount' => ['type' => 'int', 'expression' => 'SELECT COUNT(DISTINCT I.ownerId) FROM Item I'],
        ];
    }

    public function testMetaManagerListsTheComputedFieldsButNotAsColumns()
    {
        $metaManager = $this->di->get('metaManager');

        $this->assertSame(['itemCount', 'ownerCount'], array_keys($metaManager->getComputedFields(self::MODEL)));
        $this->assertTrue($metaManager->isComputedField(self::MODEL, 'itemCount'));
        $this->assertFalse($metaManager->isComputedField(self::MODEL, 'name'));
        $this->assertSame(['id', 'name'], $metaManager->getModelMeta(self::MODEL)[\Phalcon\Mvc\Model\MetaData::MODELS_ATTRIBUTES]);
    }

    public function testColumnsSelectTheExpressionsUnderTheirNames()
    {
        $this->assertSame(
            ['(SELECT COUNT(I.id) FROM Item I WHERE I.boxId = ' . self::MODEL . '.id) AS itemCount'],
            $this->columns()->columns(['itemCount', 'unknown'])
        );
        $this->assertSame(['itemCount', 'ownerCount'], $this->columns()->names());
    }

    public function testExtractTakesComputedFieldsOutOfTheRequestedFields()
    {
        list($fields, $names) = $this->columns()->extract([
            self::MODEL . '.id',
            self::MODEL . '.ownerCount',
            'itemCount',
            'Other.itemCount',
            self::MODEL . '.itemCount',
        ]);

        $this->assertSame([self::MODEL . '.id', 'Other.itemCount'], $fields);
        $this->assertSame(['ownerCount', 'itemCount'], $names);
    }

    public function testASortOnAComputedFieldOrdersByItsAlias()
    {
        $columns = $this->columns();

        $this->assertSame(['itemCount'], $columns->namedInOrderBy(self::MODEL . '.itemCount DESC'));
        $this->assertSame(['itemCount'], $columns->namedInOrderBy('itemCount'));
        $this->assertSame([], $columns->namedInOrderBy(self::MODEL . '.name ASC'));
        $this->assertSame([], $columns->namedInOrderBy(null));

        $this->assertSame('itemCount DESC', $columns->rewriteOrderBy(self::MODEL . '.itemCount DESC'));
        $this->assertSame('itemCount', $columns->rewriteOrderBy('itemCount'));
        $this->assertSame(self::MODEL . '.name ASC', $columns->rewriteOrderBy(self::MODEL . '.name ASC'));
        $this->assertNull($columns->rewriteOrderBy(null));
    }

    public function testASortOnAComputedFieldThatIsNotSelectedIsDropped()
    {
        $columns = $this->columns();

        $this->assertSame(
            self::MODEL . '.name ASC',
            $columns->rewriteOrderBy(self::MODEL . '.itemCount DESC, ' . self::MODEL . '.name ASC', [])
        );
        $this->assertNull($columns->rewriteOrderBy(self::MODEL . '.itemCount DESC', ['ownerCount']));
        $this->assertSame('itemCount DESC', $columns->rewriteOrderBy(self::MODEL . '.itemCount DESC', ['itemCount']));
    }

    public function testAComputedFieldIsReadWithTheRecordWhateverTheFieldGrants()
    {
        $acl = $this->acl(true);

        $this->assertTrue($acl->isFieldActionAllowed(self::MODEL, 'itemCount', 'get'));
        $this->assertFalse($acl->isFieldActionAllowed(self::MODEL, 'itemCount', 'create'));
        $this->assertFalse($acl->isFieldActionAllowed(self::MODEL, 'itemCount', 'update'));
        // An ordinary field still follows its (here missing, restrictive) grant.
        $this->assertFalse($acl->isFieldActionAllowed(self::MODEL, 'name', 'get'));
    }

    public function testAComputedFieldIsHiddenWhenTheRecordIsNotReadable()
    {
        $this->assertFalse($this->acl(false)->isFieldActionAllowed(self::MODEL, 'itemCount', 'get'));
    }

    public function testComputedFieldsInAWriteBodyAreDiscarded()
    {
        $acl = $this->acl(true);

        foreach (['create', 'update'] as $action) {
            $payload = $acl->authorizeWritableFields(self::MODEL, [
                'id' => 'b1',
                'itemCount' => 99,
                'ownerCount' => 99,
            ], $action);

            $this->assertSame(['id' => 'b1'], $payload);
        }
    }

    /**
     * @return ComputedColumns
     */
    private function columns()
    {
        return new ComputedColumns(self::MODEL, self::computed());
    }

    /**
     * A restrictive Acl that grants no field resource; the model's get is allowed as given.
     *
     * @param  bool $modelReadable
     * @return Acl
     */
    private function acl($modelReadable)
    {
        $acl = $this->getMockBuilder(Acl::class)
            ->setConstructorArgs([$this->di, Acl::RESOLUTION_RESTRICTIVE])
            ->onlyMethods(['isModelActionAllowed', 'isResourceAllowed'])
            ->getMock();
        $acl->method('isModelActionAllowed')->willReturn($modelReadable);
        $acl->method('isResourceAllowed')->willReturn(false);

        return $acl;
    }
}
