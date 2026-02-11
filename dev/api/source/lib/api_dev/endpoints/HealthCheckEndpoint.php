<?php

namespace ApiDev;

/**
 * Health check endpoint.
 *
 * Returns a simple JSON response indicating that the API is operational.
 */
class HealthCheckEndpoint extends Endpoint
{
    /**
     * Handles the health check request.
     *
     * @return Response JSON response with status "ok" and HTTP 200
     */
    public function handle(): Response
    {
        $body = json_encode(['status' => 'ok']);
        $headers = ['Content-Type: application/json'];

        return new Response($body, 200, $headers);
    }
}
