# Drupal Lenient Composer Plugin

Lenient with it, Drupal 11 with it.

## Why?

The Drupal community introduced a lenient Composer facade that modified the `drupal/core` constraint for packages. This
was done to remove a barrier with getting extensions installed via Composer to work on making modules Drupal 9 ready.

We hit the same problem, again. At DrupalCon Portland we sat down and decided a Composer plugin is the best approach.

See [Add a composer plugin that supports 'composer require-lenient' to support major version transitions](https://www.drupal.org/project/drupal/issues/3267143).

Drupal documentation page: [Using Drupal's Lenient Composer Endpoint](https://www.drupal.org/docs/develop/using-composer/using-drupals-lenient-composer-endpoint).

## How

This subscribes to `PluginEvents::PRE_POOL_CREATE` and filters packages. This is inspired by `symfony/flex`, but it does
not filter out packages. It rewrites the `drupal/core` constraint on any package with a type of `drupal-*`,
excluding `drupal-core`. The constraint is set to `'^8 || ^9 || ^10 || ^11 || ^12'` for `drupal/core`.

## Try it

Set up a fresh Drupal 11 site with this plugin (remember to press `y` for the new `allow-plugins` prompt.)

```shell
composer create-project drupal/recommended-project d11
cd d11
composer require mglaman/composer-drupal-lenient
```

The plugin only works against specified packages. To allow a package to have a lenient Drupal core version constraint,
you must add it to `extra.drupal-lenient.allowed-list`. The following is an example to add Simplenews via the command line 
with `composer config`

```shell
composer config --merge --json extra.drupal-lenient.allowed-list '["drupal/simplenews"]'
```

Now, add a module that does [not have a Drupal 11 compatible](https://dev.acquia.com/drupal11/deprecation_status/projects?next_step=Fix%20deprecation%20errors%20found) release!

```shell
composer require drupal/simplenews
```

🥳 Now you can use [cweagans/composer-patches](https://github.com/cweagans/composer-patches) to patch the module for Drupal 11 compatibility!

For a quick start, allow installing the module by installing [Backward Compatibility](https://www.drupal.org/project/backward_compatibility):

> Backward Compatibility allows you to install old Drupal modules in current Drupal.

Alternatively, manually add the latest version in the module `*.info.yml` file:

```shell
core_version_requirement: ^9.3 || ^10 || ^11
```

## Allowing all packages

If you want to allow all packages to have a lenient Drupal core version constraint, you can set `extra.drupal-lenient.allow-all` to `true`.

```shell
composer config --json extra.drupal-lenient.allow-all true
```

Using `allow-all` allows you to install any package without needing to add it to the `allowed-list`.

## Support when `composer.lock` removed

This plugin must be installed globally if your project's `composer.lock` file is removed.

```shell
composer global config --no-plugins allow-plugins.mglaman/composer-drupal-lenient true
composer global require mglaman/composer-drupal-lenient
```

**Warning**: this means the plugin will run on all Composer commands. This is not recommended, but it is the only way 
the plugin can work when `composer.lock` is removed.
