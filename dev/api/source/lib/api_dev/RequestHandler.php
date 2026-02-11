<?php

namespace ApiDev;

/**
 * Handles incoming HTTP requests and dispatches them to appropriate endpoints.
 * 
 * Processes requests by matching them against registered route configurations
 * and sending the resulting response back to the client.
 */
class RequestHandler
{
    /**
     * Handles the incoming HTTP request.
     * 
     * Matches the request against registered routes, invokes the appropriate
     * endpoint handler, and sends the response to the client.
     * 
     * @param RequestInterface $request The HTTP request to handle
     * @return void
     */
    public function handle(RequestInterface $request): void
    {
        $response = $this->getResponse($request);
        $this->sendResponse($response);
    }

    /**
     * Finds and executes the appropriate route handler for the request.
     * 
     * Iterates through registered route configurations to find a matching route.
     * Returns a MissingResponse if no matching route is found.
     * 
     * @param RequestInterface $request The HTTP request to process
     * @return Response The HTTP response from the matched endpoint
     */
    private function getResponse(RequestInterface $request): Response
    {
        $configurations = Configuration::getConfigurations();

        foreach ($configurations as $config) {
            if ($config->match($request)) {
                return $config->handle($request);
            }
        }

        return new MissingResponse();
    }

    /**
     * Sends the HTTP response to the client.
     * 
     * Sets the HTTP status code, headers, and outputs the response body.
     * 
     * @param Response $response The response to send
     * @return void
     */
    private function sendResponse(Response $response): void
    {
        http_response_code($response->httpCode);
        foreach ($response->headerLines as $header) {
            header($header);
        }
        echo $response->body;
    }
}
