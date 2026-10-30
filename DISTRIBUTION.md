# Composer distribution

This public fork is published under the Composer package name `ryzemcbe/bedrock-protocol`.

Once the package has been registered on [Packagist](https://packagist.org/) and a tested release tag exists, developers can install it with:

```bash
composer require ryzemcbe/bedrock-protocol:<tested-release-version>
```

The PHP namespace remains `pocketmine\network\mcpe\protocol\` for compatibility.

The package declares an upstream package replacement at `self.version`; it is not a promise that all upstream versions have identical APIs.

Protocol releases should be tagged only after protocol unit tests and RyzeMP's multiversion integration tests pass.

**Release checklist:** validate `composer.json`, run dependency tests, create a SemVer tag, register/update this public GitHub repository in Packagist, and verify a clean installation without custom VCS configuration. Tags and Packagist registration are separate operations.
