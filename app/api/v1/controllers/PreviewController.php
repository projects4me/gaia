<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;

/**
 * Preview controller
 *
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PreviewController extends \Phalcon\Mvc\Controller
{

    /**
     * Retrieve the user and image and return
     *
     * @param string $id
     * @return mixed
     * @throws \Phalcon\Exception
     */
    public function getAction($id)
    {
        global $logger;
        $logger->debug("Gaia.MVC.Controller.preview->getAction");

        if (!(isset($id) && !empty($id))) {
            $logger->error("No id was provided");
            throw new \Phalcon\Exception('Id must be set, please refer to guides.');
        }

        $params = array(
            'where' => '(Downloadtoken.downloadToken : '.$id.')',
            'sort' => 'Downloadtoken.dateCreated',
        );

        $model = new Downloadtoken();
        $data = $model->readAll($params);
        if (isset($data[0]) && $data[0]->downloadToken) {
            $logger->info("Found the download token");
            $downloadToken = Downloadtoken::find(
                "downloadToken = '".$data[0]->downloadToken."'"
            );
            // validate expiry
            $expiry = new \DateTime($data[0]->expires,new \DateTimeZone('UTC'));
            $now = new \DateTime('now',new \DateTimeZone('UTC'));

            $diffInSeconds = $now->getTimestamp() - $expiry->getTimestamp();
            $uploadRel = ($data[0]->relatedTo).'Upload';

            // If the download link has not expired
            if ($diffInSeconds <= 0) {
               $upload = $data[0]->$uploadRel;
                $logger->info("valid download token provided");

                if (file_exists($upload->filePath)) {
                    $logger->critical("File found, preparing for download");
                    $this->response->setStatusCode(200, "OK");
                    $this->response->setContentType($upload->fileMime);
                    $this->response->setHeader("Content-Type", $upload->fileMime);
                    $this->response->setHeader("Content-Description", 'File Transfer');
                    $this->response->setHeader("Content-Length", $upload->fileSize);
                    readfile($upload->filePath);
                    $this->response->send();
                    $downloadToken->delete();
                    $logger->debug("-Gaia.MVC.Controller.download->getAction");
                    return $this->response;
                } else {
                    throw new \Gaia\Exception\FileNotfound("File not found");
                }
            }
            $logger->info("Expired download token provided");
            $downloadToken->delete();
        }
        throw new \Gaia\Exception\ResourceNotFound();
    }

}
