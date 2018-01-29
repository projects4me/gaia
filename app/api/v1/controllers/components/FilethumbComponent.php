<?php

namespace Foundation\Mvc\Controller\Component;

use \Phalcon\Events\Event as Event;

/**
 * Class filethumbComponent
 *
 * @class filethumbComponent
 * @package Foundation\Mvc\Controller\Component
 */
class filethumbComponent
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

        $included = $controller->finalData['included'];
        $uploads = array_filter($included,function($v,$k){
            return $v['type'] == 'upload';
        },ARRAY_FILTER_USE_BOTH);

        foreach($uploads as $key => $value) {
            if ($value['attributes']['fileThumbnail']){

                $logger->debug('File '.$value['attributes']['name'].' with thumbnail found');
                $targetPath = APP_PATH . DS. 'filesystem'.DS.'uploads' . DS.
                                "thumbnail/thumb_" . $value['id']. '.jpg';


                $thumbdata = 'data:'.$value['attributes']['fileMime'].';base64,'.base64_encode(file_get_contents($targetPath));
                $controller->finalData['included'][$key]['attributes']['fileThumbnail'] = $thumbdata;
            }
        }
        $logger->debug('-Gaia.Controller.Component.filethumb::afterRead()');
    }
}