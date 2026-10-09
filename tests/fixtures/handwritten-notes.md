# Version 0.3.0

This release adds a **settings section** and fixes a few _small_ problems. See the [documentation](https://github.com/scottoffen/wp-github-updater#usage) for details.

> [!NOTE]
> The settings section needs WordPress 5.8 or later.

> [!WARNING]
> Back up your site before updating. `composer update` changes the bundled library.

## Changes

1. Added `Updater::capability()`
2. Added `PluginUpdater::settings()`
   - `render()` prints the section
   - `addPage()` adds a page under Settings
3. Removed ~~the old token form~~

| Method | Returns | Notes |
| --- | :---: | ---: |
| `render()` | `void` | Prints the section |
| `addPage()` | `void` | Adds a page |

![The settings section](https://github.com/scottoffen/wp-github-updater/assets/1/screenshot.png)

```php
Updater::plugin(__FILE__)
    ->fromGitHub('owner/repository')
    ->register();
```

---

Thanks to everyone who tested this release!
