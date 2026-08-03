# Quick Start with Docker

Pull the image and run it:

```yaml
services:
  proxy:
    image: darthjee/tent:latest
    ports:
      - "0.0.0.0:80:80"
    volumes:
      - ./proxy/static/:/var/www/html/static/
      - ./proxy_configuration/:/var/www/html/configuration/
    links:
      - my_backend:backend
      - my_frontend:frontend
    env_file: .env
```

The two key mounts are:
- `/var/www/html/static/` — static files Tent will serve directly.
- `/var/www/html/configuration/` — PHP rule files that define routing behavior.

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
