<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\Core\MVC\REST\Controllers\RestController;
use Gaia\MVC\Models\Downloadtoken;

/**
 * Download controller
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class DownloadController extends \Phalcon\Mvc\Controller
{
    /**
     * Retrieve the user and image and return
     *
     * @param string $id
     * @todo Handle validation
     * @todo Generate Image
     * @throws \Phalcon\Exception
     */
    public function getAction($id)
    {
        global $logger;
        $logger->debug("Gaia.MVC.Controller.download->getAction");

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
        $data = $data['baseModel'];

        if (isset($data[0]) && $data[0]->downloadToken) {
            $logger->info("Found the download token");
            $downloadToken = Downloadtoken::find(
                "downloadToken = '".$data[0]->downloadToken."'"
            );

            // Get the upload
            $uploadId = $data[0]->uploadId;
            $upload = \Gaia\MVC\Models\Upload::findFirst("id='{$uploadId}'");

            // validate expiry
            $expiry = new \DateTime($data[0]->expires, new \DateTimeZone('UTC'));
            $now = new \DateTime('now', new \DateTimeZone('UTC'));

            $diffInSeconds = $now->getTimestamp() - $expiry->getTimestamp();
            // If the download link has not expired
            if ($diffInSeconds <= 0) {
                $logger->info("valid download token provided");

                if (file_exists($upload->filePath)) {
                    $logger->critical("File found, preparing for download");
                    $this->response->setStatusCode(200, "OK");
                    $this->response->setContentType($upload->fileMime);
                    $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $upload->name . '"');
                    $this->response->setHeader("Content-Description", 'File Transfer');
                    $this->response->setHeader("Expires", '0');
                    $this->response->setHeader("Cache-Control", 'must-revalidate');
                    $this->response->setHeader("Content-Length", $upload->fileSize);
                    $this->response->setContent(file_get_contents($upload->filePath));

                    // Delete the upload and token
                    $this->deleteUploadAndToken($upload, $downloadToken);
                    $logger->debug("-Gaia.MVC.Controller.download->getAction");
                    return $this->response;
                } else {
                    $logger->critical("File mentioned in the data not found");
                    throw new \Gaia\Exception\FileNotFound("File not found");
                    $logger->debug("-Gaia.MVC.Controller.download->getAction");
                }
            }
            $logger->info("Expired download token provided");
            $this->deleteUploadAndToken($upload, $downloadToken);
        }
        throw new \Gaia\Exception\ResourceNotFound();
    }

    /**
     * Deletes the upload and its associated download token.
     *
     * This method deletes the upload record and, if specified, the associated file from the filesystem.
     * It also deletes the download token associated with the upload.
     *
     * @param object $upload The upload object containing details about the upload.
     * @param object $downloadToken The download token object associated with the upload.
     *
     * @return void
     */
    final private function deleteUploadAndToken($upload, $downloadToken)
    {
        $deleteUploadType = [
            'export' => [
                'deleteFile' => false
            ]
        ];

        if (in_array($upload->relatedTo, array_keys($deleteUploadType))) {
            $upload->delete();
            $deleteFile = $deleteUploadType[$upload->relatedTo]['deleteFile'];
            if ($deleteFile) {
                unlink($upload->filePath);
            }
        }
        $downloadToken->delete();
    }
}
