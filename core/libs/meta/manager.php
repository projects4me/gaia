<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Meta;

use Phalcon\Db\Column;
use Phalcon\Mvc\Model\MetaData;
use Gaia\Libraries\file\Handler as fileHandler;

/**
 * This class is used for the metadata storage, retrival and maintenance.
 *
 * The meta data in Foundation is divided into 3 parts
 * 1) Model Metadata
 * 2) View Metadata
 * 3) BusinessProcess Metadata
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category metaManager
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
  */
class Manager
{
    /**
     * This is the dependency injector used for this class
     * @var \Phalcon\DiInterface $di
     */
    protected $di;

    public const basePath = '/app/metadata';

    /**
     * Manager constructor.
     *
     * @param \Phalcon\DiInterface $di
     */
    public function __construct(\Phalcon\Di\FactoryDefault $di)
    {
        $this->di = $di;
    }

    /**
     * This function get the model metadata stored for the application and
     * returns it. This class uses Phalcon Model Data
     *
     * @todo Use Cahce
     * @param string $model
     * @return array
     */
    public function getModelMeta($model)
    {
        $metadata = $this->di->get('fileHandler')->readFile(APP_PATH . self::basePath . '/model/' . $model . '.php');
        $metadata = $metadata[$model];
        $fields = $this->parseFields($metadata);

        $modelMeta = array(
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
            MetaData::MODELS_DATA_TYPES_NUMERIC => array(
            ),

            // The identity column, use boolean false if the model doesn't have
            // an identity column
            MetaData::MODELS_IDENTITY_COLUMN => $fields['id'],

            // How every column must be bound/casted
            MetaData::MODELS_DATA_TYPES_BIND => $fields['bind'],

            // Fields that must be ignored from INSERT SQL statements
            MetaData::MODELS_AUTOMATIC_DEFAULT_INSERT => array(),//$fields['default'],

            // Fields that must be ignored from UPDATE SQL statements
            MetaData::MODELS_AUTOMATIC_DEFAULT_UPDATE => array(),//$fields['default'],

            // Default values for columns
            MetaData::MODELS_DEFAULT_VALUES => array(),//$fields['default'],

            // Fields that allow empty strings
            MetaData::MODELS_EMPTY_STRING_VALUES => array(
            ),

            // All the relationships for the model
            // For now we will just copy things over and see if for future
            // we need any adjustment in the format
            'relationships' => (isset($metadata['relationships']) ? $metadata['relationships'] : array()),

            // Load the behaviors as well
            'behaviors' => (isset($metadata['behaviors']) ? $metadata['behaviors'] : array()),

            'acl' => (isset($metadata['acl']) ? $metadata['acl'] : array()),

            'customIdentifiers' => (isset($metadata['customIdentifiers']) ? $metadata['customIdentifiers'] : array())
        );

        return $modelMeta;
    }

    /**
     * This function parses the data from the metadata array
     *
     * @param array $metadata
     * @return array
     */
    public function parseFields($metadata)
    {
        // The application default values
        $data = array('default' => array());
        $data['tableName'] = $metadata['tableName'];
        foreach ($metadata['fields'] as $field => $properties) {
            // Add all the fields in the attributes array
            $data['attributes'][] = $field;

            // Sort out the primary and non primary fields
            if(isset($metadata['indexes'][$field]) && $metadata['indexes'][$field] == 'primary') {
                $data['primary'][] = $field;
                $data['id'] = $field;
            } else {
                $data['nonPrimary'][] = $field;
            }

            // Set if any field can be nul
            if (!$properties['null']) {
                $data['notNull'][] = $field;
            }

            // set the field type
            $data['type'][$field] = $this->getFieldType($properties['type']);

            // set the field data bind type
            $data['bind'][$field] = $this->getBindType($properties['type']);

            // if default value is set in metadata then set it
            if (isset($properties['default'])) {
                //$data['bind'][$field] = $properties['default'];
                $data['default'][$field] = $properties['default'];
            }

        }
        return $data;
    }

