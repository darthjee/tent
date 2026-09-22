# Install GD, exif and upload limits

Add GD with JPEG and PNG support, the bundled `exif` extension, and a PHP ini drop-in that raises the upload limits. Do this in both the production image and the dev/test base image. Oak's proxy extension resizes uploaded phone photos in-process and needs EXIF orientation. The stock limits (`upload_max_filesize=2M`, `post_max_size=8M`) reject typical phone photos.

In both Dockerfiles, extend the existing `apt-get install` + `docker-php-ext-install` `RUN` (keep it one layer, and keep the `rm -rf /var/lib/apt/lists/*` cleanup):

- apt packages: add `libjpeg62-turbo-dev libpng-dev`;
- before `docker-php-ext-install`: `docker-php-ext-configure gd --with-jpeg`;
- `docker-php-ext-install`: add `gd exif` to the existing list (`zip pdo pdo_mysql gd exif`).

Then, still as root (before `USER app`), write the ini drop-in:

```dockerfile
RUN printf 'upload_max_filesize=20M\npost_max_size=25M\n' > /usr/local/etc/php/conf.d/tent.ini
```

Do not set `memory_limit`.

## Files to Change
- `dockerfiles/tent/Dockerfile` — add the GD/exif libs and extension install, and write `/usr/local/etc/php/conf.d/tent.ini`.
- `dockerfiles/dev_tent-base/Dockerfile` — same changes in the `base` stage, so `tent-test` and `dev_tent` inherit them.
