<?php

namespace Program;

use HttpServer\Response;
use Scheduler\IAsyncTaskParameters;

class RequestAsyncHandlerParams implements IAsyncTaskParameters
{
    /**
     * @var array<string>
     */
    public array $Tiles;

    public Response $Response;

    public function __construct(string $fileContent, Response $response)
    {
        /**
         * Split data to 64KB packages
         */
        $this->Tiles = str_split($fileContent, 65536);
        $this->Response = $response;
    }

    public function GetNext() : string
    {
        if (count($this->Tiles) == 0)
        {
            return "";
        }
        return array_shift($this->Tiles);
    }
}