<?php

namespace Tent\Tests\Service;

require_once __DIR__ . '/../../../support/loader.php';

use PHPUnit\Framework\TestCase;
use Tent\Models\Response;
use Tent\Service\ResponseSender;

class ResponseSenderTest extends TestCase
{
    private array $emitted;
    private array $statuses;
    private ResponseSender $sender;

    protected function setUp(): void
    {
        $this->emitted = [];
        $this->statuses = [];
        $this->sender = new ResponseSender(
            function (string $line, bool $replace): void {
                $this->emitted[] = [$line, $replace];
            },
            function (int $code): void {
                $this->statuses[] = $code;
            }
        );
    }

    public function testSendEmitsEveryHeaderWithoutReplacing()
    {
        $headers = [
            'Content-Type: application/json',
            'Set-Cookie: access_token=abc; HttpOnly',
            'Set-Cookie: refresh_token=def; HttpOnly',
            'Set-Cookie: logged_in=true',
        ];
        $response = $this->buildResponse($headers);

        $this->send($response);

        $expected = array_map(fn ($header) => [$header, false], $headers);
        $this->assertSame($expected, $this->emitted);
    }

    public function testSendEmitsAllSetCookieHeaders()
    {
        $response = $this->buildResponse([
            'Set-Cookie: access_token=abc; HttpOnly',
            'Content-Type: application/json',
            'Set-Cookie: refresh_token=def; HttpOnly',
            'Set-Cookie: logged_in=true',
        ]);

        $this->send($response);

        $cookies = array_values(array_filter(
            array_column($this->emitted, 0),
            fn ($line) => str_starts_with($line, 'Set-Cookie:')
        ));
        $this->assertSame([
            'Set-Cookie: access_token=abc; HttpOnly',
            'Set-Cookie: refresh_token=def; HttpOnly',
            'Set-Cookie: logged_in=true',
        ], $cookies);
    }

    public function testSendEchoesBody()
    {
        $response = $this->buildResponse(['Content-Type: application/json']);

        $output = $this->send($response);

        $this->assertSame($response->body(), $output);
    }

    public function testSendEmitsStatusCode()
    {
        $response = new Response(['body' => '', 'httpCode' => 404, 'headers' => []]);

        $this->send($response);

        $this->assertSame([404], $this->statuses);
    }

    public function testSendWithNoHeadersEmitsNothing()
    {
        $response = $this->buildResponse([]);

        $this->send($response);

        $this->assertSame([], $this->emitted);
    }

    private function send(Response $response): string
    {
        ob_start();
        $this->sender->send($response);
        return ob_get_clean();
    }

    private function buildResponse(array $headers): Response
    {
        return new Response([
            'body' => '{"ok":true}',
            'httpCode' => 200,
            'headers' => $headers,
        ]);
    }
}
