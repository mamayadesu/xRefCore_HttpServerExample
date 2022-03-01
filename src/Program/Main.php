<?php

namespace Program;

use Data\String\BackgroundColors;
use Data\String\ForegroundColors;
use HttpServer\Exceptions\ServerStartException;
use HttpServer\Request;
use HttpServer\Response;
use HttpServer\Server;
use IO\Console;

class Main
{
    private Server $server;

    public function __construct(array $args)
    {
        Console::WriteLine("Starting server");
        $this->server = new Server("127.0.0.1", 8080);

        $this->server->On("start", function(Server $server) {
            Console::WriteLine("Server started");
        });

        $this->server->On("request", function(Request $request, Response $response) {
            Console::Write("Request received. Content: ", ForegroundColors::DARK_PURPLE, BackgroundColors::YELLOW);
            Console::WriteLine($request->GetRawContent());

            if ($request->RequestUri == "/stop")
            {
                $this->server->Shutdown();
            }
            $response->Status(200);
            $response->End("<h1>It works!</h1>");
        });

        $this->server->On("shutdown", function(Server $server) {
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
}