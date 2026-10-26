<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Graph\Mapper;

use GaiaDev\Graph\Graph;
use GaiaDev\Graph\Schema;

/**
 * Maps metadata model records and the registered model list onto model, field,
 * relationship and behaviour nodes.
 *
 * A model gets `registered` (listed in models.php) and `hasMetadata` (has a
 * metadata file), so either list can name a model the other misses.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Graph\Mapper
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class ModelMapper
{
    /**
     * @param Graph $graph
     * @param array[] $models MetadataModelExtractor records
     * @param string[] $registered ModelListExtractor names
     */
    public function map(Graph $graph, array $models, array $registered)
    {
        foreach ($models as $model) {
            $graph->addNode(Schema::MODEL, $model['name'], [
                'file' => $model['file'],
                'tableName' => $model['tableName'],
                'isView' => $model['isView'],
                'registered' => in_array($model['name'], $registered, true),
                'hasMetadata' => true,
                'acl' => $model['acl'],
            ]);
        }

        foreach ($registered as $name) {
            if (!$graph->hasNode(Schema::nodeId(Schema::MODEL, $name))) {
                $graph->addNode(Schema::MODEL, $name, ['registered' => true, 'hasMetadata' => false]);
            }
        }

        foreach ($models as $model) {
            $this->mapMembers($graph, $model);
        }
    }

    /**
     * @param Graph $graph
     * @param array $model
     */
    private function mapMembers(Graph $graph, array $model)
    {
        $modelId = Schema::nodeId(Schema::MODEL, $model['name']);

        foreach ($model['fields'] as $field) {
            $fieldId = $graph->addNode(Schema::FIELD, $model['name'] . '.' . $field['name'], $field['attributes']);
            $graph->addEdge($modelId, 'has_field', $fieldId);
        }

        foreach ($model['fields'] as $field) {
            if (empty($field['attributes']['linkedTo'])) {
                continue;
            }
            $target = Schema::nodeId(Schema::FIELD, $model['name'] . '.' . $field['attributes']['linkedTo']);
            if ($graph->hasNode($target)) {
                $graph->addEdge(Schema::nodeId(Schema::FIELD, $model['name'] . '.' . $field['name']), 'linked_to', $target);
            }
        }

        foreach ($model['relationships'] as $relationship) {
            $this->mapRelationship($graph, $modelId, $model['name'], $relationship);
        }

        foreach ($model['behaviors'] as $behaviour) {
            $graph->addEdge($modelId, 'uses_behaviour', $graph->addNode(Schema::BEHAVIOUR, $behaviour));
        }
    }

    /**
     * A many-to-many relationship goes `via` its join model to the `targets`
     * (secondary) model; every other kind `targets` its related model.
     *
     * @param Graph $graph
     * @param string $modelId
     * @param string $modelName
     * @param array $relationship
     */
    private function mapRelationship(Graph $graph, string $modelId, string $modelName, array $relationship)
    {
        $relationshipId = $graph->addNode(
            Schema::RELATIONSHIP,
            $modelName . '.' . $relationship['name'],
            array_merge(['kind' => $relationship['kind']], $relationship['attributes'])
        );
        $graph->addEdge($modelId, 'has_relationship', $relationshipId);

        if ($relationship['secondaryModel'] !== null) {
            $graph->addEdge($relationshipId, 'targets', $this->ensureModel($graph, $relationship['secondaryModel']));
            if ($relationship['relatedModel'] !== null) {
                $graph->addEdge($relationshipId, 'via', $this->ensureModel($graph, $relationship['relatedModel']));
            }
        } elseif ($relationship['relatedModel'] !== null) {
            $graph->addEdge($relationshipId, 'targets', $this->ensureModel($graph, $relationship['relatedModel']));
        }
    }

    /**
     * A relationship may target a model that has neither metadata nor a
     * models.php entry; it still gets a node so the edge has an end.
     *
     * @param Graph $graph
     * @param string $name
     * @return string the node id
     */
    private function ensureModel(Graph $graph, string $name): string
    {
        $id = Schema::nodeId(Schema::MODEL, $name);
        if (!$graph->hasNode($id)) {
            $graph->addNode(Schema::MODEL, $name, ['registered' => false, 'hasMetadata' => false]);
        }

        return $id;
    }
}
