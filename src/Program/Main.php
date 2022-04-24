<?php

namespace Program;

use Data\String\ForegroundColors;
use HttpServer\Exceptions\ServerStartException;
use HttpServer\Request;
use HttpServer\Response;
use HttpServer\Server;
use IO\Console;

class Main
{
    const WEBPATH = "/var/httpserverexample/"; // DOCUMENT ROOT

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
             * SHUTDOWN SERVER ON http://yousite.example/*shutdown
             * #################
             */
            if ($request->RequestUri == "/*shutdown")
            {
                $response->End("Server shutting down");
                $this->server->Shutdown();
                return;
            }

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
            $target = self::WEBPATH . $newPath;
            if (is_dir($target))
            {
                $dirContentPage = $this->GetDirContentPage($request->RequestUri, $prevDirectory, $target);
                $response->End($dirContentPage); // Print directory content
                return;
            }

            /**
             * #################
             * GETTING FILE EXTENSION AND MIME TYPE
             * #################
             */
            $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
            $mime = "octet/stream";
            switch ($extension)
            {
                case "css":
                    $mime = "text/css";
                    break;

                case "js":
                    $mime = "application/javascript";
                    break;

                case "txt":
                    $mime = "text/plain";
                    break;

                case "jpg":
                case "jpeg":
                    $mime = "image/jpeg";
                    break;

                case "gif":
                    $mime = "image/gif";
                    break;

                case "png":
                    $mime = "image/png";
                    break;

                case "htm":
                case "html":
                    $mime = "text/html";
                    break;

                case "doc":
                case "dot":
                    $mime = "application/msword";
                    break;

                case "pdf":
                    $mime = "application/pdf";
                    break;

                case "mp3":
                    $mime = "audio/mpeg";
                    break;

                case "wav":
                    $mime = "audio/x-wav";
                    break;

                case "bmp":
                    $mime = "image/bmp";
                    break;

                case "ico":
                    $mime = "image/x-icon";
                    break;

                case "mp4":
                    $mime = "video/mp4";
                    break;

                case "avi":
                    $mime = "video/x-msvideo";
                    break;
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
            $response->End($content);
        });

        $this->server->On("shutdown", function(Server $server)
        {
            Console::WriteLine("Server was shutdown");
        });

        try
        {
            $this->server->Start();
        }
        catch (ServerStartException $e)
        {
            Console::WriteLine("Failed to start server. " . $e->getMessage(), ForegroundColors::RED);
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
}