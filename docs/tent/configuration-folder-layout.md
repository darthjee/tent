# Configuration Folder Layout

Tent reads from `/var/www/html/configuration/` inside the container. The expected entry point is `configure.php`. A typical layout:

```
proxy_configuration/
├── configure.php          # entry point — loads rule files
└── rules/
    ├── backend.php        # routing rules for the API
    └── frontend.php       # routing rules for the frontend
```

## `configure.php`

This is the file Tent boots from. Its only job is to include the rule files:

```php
<?php

use Tent\Configuration;

require_once __DIR__ . '/rules/frontend.php';
require_once __DIR__ . '/rules/backend.php';
```

You can split rules into as many files as makes sense for your project — the only requirement is that `configure.php` requires them all.

[← Back to How to Use darthjee/tent](../HOW_TO_USE_DARTHJEE-TENT.md)
