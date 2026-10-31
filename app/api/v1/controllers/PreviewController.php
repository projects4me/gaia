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
    /** @var string[] non-executable raster types that may be served inline */
    const PREVIEWABLE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

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

        // The token is consumed up front, so a second request (or a failure below) cannot reuse it
        $downloadToken = Downloadtoken::consume($id);

        if ($downloadToken) {
            $logger->info("Consumed the download token");

            $upload = Upload::findFirst([
                'conditions' => 'id = :id:',
                'bind' => ['id' => (string) $downloadToken->uploadId],
            ]);

            // Only raster images are previewed (no SVG: it can carry script); anything else looks like an invalid token
            if ($upload && in_array((string) $upload->fileMime, self::PREVIEWABLE_TYPES, true)) {
                if (file_exists($upload->filePath)) {
                    $logger->info("File found, preparing preview");
                    $this->response->setStatusCode(200, "OK");
                    $this->response->setContentType($upload->fileMime);
                    $this->response->setHeader("Content-Disposition", 'inline');
                    $this->response->setHeader("X-Content-Type-Options", 'nosniff');
                    $this->response->setHeader("Cache-Control", 'no-store');
                    $this->response->setHeader("Content-Length", (string) filesize($upload->filePath));
                    $this->response->setContent(file_get_contents($upload->filePath));

                    $logger->debug("-Gaia.MVC.Controller.preview->getAction");
                    return $this->response;
                }
                $logger->critical("File mentioned in the data not found");
                throw new \Gaia\Exception\FileNotFound("File not found");
            }
        }
        throw new \Gaia\Exception\ResourceNotFound();
    }

}
