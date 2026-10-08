# Plan: Multiple Set-Cookie headers collapse to the last one in send_response

Issue: [295-multiple-set-cookie-headers-collapse-to-the-last-one-in-send-response.md](../../issues/295-multiple-set-cookie-headers-collapse-to-the-last-one-in-send-response.md)

## Overview
Move `send_response` out of `index.php` into a testable `Tent\Service\ResponseSender` class that emits every response header with `replace = false`, so repeated headers such as `Set-Cookie` all reach the client.

See [tent.md](tent.md) for the full plan.
