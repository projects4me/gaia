<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\MVC\Models\Downloadtoken;

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
                    exit();
                } else {
                    $logger->critical("File mentioned in the data not found");
                    $this->response->setStatusCode(500, "Internal Server Error");
                    $this->response->send();
                    $logger->debug("-Gaia.MVC.Controller.download->getAction");
                    exit();
                }
            }
            $logger->info("Expired download token provided");
            $downloadToken->delete();
        }
        $this->response->setStatusCode(404, "Not Found");
        $this->response->send();
        $logger->debug("-Gaia.MVC.Controller.download->getAction");
        exit();
    }

}
