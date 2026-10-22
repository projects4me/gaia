<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\MVC\REST\Controllers;

use Gaia\Libraries\Utils\TranslationCatalog;

/**
 * Translation controller
 *
 * Public (no token) app routes, like /image: Prometheus loads its labels before
 * anyone signs in.
 *  - GET /translations           the supported locales, e.g. ["en-us"]
 *  - GET /translations/{locale}  the nested translation document of a locale
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package Gaia\MVC\REST\Controllers
 * @category Controller
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class TranslationController extends \Phalcon\Mvc\Controller
{
    /**
     * Translation endpoints are not action-ACL managed.
     *
     * @var bool
     */
    protected $aclAllowed = false;

    /**
     * @return \Phalcon\Http\Response
     */
    public function listAction()
    {
        $this->response->setStatusCode(200, "OK");
        $this->response->setJsonContent(TranslationCatalog::fromConfig()->locales());

        return $this->response;
    }

    /**
     * @return \Phalcon\Http\Response
     * @throws \Gaia\Exception\ResourceNotFound
     */
    public function getAction()
    {
        $document = TranslationCatalog::fromConfig()->document($this->dispatcher->getParam('locale'));

        if ($document === null) {
            throw new \Gaia\Exception\ResourceNotFound("Locale not supported");
        }

        $this->response->setStatusCode(200, "OK");
        $this->response->setContentType('application/json', 'UTF-8');
        $this->response->setContent($document);

        return $this->response;
    }
}
