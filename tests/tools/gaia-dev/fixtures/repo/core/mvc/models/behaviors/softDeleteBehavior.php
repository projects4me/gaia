<?php

// Test fixture for tests/tools/gaia-dev: a model behaviour.
namespace Gaia\MVC\Models\Behaviors;

class softDeleteBehavior extends Behavior implements BehaviorInterface
{
    public function notify($eventType, $model)
    {
    }

    private function mark()
    {
    }
}
