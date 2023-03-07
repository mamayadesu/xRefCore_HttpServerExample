<?php

namespace Program;

use HttpServer\Request;
use HttpServer\Response;
use Scheduler\IAsyncTaskParameters;
use TypeError;

class RequestAsyncHandlerParams implements IAsyncTaskParameters
{
    public Request $Request;
    public Response $Response;

    /**
     * @var resource
     */
    public $File;

    public function __construct($f, Request $request, Response $response)
    {
        $t = gettype($f);
        if ($t != "resource")
        {
            throw new TypeError("Argument 1 passed to Program\RequestAsyncHandlerParams::__construct() must be an resource, " . $t . " given");
        }

        $this->File = $f;
        $this->Request = $request;
        $this->Response = $response;
    }

    public function GetNext() : string
    {
        /**
         * Reading next 16KB data of file
         */
        return fread($this->File, 16 * 1024);
    }
}
