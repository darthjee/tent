<?php

namespace ApiDev;

class HealthCheckEndpoint extends Endpoint
{
    public function handle()
    {
        $body = json_encode(['status' => 'ok']);
        $headers = ['Content-Type: application/json'];
        
        return new Response($body, 200, $headers);
    }
}
