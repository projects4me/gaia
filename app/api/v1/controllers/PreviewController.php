<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace  Gaia\MVC\REST\Controllers;

use Gaia\MVC\Models\Downloadtoken;
use Gaia\MVC\Models\Upload;

/**
 * Preview controller
 *
 * Serves an image inline for a single-use download token created by the
 * authenticated GET /upload?id=X&download=true call.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Foundation
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PreviewController extends \Phalcon\Mvc\Controller
{

    /**
     * Retrieve the image the token points to and return it inline
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

        $downloadToken = Downloadtoken::findFirst([
            'conditions' => 'downloadToken = :token:',
            'bind' => ['token' => (string) $id],
        ]);

        if ($downloadToken) {
            $logger->info("Found the download token");

            $expiry = new \DateTime($downloadToken->expires, new \DateTimeZone('UTC'));
            $now = new \DateTime('now', new \DateTimeZone('UTC'));

            // If the download link has not expired
            if ($now->getTimestamp() - $expiry->getTimestamp() <= 0) {
                $upload = Upload::findFirst([
                    'conditions' => 'id = :id:',
                    'bind' => ['id' => (string) $downloadToken->uploadId],
                ]);

                // Only images are previewed; anything else looks like an invalid token
                if ($upload && strpos((string) $upload->fileMime, 'image/') === 0) {
                    if (file_exists($upload->filePath)) {
                        $logger->info("File found, preparing preview");
                        $this->response->setStatusCode(200, "OK");
                        $this->response->setContentType($upload->fileMime);
                        $this->response->setHeader("Content-Disposition", 'inline');
                        $this->response->setHeader("X-Content-Type-Options", 'nosniff');
                        $this->response->setHeader("Content-Length", (string) filesize($upload->filePath));
                        $this->response->setContent(file_get_contents($upload->filePath));

                        // Single use
                        $downloadToken->delete();
                        $logger->debug("-Gaia.MVC.Controller.preview->getAction");
                        return $this->response;
                    }
                    $logger->critical("File mentioned in the data not found");
                    $downloadToken->delete();
                    throw new \Gaia\Exception\FileNotFound("File not found");
                }
            } else {
                $logger->info("Expired download token provided");
            }
            $downloadToken->delete();
        }
        throw new \Gaia\Exception\ResourceNotFound();
    }

}