    /**
     * This function returns the type defined in metadata mapped according to
     * PhalconPHP
     *
     * @param string $type
     * @return integer
     */
    public function getFieldType($type)
    {
        $dbType = '';
        switch ($type) {
            case 'int':
                $dbType = Column::TYPE_INTEGER;
                break;
            case 'date':
                $dbType = Column::TYPE_DATE;
                break;
            case 'varchar':
                $dbType = Column::TYPE_VARCHAR;
                break;
            case 'decimal':
                $dbType = Column::TYPE_DECIMAL;
                break;
            case 'datetime':
                $dbType = Column::TYPE_DATETIME;
                break;
            case 'char':
                $dbType = Column::TYPE_CHAR;
                break;
            case 'text':
                $dbType = Column::TYPE_TEXT;
                break;
            case 'float':
                $dbType = Column::TYPE_FLOAT;
                break;
            case 'bool':
                $dbType = Column::TYPE_BOOLEAN;
                break;
            case 'tinyBlob':
                $dbType = Column::TYPE_TINYBLOB;
                break;
            case 'blob':
                $dbType = Column::TYPE_BLOB;
                break;
            case 'mediumBlob':
                $dbType = Column::TYPE_MEDIUMBLOB;
                break;
            case 'longBlob':
                $dbType = Column::TYPE_LONGBLOB;
                break;
            case 'bigInt':
                $dbType = Column::TYPE_BIGINTEGER;
                break;
        }
        return $dbType;
    }

    /**
     * This function returns the field data bind type defined in metadata
     * mapped according to PhalconPHP
     *
     * @param string $type
     * @return integer
     */
    public function getBindType($type)
    {
        $bindType = '';
        switch ($type) {
            case 'int':
                $bindType = Column::BIND_PARAM_INT;
                break;
            case 'date':
                $bindType = Column::BIND_PARAM_STR;
                break;
            case 'varchar':
                $bindType = Column::BIND_PARAM_STR;
                break;
            case 'decimal':
                $bindType = Column::BIND_PARAM_DECIMAL;
                break;
            case 'datetime':
                $bindType = Column::BIND_PARAM_STR;
                break;
            case 'char':
                $bindType = Column::BIND_PARAM_STR;
                break;
            case 'text':
                $bindType = Column::BIND_PARAM_BLOB;
                break;
            case 'float':
                $bindType = Column::BIND_PARAM_DECIMAL;
                break;
            case 'bool':
                $bindType = Column::BIND_PARAM_BOOL;
                break;
            case 'tinyBlob':
                $bindType = Column::BIND_PARAM_BLOB;
                break;
            case 'blob':
                $bindType = Column::BIND_PARAM_BLOB;
                break;
            case 'mediumBlob':
                $bindType = Column::BIND_PARAM_BLOB;
                break;
            case 'longBlob':
                $bindType = Column::BIND_PARAM_BLOB;
                break;
            case 'bigInt':
                $bindType = Column::BIND_PARAM_INT;
                break;
        }
        return $bindType;
    }

    /**
     * This function is used to get list of groups of the given model.
     *
     * @method getModelGroups
     * @return array
     */
    public function getModelGroups($modelName)
    {
        $systemGroups = $this->getGroups();
        $metadata = $this->getModelMeta($modelName);
        $fields = $metadata[0];
        $explicitKeys = !empty($metadata['acl']['groupExplicitKeys'])
                        ? $metadata['acl']['groupExplicitKeys']
                        : [];
        $modelGroups = [];

        foreach ($systemGroups as $group) {
            $groupMetadata = $this->getModelMeta($group);
            $relatedKey = $groupMetadata['acl']['group']['relatedKey'];

            // Check if there is same field as relatedKey of group.
            foreach ($fields as $field) {
                if ($relatedKey === $field) {
                    $modelGroups[] = $group;
                }
            }

            // Check if there is an explicit key against the group name in the model metadata.
            if ($explicitKeys && isset($explicitKeys[strtolower($group)]) && !isset($modelGroups[$group])) {
                $modelGroups[] = $group;
            }
        }

        return $modelGroups;
    }

    /**
     * This function is used to return the list of groups in the system.
     *
     * @method getGroups
     * @return array
     */
    public function getGroups()
    {
        $path = APP_PATH . '/app/metadata/model';
        global $settings;
        $models = $settings['models'];
        $groups = [];

        foreach ($models as $modelName) {
            $metadata = $this->getModelMeta($modelName);

            if (isset($metadata['acl']['group'])) {
                $groups[] = $modelName;
            }
        }

        return $groups;
    }

    /**
     * This function returns list of identifiers of the given model.
     *
     * @method getModelIdentifiers
     * @param $modelName Name of the model.
     * @return Array
     */
    public function getModelIdentifiers($modelName)
    {
        $data = $this->getModelMeta($modelName);

        // Prepare Array of identifiers on which applying ACL is not allowed.
        $excludedIdentifiers = [
            'id'
        ];

        foreach ($data['customIdentifiers'] as $identifier) {
            $excludedIdentifiers[] = $identifier;
        }

        return $excludedIdentifiers;
    }
}
