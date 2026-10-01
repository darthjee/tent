# Plan: StaticFileHandler: emit ETag / Last-Modified and answer conditional GETs with 304

Issue: [289-staticfilehandler-emit-etag-last-modified-and-answer-conditional-gets-with-304.md](../../issues/289-staticfilehandler-emit-etag-last-modified-and-answer-conditional-gets-with-304.md)

## Overview
Add an opt-in `conditional` option to the `static` handler. When it is on, `GET` responses carry `ETag` / `Last-Modified` validators, and matching `If-None-Match` / `If-Modified-Since` requests get an early, bodyless `304 Not Modified`. All work is inside `source/` plus the handler docs.

See [tent.md](tent.md) for the full plan.
