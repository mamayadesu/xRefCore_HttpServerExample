<?php

declare(ticks=1);

namespace Program;

use Data\String\ForegroundColors;
use HttpServer\Exceptions\ServerStartException;
use HttpServer\Request;
use HttpServer\Response;
use HttpServer\Server;
use IO\Console;
use Scheduler\AsyncTask;

class Main
{
    const WEBPATH = "C:\\httpserver\\"; // DOCUMENT ROOT

    private Server $server;

    public function __construct(array $args)
    {
        Console::WriteLine("Starting server");
        $this->server = new Server("0.0.0.0", 8080);

        $this->server->On("start", function(Server $server)
        {
            Console::WriteLine("Server started");
        });

        /**
         * #################
         * REQUEST HANDLER
         * #################
         */
        $this->server->On("request", function(Request $request, Response $response)
        {
            Console::WriteLine("[" . date("d.m.Y H:i:s", time()) . "] '" . $request->RequestUri . "' from " . $request->RemoteAddress . ":" . $request->RemotePort);

            /**
             * #################
             * REDIRECT TO index.html
             * #################
             */
            if ($request->RequestUri == "/" && file_exists(self::WEBPATH . "index.html"))
            {
                $request->RequestUri .= "index.html";
                $request->RequestUrl .= "/index.html";
                $request->PathInfo .= "index.html";
            }
            $path = $request->PathInfo;

            /**
             * #################
             * REMOVING "." AND ".." AND "//" "///" (etc.) FROM URI
             * #################
             */
            $pathSplit = explode('/', $path);
            $newPathSplit = [];
            foreach ($pathSplit as $element)
            {
                if ($element != "" && $element != ".." && $element != ".")
                {
                    $newPathSplit[] = $element;
                }
            }
            $newPath = implode(DIRECTORY_SEPARATOR, $newPathSplit);

            /**
             * #################
             * GETTING PREVIOUS PATH
             * #################
             */
            $prevPathSplit = $newPathSplit;
            if (count($prevPathSplit) > 0)
            {
                array_pop($prevPathSplit);
            }
            $prevDirectory = implode('/', $prevPathSplit);

            /**
             * #################
             * PATH TO TARGET FILE OR DIRECTORY ON LOCAL MACHINE
             * #################
             */
            $target = self::WEBPATH . rawurldecode($newPath);
            if (is_dir($target))
            {
                $dirContentPage = $this->GetDirContentPage($request->RequestUri, $prevDirectory, $target);
                $response->End($dirContentPage); // Print directory content
                return;
            }

            /**
             * #################
             * 404 NOT FOUND
             * #################
             */
            if (!file_exists($target))
            {
                $response->Status(404);
                $_404 = <<<HTML

<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
<hr>
<address>xRefCore Web Server</address>
</body></html>

HTML;

                $response->End($_404);
                return;
            }

            /**
             * #################
             * GETTING MIME TYPE
             * #################
             */
            $mime = mime_content_type($target);

            /**
             * #################
             * GETTING FILE CONTENT
             * #################
             */
            $content = file_get_contents($target);

            /**
             * #################
             * PRINTING RESULT
             * #################
             */
            $response->Status(200);
            $response->Header("Content-Type", $mime);
            /**
             * We'll send content using async task to do not block whole process,
             * because file content may be large
             */
            $params = new RequestAsyncHandlerParams($content, $response);
            new AsyncTask($this, 1, false, [$this, "RequestAsyncHandler"], $params);
        });

        $this->server->On("shutdown", function(Server $server)
        {
            Console::WriteLine("Server was shutdown");
        });

        /**
         * Starting HTTP-server asynchronously
         */
        $this->server->ClientNonBlockMode = true;
        try
        {
            $this->server->Start(true);
        }
        catch (ServerStartException $e)
        {
            Console::WriteLine("Failed to start server. " . $e->getMessage(), ForegroundColors::RED);
        }
        while(true)
        {
            time_nanosleep(0, 1000000); // don't let process stop
        }
    }

    /**
     * #################
     * GETTING DIRECTORY CONTENT PAGE
     * #################
     */
    private function GetDirContentPage(string $requestUri, string $prevDirectory, string $target) : string
    {
        /**
         * Adding slash to end of URI
         */
        $ru = str_split($requestUri);
        if ($ru[count($ru) - 1] != "/")
            $requestUri .= "/";

        $result = "
<html>
    <head>
        <title>Content of " . $requestUri . "</title>
    </head>
    <body>
        <table border>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Size</th>
                <th>Modified</th>
            </tr>
";

        /**
         * Scanning current directory
         */
        foreach (scandir($target) as $name)
        {
            /**
             * Getting full path to target directory on local machine to get file size
             */
            $fullPathToTarget = $target . DIRECTORY_SEPARATOR . $name;

            $targetType = "File";
            if (is_dir($fullPathToTarget))
                $targetType = "Directory";

            $size = "";
            if ($targetType == "File")
            {
                $st = "B";
                $filesize = filesize($fullPathToTarget);
                while ($st != "GB")
                {
                    if ($filesize >= 1024)
                    {
                        $filesize = round($filesize / 1024, 1);
                        switch ($st)
                        {
                            case "B":
                                $st = "KB";
                                break;

                            case "KB":
                                $st = "MB";
                                break;

                            case "MB":
                                $st = "GB";
                                break;
                        }
                    }
                    else
                    {
                        break;
                    }
                }
                $size = $filesize . " " . $st;
            }
            if ($name == "..")
            {
                $url = "/" . $prevDirectory;
            }

            /**
             * Removing "."
             */
            if ($name == ".")
            {
                continue;
            }

            /**
             * Adding this directory or file to <table>
             */
            $result .= "<tr><td><a href='" . $requestUri . $name . "'>" . $name . "</a></td><td>" . $targetType . "</td><td>" . $size . "</td><td>" . date("d.m.Y H:i:s", filemtime($fullPathToTarget)) . "</td></tr>";
        }

        $result .= "
        </table>
    </body>
</html>";
        return $result;
    }

    public function RequestAsyncHandler(AsyncTask $task, RequestAsyncHandlerParams $params) : void
    {
        $tile = $params->GetNext();

        if ($tile == "")
        {
            $params->Response->End();
            $task->Cancel();
            return;
        }
        $params->Response->PrintBody($tile);
    }
}