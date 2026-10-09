# Contributing

Notes for the maintainer on versioning, releasing, and Packagist.

## Versioning

The only versioning of this package comes from the git tag. The package `composer.json` must not have a `version` key. Packagist reads versions from the GitHub tags, and the release workflow is designed to fail if any `version` key is present.

Versions must be semantic version numbers, and the GitHub tag is the version with a leading `v`, such as `v1.2.0`. A version with a suffix, such as `1.0.0-beta.1`, is a pre-release.

## Releasing

Releases are not created automatically when merging to the `main` branch. Releases must be created from the Actions tab in GitHub.

1. Open the Release workflow and click "Run workflow".
2. Enter the version, such as `1.2.0`. The leading `v` is optional.
3. Check "dry run" to run the workflow without creating anything. Run it again with the box clear to create the release.

The workflow checks that the run is on the default branch, that the version is well formed and not already tagged, and that `composer.json` is valid and has no `version` key. It then runs the tests on PHP 8.1 through 8.4 against that exact commit. If everything passes, it creates the tag and a GitHub release with generated notes.

## Packagist

Packagist builds the package page from the tags in the GitHub repository. Some initial setup is required.

> [!NOTE]
> A Packagist account is required. If you don't already have a Packagist account, it is recommended to create one using your GitHub account. This allows the webhooks needed to be created automatically by Packagist.

1. Submit the repository URL at https://packagist.org/packages/submit. The package name comes from `composer.json`: `scottoffen/markdown-converter`.
2. Turn on auto-updates, either by connecting GitHub in your Packagist profile settings, or by adding a webhook in the repository settings with the payload URL and token from your Packagist profile and the content type `application/json`.

After that, each release tag appears on Packagist without further work.

If a release doesn't appear, open the package page on Packagist. It shows a warning when the repository isn't set up for auto-updates. Without the webhook, Packagist only checks for new tags on a schedule, not right away.
