<?php

namespace ApiDev;

class RequestHandler
{
    public function handle($request)
    {
        $configurations = Configuration::getConfigurations();

        foreach ($configurations as $config) {
            if ($config->match($request)) {
                $response = $config->handle($request);
                $this->sendResponse($response);
                return;
            }
        }

        return null;
    }

    private function sendResponse($response)
    {
        http_response_code($response->httpCode);
        foreach ($response->headerLines as $header) {
            header($header);
        }
        echo $response->body;
    }
}
