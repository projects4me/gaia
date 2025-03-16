<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Libraries\Utils;

/**
 * This class provides utility functions for working with CSV files.
 *
 * @class Csv
 * @package Gaia\Libraries\Utils
 * @author Rana Nouman <ranamnouman@gmail.com>
 */
class Csv
{
    /**
     * This function generates CSV files for the provided module data and its relationships. It creates a main CSV file
     * for the module and additional CSV files for any pending relationships. The main CSV file for the module contains
     * the relationships of the type hasOne and belongsTo. The additional CSV files contain the relationships of the type
     * hasMany and manyToMany.
     *
     * @param string $moduleName The name of the module.
     * @param array  $data       The data to be exported.
     * @param array  $metadata   Metadata information about the module fields.
     * @param array  $rels       Relationship data for the module.
     * @param object $model      The model instance.
     *
     * @return array An array of file paths to the generated CSV files.
     */
    public static function prepareCsvFiles($moduleName, $data, $metadata, $rels, $model)
    {
        global $currentUser;
        $modulePluralizedName = strtolower($moduleName) . 's';
        $csvFiles = [];
        $modelData = [];
        $relsData = [];
        $modelFields = array_keys($metadata['fields']);
        $userId = $currentUser->id;
        $tempDir = APP_PATH . DS . 'filesystem' . DS . 'temp' . DS . $userId . DS . 'csvs';

        // Create the directory if it doesn't exist
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0700, true);
        }

        $csvPath = $tempDir . DS . $modulePluralizedName . '.csv';
        $file = fopen($csvPath, 'w');

        $file = self::setCsvHeaders($file, $modelFields, $data, $rels, $model);
        list($file, $pendingRels) = self::setCsvContent($file, $data, $metadata, $rels, $model);
        fclose($file);
        $csvFiles[] = $csvPath;

        // Handle pending relationships (one-to-many and many-to-many)
        foreach ($pendingRels as $relName => $relData) {
            $csvPath = APP_PATH . DS . 'filesystem' . DS . 'exports' . DS . $relName . '.csv';
            $csvFiles[] = $csvPath;

            $file = fopen($csvPath, 'w');

            // Set Headers
            fputcsv($file, array_keys($relData[0]));

            // Set Content
            foreach ($relData as $key => $dataset) {
                fputcsv($file, array_values($dataset));
            }

            fclose($file);
        }

        return $csvFiles;
    }

    /**
     * Sets the CSV headers for the given file based on the model fields, data, and relationships (hasOne and belongsTo only).
     * This is used to write the main CSV file for the module.
     *
     * @param resource $file        The file resource to write the CSV headers to.
     * @param array    $modelFields The fields of the model to be included in the CSV headers.
     * @param array    $data        The data from which relationship fields will be extracted.
     * @param array    $rels        The relationships to be considered for extracting fields.
     * @param object   $model       The model instance.
     *
     * @return resource The file resource with the CSV headers written.
     */
    public static function setCsvHeaders($file, $modelFields, $data, $rels, $model)
    {
        $relsFields = [];
        $relationship = $model->getRelationship();
        $supportedRelTypes = ['hasOne', 'belongsTo'];
        $modelFields = [];

        foreach ($data as $key => $dataset) {
            foreach ($rels as $rel) {
                $relType = $relationship->getRelationship($rel)['type'];

                if (in_array($relType, $supportedRelTypes) && $dataset[$rel]) {
                    $prefixedArray = array_map(
                        function ($key) use ($rel) {
                            return "{$rel}_{$key}";
                        },
                        array_keys($dataset[$rel])
                    );
                    $relsFields = array_merge($relsFields, $prefixedArray);
                }

                unset($dataset[$rel]);
            }

            $modelFields = array_keys($dataset);
            break;
        }

        $fields = array_merge($modelFields, $relsFields);
        fputcsv($file, $fields);
        return $file;
    }

    /**
     * Sets the content of a CSV file based on provided data and relationships (hasOne and belongsTo only). This is
     * used to write the main CSV file for the module.
     *
     * @param resource $file     The file resource to write the CSV data to.
     * @param array    $data     The data to be written to the CSV file.
     * @param array    $metadata Metadata associated with the data.
     * @param array    $rels     Requested relationships.
     * @param object   $model    The model instance.
     *
     * @return array An array containing the file resource and any pending relationships.
     */
    public static function setCsvContent($file, $data, $metadata, $rels, $model)
    {
        $relationship = $model->getRelationship();
        $pendingRels = [];

        // Handle model and one-to-one relationships
        foreach ($data as $key => $dataset) {
            $csvData = [];

            foreach ($dataset as $innerKey => $value) {
                if (!is_array($value)) {
                    $csvData[] = $value;
                } else {
                    $rel = $relationship->getRelationship($innerKey);
                    if ($rel['type'] == 'hasOne' || $rel['type'] == 'belongsTo') {
                        $csvData = array_merge($csvData ?? [], array_values($value));
                    } else {
                        $pendingRels[$innerKey] = array_merge($pendingRels[$innerKey] ?? [], $value);
                    }
                }
            }
            fputcsv($file, $csvData);
        }
        return array($file, $pendingRels);
    }
}
