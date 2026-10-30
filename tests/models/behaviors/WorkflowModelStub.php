<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Tests\Models\Behaviors;

use Phalcon\Mvc\ModelInterface;
use PHPUnit\Framework\TestCase;

/**
 * Builds the model mocks the workflow behaviour tests share: attributes read
 * and written through readAttribute/writeAttribute, a stored snapshot, and
 * the messages the behaviour appends collected in `$model->messages`.
 *
 * @author   Hammad Hassan <gollomer@gmail.com>
 * @package  Gaia\Tests
 * @category Tests
 * @license  http://www.gnu.org/licenses/agpl.html AGPLv3
 */
trait WorkflowModelStub
{
    /**
     * @param  array $values
     * @param  array $snapshot
     * @return ModelInterface
     */
    private function model(array $values, array $snapshot = [])
    {
        /** @var TestCase $this */
        $model = $this->getMockBuilder(ModelInterface::class)
            ->addMethods(['getSnapshotData', 'readAttribute', 'writeAttribute'])
            ->getMockForAbstractClass();
        $model->messages = [];
        $model->method('getSnapshotData')->willReturn($snapshot);
        $model->method('readAttribute')->willReturnCallback(function ($field) use ($model) {
            return isset($model->{$field}) ? $model->{$field} : null;
        });
        $model->method('writeAttribute')->willReturnCallback(function ($field, $value) use ($model) {
            $model->{$field} = $value;
        });
        $model->method('appendMessage')->willReturnCallback(function ($message) use ($model) {
            $model->messages[] = $message->getMessage();
            return $model;
        });
        foreach ($values as $field => $value) {
            $model->{$field} = $value;
        }

        return $model;
    }
}
