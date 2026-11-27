<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Tests\Gaia\MVC\Models;

use Phalcon\Di\Di;
use Phalcon\Mvc\Model\MetaData;
use Phalcon\Mvc\Model\MetaData\Strategy\Introspection;
use PHPUnit\Framework\TestCase;

/**
 * The ORM reads its metadata from Gaia's model metadata (Model::metaData()), not from the schema. This test keeps the
 * two in step: for every model in app/metadata/model it compares Model::metaData() with what Phalcon's Introspection
 * strategy reads from the test database, which the migrations built from the same metadata.
 *
 * A known difference goes in ALLOWED_DIFFERENCES with its reason; anything else fails the test.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ModelMetadataParityTest extends TestCase
{
    /**
     * The aspects compared, by their metadata key.
     */
    private const ASPECTS = array(
        MetaData::MODELS_ATTRIBUTES => 'attributes',
        MetaData::MODELS_PRIMARY_KEY => 'primaryKey',
        MetaData::MODELS_NON_PRIMARY_KEY => 'nonPrimaryKey',
        MetaData::MODELS_NOT_NULL => 'notNull',
        MetaData::MODELS_DATA_TYPES => 'dataTypes',
        MetaData::MODELS_DATA_TYPES_NUMERIC => 'numeric',
        MetaData::MODELS_IDENTITY_COLUMN => 'identity',
        MetaData::MODELS_DATA_TYPES_BIND => 'bindTypes',
        MetaData::MODELS_DEFAULT_VALUES => 'defaults',
        MetaData::MODELS_EMPTY_STRING_VALUES => 'emptyStrings',
    );

    private const VIEW = 'a read-only model backed by a database view: views have no primary key and report every '
        . 'column nullable without a default, and their computed column types differ; the ORM never writes them';

    private const BIGINT_DATA_TYPE = 'a bigint column (from the original dump) declared int: the ORM casts both as '
        . 'integers, and the values stay in the int range';

    private const BIGINT_BIND_TYPE = 'Phalcon\'s PostgreSQL introspection binds bigint as a string; the metadata binds '
        . 'the int field as an integer, which PostgreSQL accepts for the same values';

    private const DOUBLE = 'a double precision column declared float: both are floating-point and bound as decimals';

    private const SEARCH_COLUMN = 'a search column (vector/tsvector) carried as text; introspection reports the type '
        . 'it cannot name as varchar, and the ORM never writes it (generated or ormWrite false)';

    /**
     * Known differences: '<Model>' (the whole model), '<Model>.<aspect>' (the whole aspect) or
     * '<Model>.<aspect>.<field>' => why it is allowed.
     */
    private const ALLOWED_DIFFERENCES = array(
        'Conversationroomcount' => self::VIEW,
        'Usercollaboration' => self::VIEW,
        'Userecentactivity' => self::VIEW,
        'Usergrouppermission' => self::VIEW,
        'Userlatestissue' => self::VIEW,
        'Userlatestproject' => self::VIEW,
        'Useropenclosedissue' => self::VIEW,
        'Useropenclosedproject' => self::VIEW,
        'Userpermission' => self::VIEW,
        'Usertimespent' => self::VIEW,
        'Userworkmostwith' => self::VIEW,
        'Workspacemember' => self::VIEW,
        'Workspacemilestone' => self::VIEW,

        'Aclcontroller.dataTypes.lft' => self::BIGINT_DATA_TYPE,
        'Aclcontroller.bindTypes.lft' => self::BIGINT_BIND_TYPE,
        'Aclcontroller.dataTypes.rht' => self::BIGINT_DATA_TYPE,
        'Aclcontroller.bindTypes.rht' => self::BIGINT_BIND_TYPE,
        'Badgelevel.dataTypes.max_criteria' => self::BIGINT_DATA_TYPE,
        'Badgelevel.bindTypes.max_criteria' => self::BIGINT_BIND_TYPE,
        'Badgelevel.dataTypes.min_criteria' => self::BIGINT_DATA_TYPE,
        'Badgelevel.bindTypes.min_criteria' => self::BIGINT_BIND_TYPE,
        'Conversationroom.dataTypes.issueNumber' => self::BIGINT_DATA_TYPE,
        'Conversationroom.bindTypes.issueNumber' => self::BIGINT_BIND_TYPE,
        'Issue.dataTypes.issueNumber' => self::BIGINT_DATA_TYPE,
        'Issue.bindTypes.issueNumber' => self::BIGINT_BIND_TYPE,
        'Scoreboard.dataTypes.score' => self::BIGINT_DATA_TYPE,
        'Scoreboard.bindTypes.score' => self::BIGINT_BIND_TYPE,
        'User.dataTypes.failedLoginAttempts' => self::BIGINT_DATA_TYPE,
        'User.bindTypes.failedLoginAttempts' => self::BIGINT_BIND_TYPE,

        'Ideamessage.dataTypes.costUsd' => self::DOUBLE,
        'Project.dataTypes.estimatedBudget' => self::DOUBLE,
        'Project.dataTypes.spentBudget' => self::DOUBLE,

        'Issue.dataTypes.searchEmbedding' => self::SEARCH_COLUMN,
        'Issue.dataTypes.searchVector' => self::SEARCH_COLUMN,
    );

    protected function setUp(): void
    {
        if (!class_exists(\Phalcon\Di\FactoryDefault::class)) {
            $this->markTestSkipped('Phalcon is not available in this PHP runtime');
        }
    }

    /**
     * @return array
     */
    public static function modelProvider()
    {
        $models = array();
        foreach (glob(dirname(__DIR__, 4) . '/app/metadata/model/*.php') as $file) {
            $name = basename($file, '.php');
            $models[$name] = array($name);
        }
        return $models;
    }

    /**
     * @dataProvider modelProvider
     * @param string $modelName
     */
    public function testMetadataMatchesTheSchema($modelName)
    {
        $class = '\\Gaia\\MVC\\Models\\' . $modelName;
        $this->assertTrue(class_exists($class), $modelName . ' has metadata but no model class');
        if (isset(self::ALLOWED_DIFFERENCES[$modelName])) {
            $this->addToAssertionCount(1);
            return;
        }

        $di = Di::getDefault();
        $model = new $class();
        $fromMetadata = $model->metaData();
        $fromSchema = (new Introspection())->getMetaData($model, $di);

        $differences = array();
        foreach (self::ASPECTS as $key => $aspect) {
            if (isset(self::ALLOWED_DIFFERENCES[$modelName . '.' . $aspect])) {
                continue;
            }
            foreach ($this->compare($aspect, $fromSchema[$key] ?? null, $fromMetadata[$key] ?? null) as $field => $diff) {
                if (!isset(self::ALLOWED_DIFFERENCES[$modelName . '.' . $aspect . '.' . $field])) {
                    $differences[] = $aspect . '.' . $field . ': ' . $diff;
                }
            }
        }

        $this->assertSame(array(), $differences, $modelName . ' metadata differs from the schema');
    }

    /**
     * Compare one aspect field by field.
     *
     * @param  string $aspect
     * @param  mixed  $schema    What introspection read
     * @param  mixed  $metadata  What Model::metaData() returns
     * @return array  field => description of the difference
     */
    private function compare($aspect, $schema, $metadata)
    {
        if ($aspect === 'identity') {
            return $schema === $metadata
                ? array()
                : array('*' => 'schema ' . json_encode($schema) . ', metadata ' . json_encode($metadata));
        }

        $schema = is_array($schema) ? $schema : array();
        $metadata = is_array($metadata) ? $metadata : array();

        // Lists of column names: compare as sets
        if (in_array($aspect, array('attributes', 'primaryKey', 'nonPrimaryKey', 'notNull'), true)) {
            $schema = array_fill_keys($schema, true);
            $metadata = array_fill_keys($metadata, true);
        }

        $differences = array();
        foreach (array_unique(array_merge(array_keys($schema), array_keys($metadata))) as $field) {
            if (!array_key_exists($field, $schema)) {
                $differences[$field] = 'only in metadata';
            } elseif (!array_key_exists($field, $metadata)) {
                $differences[$field] = 'only in schema';
            } elseif ($this->normalize($aspect, $schema[$field]) !== $this->normalize($aspect, $metadata[$field])) {
                $differences[$field] = 'schema ' . json_encode($schema[$field])
                    . ', metadata ' . json_encode($metadata[$field]);
            }
        }
        return $differences;
    }

    /**
     * The schema reads defaults back as SQL text ('0', 'false', 'open'); compare them as such. A boolean column's
     * default reads back as 'false'/'true' where the metadata writes 0/1, so both spell it as '0'/'1' (the column
     * types are compared separately).
     *
     * @param  string $aspect
     * @param  mixed  $value
     * @return mixed
     */
    private function normalize($aspect, $value)
    {
        if ($aspect !== 'defaults' || $value === null) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        $value = (string) $value;
        $booleans = array('false' => '0', 'true' => '1');
        return $booleans[$value] ?? $value;
    }
}
