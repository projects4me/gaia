<?php

use Foundation\Mvc\Model;
use Phalcon\Db\Column;
use Phalcon\Mvc\Model\MetaData;

class Users extends Model
{

    public function metaData()
    {
        return array(
            // Every column in the mapped table
            MetaData::MODELS_ATTRIBUTES => array(
                'id', 'username', 'password'
            ),

            // Every column part of the primary key
            MetaData::MODELS_PRIMARY_KEY => array(
                'id'
            ),

            // Every column that isn't part of the primary key
            MetaData::MODELS_NON_PRIMARY_KEY => array(
                'username', 'password'
            ),

            // Every column that doesn't allows null values
            MetaData::MODELS_NOT_NULL => array(
                'id', 'username'
            ),

            // Every column and their data types
            MetaData::MODELS_DATA_TYPES => array(
                'id'   => Column::TYPE_INTEGER,
                'username' => Column::TYPE_VARCHAR,
                'password' => Column::TYPE_VARCHAR,
            ),

            // The columns that have numeric data types
            MetaData::MODELS_DATA_TYPES_NUMERIC => array(
            ),

            // The identity column, use boolean false if the model doesn't have
            // an identity column
            MetaData::MODELS_IDENTITY_COLUMN => 'id',

            // How every column must be bound/casted
            MetaData::MODELS_DATA_TYPES_BIND => array(
                'id'   => Column::BIND_PARAM_INT,
                'username' => Column::BIND_PARAM_STR,
                'password' => Column::BIND_PARAM_STR,
            ),

            // Fields that must be ignored from INSERT SQL statements
            MetaData::MODELS_AUTOMATIC_DEFAULT_INSERT => array(
            ),

            // Fields that must be ignored from UPDATE SQL statements
            MetaData::MODELS_AUTOMATIC_DEFAULT_UPDATE => array(
            ),

            // Default values for columns
            MetaData::MODELS_DEFAULT_VALUES => array(
            ),

            // Fields that allow empty strings
            MetaData::MODELS_EMPTY_STRING_VALUES => array(
            )
        );
    }
}