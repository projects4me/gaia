<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components\Events\Support;

/**
 * This class resolves a project id from a persisted model or a related parent.
 *
 * @class   ProjectIdResolver
 * @package Gaia\MVC\REST\Controllers\Components\Events\Support
 */
class ProjectIdResolver
{
    /**
     * relatedTo values mapped to Gaia model class short names.
     *
     * @var  array
     * @type array
     */
    protected $relatedToModels = array(
        'conversationrooms' => 'Conversationroom',
        'conversationroom' => 'Conversationroom',
        'issue' => 'Issue',
        'issues' => 'Issue',
        'wiki' => 'Wiki',
        'project' => 'Project',
        'milestone' => 'Milestone',
    );

    /**
     * Returns the project id for the model, walking relatedTo / issueId when
     * projectId is not on the row.
     *
     * @param  mixed $model The persisted model.
     * @method resolve
     * @return string|null
     */
    public function resolve($model)
    {
        if (!$model) {
            return null;
        }

        $className = $this->className($model);
        if ($className === 'Project') {
            return isset($model->id) ? (string) $model->id : null;
        }

        if (!empty($model->projectId)) {
            return (string) $model->projectId;
        }

        if (!empty($model->relatedTo) && !empty($model->relatedId)) {
            $relatedClass = $this->relatedToModels[strtolower($model->relatedTo)] ?? null;
            if ($relatedClass) {
                return $this->projectIdFromRelated($relatedClass, $model->relatedId);
            }
        }

        if (!empty($model->issueId)) {
            return $this->projectIdFromRelated('Issue', $model->issueId);
        }

        return null;
    }

    /**
     * Loads a related model by id and returns its project id.
     *
     * @param  string $shortName Model class short name (Issue, Conversationroom, ...).
     * @param  string $id        Related record id.
     * @method projectIdFromRelated
     * @return string|null
     */
    protected function projectIdFromRelated($shortName, $id)
    {
        $related = $this->findById($shortName, $id);
        if (!$related) {
            return null;
        }
        if ($shortName === 'Project') {
            return isset($related->id) ? (string) $related->id : null;
        }
        if (!empty($related->projectId)) {
            return (string) $related->projectId;
        }
        return null;
    }

    /**
     * Finds a Gaia model by id, or null when the class or id is missing.
     *
     * @param  string $shortName Model class short name.
     * @param  string $id        Record id.
     * @method findById
     * @return mixed
     */
    protected function findById($shortName, $id)
    {
        $fqcn = '\\Gaia\\MVC\\Models\\' . $shortName;
        if (!class_exists($fqcn) || empty($id)) {
            return null;
        }

        return $fqcn::findFirst(array(
            'conditions' => 'id = :id:',
            'bind' => array('id' => $id),
        ));
    }

    /**
     * Returns the unqualified class name of the model.
     *
     * @param  mixed $model The persisted model.
     * @method className
     * @return string
     */
    protected function className($model)
    {
        $parts = explode('\\', get_class($model));
        return end($parts);
    }
}
