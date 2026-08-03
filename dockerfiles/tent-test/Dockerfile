# Use a base image from Dockerfile.tent-base
FROM darthjee/dev_tent-base:0.0.2

# Bake in Tent's own source (webroot + core classes) and full require-dev
# tooling (phpunit, pcov, phpcs, phpmd, phpdocumentor), so extension authors
# can run PHPUnit against their custom matchers/middlewares/handlers.
COPY --chown=app:app ./composer.* /home/app/app/
COPY --chown=app:app ./source/ /home/app/app/source/

RUN composer install; \
    composer dump-autoload

# Bundled test helper classes (DummyRequestMiddleware, QuickResponseMiddleware,
# DummyResponseMiddleware, FileSystemUtils, RequestToBodyHandler), reused as-is
# at the same relative path they live at in the Tent repo (tests/ under the
# app root), so their existing relative requires keep working unchanged.
COPY --chown=app:app ./tests/support/ /home/app/app/tests/support/

# Default PHPUnit wiring: bootstrap loads composer's autoloader then, if
# mounted, the extension's own loader.php; the default testsuite points at
# the extension_tests mount.
COPY --chown=app:app --from=tent-test-assets bootstrap.php /home/app/app/bootstrap.php
COPY --chown=app:app --from=tent-test-assets phpunit.xml /home/app/app/phpunit.xml

CMD ["vendor/bin/phpunit"]
