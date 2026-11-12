<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers\Components;

use \Phalcon\Events\Event as Event;

/**
 * Class filethumbComponent
 *
 * @class filethumbComponent
 * @package Gaia\MVC\REST\Controllers\Components
 */
class FilethumbComponent
{
    /**
     * Before the response is sent we check if the files exists, if so then we
     * replace replace the thumbnail with its image contents.
     *
     * @param Event $event
     * @param $controller
     */
    public function afterRead(Event $event, $controller)
    {
        global $logger;
        $logger->debug('Gaia.Controller.Component.filethumb::afterRead()');

        foreach (['data', 'included'] as $section) {
            if (empty($controller->finalData[$section]) || !is_array($controller->finalData[$section])) {
                continue;
            }

            if (isset($controller->finalData[$section]['type'])) {
                // a single resource
                $this->convertThumbnail($controller->finalData[$section]);
                continue;
            }

            foreach ($controller->finalData[$section] as $key => $resource) {
                $this->convertThumbnail($controller->finalData[$section][$key]);
            }
        }
        $logger->debug('-Gaia.Controller.Component.filethumb::afterRead()');
    }

    /**
     * Replaces the thumbnail flag of an upload resource with the data URI of
     * its thumbnail image. Other resources, and uploads without a thumbnail
     * file, are left untouched. Thumbnails are always written as JPEG by
     * UploadController::_generateThumb(), whatever the upload's own type.
     *
     * @param array $resource The resource object, changed in place
     */
    private function convertThumbnail(&$resource)
    {
        global $logger;

        if (!is_array($resource) || ($resource['type'] ?? null) != 'upload' || empty($resource['attributes']['fileThumbnail'])) {
            return;
        }

        $logger->debug('File '.($resource['attributes']['name'] ?? '').' with thumbnail found');
        $targetPath = APP_PATH . DS. 'filesystem'.DS.'uploads' . DS.
                        "thumbnail/thumb_" . $resource['id']. '.jpg';

        $contents = is_readable($targetPath) ? file_get_contents($targetPath) : false;
        if ($contents === false) {
            $logger->warning('Thumbnail file of upload '.$resource['id'].' is missing or unreadable');
            return;
        }

        $resource['attributes']['fileThumbnail'] = 'data:image/jpeg;base64,'.base64_encode($contents);
    }
}
