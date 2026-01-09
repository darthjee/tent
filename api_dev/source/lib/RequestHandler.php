<?php

namespace ApiDev;

class RequestHandler
{
    public function handle($request)
    {
        $response = $this->getResponse($request);
        $this->sendResponse($response);
    }

    private function getResponse($request)
    {
        $configurations = Configuration::getConfigurations();

        foreach ($configurations as $config) {
            if ($config->match($request)) {
                return $config->handle($request);
            }
        }

        return new MissingResponse();
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
