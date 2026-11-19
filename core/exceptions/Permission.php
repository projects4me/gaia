<?php

/**
 * Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace Gaia\Exception;

/**
 * This class is reponsible for handling the exceptions related to creation of permissions.
 *
 * @author Rana Nouman <ranamnouman@gmail.com>
 * @package Gaia
 * @category Exception
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class Permission extends \Exception
{
    use SendsErrorResponse;

    /**
     * Extra data for the response, e.g. a 'suggestion'.
     *
     * @var array|null
     */
    protected $additionalData;

    /**
     * This function overrides php default exception class construct method and is used to
     * add some additional data into the exception.
     *
     * @method __construct
     */
    public function __construct($message = "", $code = 0, ?Throwable $previous = null, $additionalData = null)
    {
        // Call the parent constructor
        parent::__construct((string) $message, (int) $code, $previous);

        // Additional data specific to your exception
        $this->additionalData = $additionalData;
    }

    /**
     * Build the http response without sending it. An empty message leaves the body empty.
     *
     * @param \Phalcon\Http\Response|null $response the response to build on; a new one when null
     * @return \Phalcon\Http\Response
     */
    public function toResponse(?\Phalcon\Http\Response $response = null): \Phalcon\Http\Response
    {
        $body = $this->getMessage() ? [
            "error" => $this->getMessage(),
            "suggestion" => $this->additionalData['suggestion'] ?? null
        ] : null;
        return ErrorResponse::build(422, 'Access levels not allowed', $body, [], $response);
    }
}
