# mglaman/composer-drupal-lenient

Lenient with it, Drupal 10 with it.

## Why?

The Drupal community introduced a lenient Composer facade that modified the `drupal/core` constraint for packages. This
was done to remove a barrier with getting extensions installed via Composer to work on making modules Drupal 9 ready.

We hit the same problem, again. At DrupalCon Portland we sat down and decided a Composer plugin is the best approach.

See [Add a composer plugin that supports 'composer require-lenient' to support major version transitions](https://www.drupal.org/project/drupal/issues/3267143)

## How

This subscribes to `PluginEvents::PRE_POOL_CREATE` and filters packages. This is inspired by `symfony/flex`, but it does
not filter out packages. It rewrites the `drupal/core` constraint on any package with a type of `drupal-*`,
excluding `drupal-core`. The constraint is set to `'^8 || ^9 || ^10 || ^11'` for `drupal/core`.

## Try it

Setup a fresh Drupal 10 site with this plugin (remember to press `y` for the new `allow-plugins` prompt.)

```shell
composer create-project drupal/recommended-project:^10@alpha d10
cd d10
composer config minimum-stability dev
composer require mglaman/composer-drupal-lenient
```

The plugin only works against specified packages. To allow a package to have a lenient Drupal core version constraint,
you must add it to `extra.drupal-lenient.allowed-list`. The following is an example to add Token via the command line 
with `composer config`

```shell
composer config --merge --json extra.drupal-lenient.allowed-list '["drupal/token"]'
```

Now, add a module that does not have a D10 compatible release!

```shell
composer require drupal/token:1.10.0
```

🥳 Now you can use [cweagans/composer-patches](https://github.com/cweagans/composer-patches) to patch the module for Drupal 10 compatibility!

## Support when `composer.lock` removed

This plugin must be installed globally if your project's `composer.lock` file is removed.

```shell
composer global config --no-plugins allow-plugins.mglaman/composer-drupal-lenient true
composer global require mglaman/composer-drupal-lenient
```

**Warning**: this means the plugin will run on all Composer commands. This is not recommended, but it is the only way 
the plugin can work when `composer.lock` is removed.
