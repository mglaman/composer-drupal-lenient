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
excluding `drupal-core`.

## Next

- [ ] TESTS ✅
- [ ] Don't use MatchAllConstraint but set <= to current `drupal/core` locked constraint
- [ ] Don't run on all Drupal extensions, only ones in an allowed list in root package's `extra`
