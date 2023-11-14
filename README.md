# mglaman/composer-drupal-lenient

Lenient with it, Drupal 10 with it.

## Why?

The Drupal community introduced a lenient Composer facade that modified the `drupal/core` constraint for packages. This
was done to remove a barrier with getting extensions installed via Composer to work on making modules Drupal 9 ready.

We hit the same problem, again. At DrupalCon Portland we sat down and decided a Composer plugin is the best approach.

See [Add a composer plugin that supports 'composer require-lenient' to support major version transitions](https://www.drupal.org/project/drupal/issues/3267143)

## Before
Imagine that you are using drupal/token and it is not yet compatible with Drupal 10. You try to upgrade to Drupal 10 via Composer!

```
composer require drupal/core-recommended:^10 drupal/core-composer-scaffold:^10 --with-all-dependencies
```
![image](https://github.com/mglaman/composer-drupal-lenient/assets/539205/a4950149-a7ae-4d03-a786-716ebaa7dee0)

Yikes! You are blocked. You don't want to go and fix the drupal/token module itself. How do you move forward?
 
## After

This thing to the rescue! Run a few commands to add this tool to your composer.json file, and then try again.

```
composer config repositories.lenient composer https://packages.drupal.org/lenient 
composer config --merge --json extra.drupal-lenient.allowed-list '["drupal/token"]'
composer require drupal/core-recommended:^10 drupal/core-composer-scaffold:^10 --with-all-dependencies
```

Voila! It's basically cheating and letting Composer "I know it's not technically compatible with Drupal 10, just download it anyway"
There's one more step. You'll need to [patch the info.yml](https://docs.cweagans.net/composer-patches/usage/defining-patches/) file on the contributed module, drupal/token in this case, to ensure that the
module can remain installed to the Drupal database. It is *very* likely that this patch already exists in the module's issue queue. 

## How

This subscribes to `PluginEvents::PRE_POOL_CREATE` and filters packages. This is inspired by `symfony/flex`, but it does
not filter out packages. It rewrites the `drupal/core` constraint on any package with a type of `drupal-*`,
excluding `drupal-core`. The constraint is set to `'^8 || ^9 || ^10'` for `drupal/core`.

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
