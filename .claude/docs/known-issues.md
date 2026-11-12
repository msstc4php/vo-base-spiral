# Known issues

## `spiral/composer-publish-plugin`

`spiral/framework` requires a Composer plugin; `allow-plugins` sets it to `false` (a library publishes nothing).
Without an explicit entry `composer update` aborts.
