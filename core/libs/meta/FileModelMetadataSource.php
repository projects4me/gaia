<?php

namespace Gaia\Libraries\Meta;

use Gaia\Libraries\Search\SearchBlock;
use Phalcon\Mvc\Model\MetaData;

/**
 * Builds a model's metadata from its metadata file: loads it through the file handler, applies the
 * search block and parses the fields.
 *
 * @package  Foundation
 * @category metaManager
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class FileModelMetadataSource implements ModelMetadataSource
{
    /** @var \Phalcon\Di\DiInterface */
    private $di;

    /** @var Manager parses the fields into the ORM structures */
    private $fieldParser;

    public function __construct($di, Manager $fieldParser)
    {
        $this->di = $di;
        $this->fieldParser = $fieldParser;
    }

    public function get(string $model): array
    {
        $metadata = $this->di->get('fileHandler')->readFile(APP_PATH . Manager::basePath . '/model/' . $model . '.php');
        // Routes without a model metadata file (e.g. Issueplanning) read as null
        $metadata = isset($metadata[$model]) ? $metadata[$model] : array();
        // A search block adds its system columns (D-071) before the fields are parsed
        $metadata = SearchBlock::apply($model, $metadata);
        $fields = $this->fieldParser->parseFields($metadata);

        return array(
            // Set the table name
            'tableName' => $fields['tableName'],

            // Every column in the mapped table
            MetaData::MODELS_ATTRIBUTES => $fields['attributes'],

            // Every column part of the primary key
            MetaData::MODELS_PRIMARY_KEY => $fields['primary'],

            // Every column that isn't part of the primary key
            MetaData::MODELS_NON_PRIMARY_KEY => $fields['nonPrimary'],

            // Every column that doesn't allows null values
            MetaData::MODELS_NOT_NULL => $fields['notNull'],

            // Every column and their data types
            MetaData::MODELS_DATA_TYPES => $fields['type'],

            // The columns that have numeric data types
            MetaData::MODELS_DATA_TYPES_NUMERIC => $fields['numeric'],

            // The identity (auto-increment) column, or false when the table has none. A GUID primary key is not
            // an identity column: the ORM would read it back from a sequence after the INSERT.
            MetaData::MODELS_IDENTITY_COLUMN => $fields['id'],

            // How every column must be bound/casted
            MetaData::MODELS_DATA_TYPES_BIND => $fields['bind'],

            // Fields that must be ignored from INSERT SQL statements: columns the ORM never writes
            MetaData::MODELS_AUTOMATIC_DEFAULT_INSERT => $fields['skipWrite'],

            // Fields that must be ignored from UPDATE SQL statements
            MetaData::MODELS_AUTOMATIC_DEFAULT_UPDATE => $fields['skipWrite'],

            // The columns the database fills when an INSERT leaves them out, with their default
            MetaData::MODELS_DEFAULT_VALUES => $fields['default'],

            // Fields that allow empty strings on a not-null column; none, as with the schema introspection
            MetaData::MODELS_EMPTY_STRING_VALUES => array(),

            'fields' => (isset($metadata['fields']) ? $metadata['fields'] : array()),

            // Read-only values computed on read, not columns (Workspaces D-013)
            'computed' => (isset($metadata['computed']) && is_array($metadata['computed']) ? $metadata['computed'] : array()),

            // All the relationships for the model
            'relationships' => (isset($metadata['relationships']) ? $metadata['relationships'] : array()),

            // Load the behaviors as well
            'behaviors' => (isset($metadata['behaviors']) ? $metadata['behaviors'] : array()),

            'acl' => (isset($metadata['acl']) ? $metadata['acl'] : array()),

            // The text fields whose user mentions are recorded (see mentionsBehavior)
            'mentions' => (isset($metadata['mentions']) ? $metadata['mentions'] : array()),

            // The validated search block, or null when the model is not searchable
            SearchBlock::KEY => (isset($metadata[SearchBlock::KEY]) ? $metadata[SearchBlock::KEY] : null),
        );
    }
}
