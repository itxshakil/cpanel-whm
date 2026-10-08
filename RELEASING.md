# Releasing

## Before the first release

1. On your machine: `composer install`, then `composer qa`. Fix anything it finds.
2. From a test Laravel app with the package installed from a path repository, run `php artisan whm:install` against a real WHM server, then `whm:doctor`, `whm:accounts`, `whm:account`, `whm:login` and `Whm::asUser(...)->uapi(...)` against a disposable account.
   Also make a UAPI call fail on purpose through `Whm::api()->apiDevelopmentTools()->cpanel(...)` (UAPI and cPanel API 2) and confirm it throws `UapiCallFailed`: cPanel's spec does not document that response, so its shape is unverified.
   Run a `Whm::batch()` with two commands, one with parameters, and confirm WHM accepts the repeated `command`, `command-1` parameters.
   If a second server is available, run `Whm::transfers()->migrate()` for one disposable account and poll `state()` until it finishes.
3. Create `github.com/itxshakil/cpanel-whm` (public, no README, licence or .gitignore, since they're already here), push `main`, and wait for every CI job to go green: Pint, PHPStan, the Composer validation and the PHP × Laravel matrix (prefer-lowest and highest).
4. In the repo settings, add the topics: `laravel`, `laravel-package`, `cpanel`, `whm`, `whm-api`, `uapi`, `web-hosting`, `php`.
5. Create these labels so the labeler and release-drafter work: `client`, `modules`, `console`, `testing`, `config`, `events`, `tests`, `documentation`, `ci`, `tooling`, `dependencies`, `meta`, `breaking-change`, `skip-changelog`, `question`.

## One-time repo settings (keeps Dependabot hands-off)

Run once, with the `gh` CLI logged in with the `workflow` scope (`gh auth refresh -h github.com -s workflow && gh auth setup-git`):

```bash
gh repo edit --enable-auto-merge --delete-branch-on-merge
gh api -X PUT repos/itxshakil/cpanel-whm/branches/main/protection --input - <<'JSON'
{
  "required_status_checks": { "strict": false, "contexts": ["CI passed"] },
  "enforce_admins": false,
  "required_pull_request_reviews": null,
  "restrictions": null
}
JSON
```

After that, Dependabot's monthly PR for GitHub Actions merges itself when CI is green. `enforce_admins: false` means you can still push straight to `main`.

## Each release

1. Make sure CI on `main` is green.
2. Move `## [Unreleased]` entries in `CHANGELOG.md` under a dated version heading and commit.
3. Tag and push:

    ```bash
    git tag -a v0.1.0 -m "v0.1.0"
    git push origin v0.1.0
    ```

4. The Release workflow checks that the tag installs cleanly without dev dependencies. Then publish the draft release that release-drafter prepared, or run `gh release create v0.1.0 --notes-from-tag`.
5. First release only: submit the repository at <https://packagist.org/packages/submit>, then check the GitHub hook is active (Packagist → your package → Settings), so new tags appear automatically.

## Versioning

- Patch: bug fixes, docs, tolerance for another WHM response shape.
- Minor: new typed methods, modules, commands or options.
- Major: a changed method signature or return type, a renamed config key, or an exception that moves in the hierarchy.

Until 1.0, minor versions may still change the public API; the changelog says so when they do. 1.0 ships once the package has run in production for six weeks and every documented WHM API 1 function has a typed method (see docs/roadmap.md).
