# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- Setup full WordPress environment in GitHub Actions for PHPUnit tests.
- Added MySQL service to `phpunit-tests.yml`.
- Added steps to install WordPress Test Library and activate plugin in CI.

### Changed
- Removed Limited Mode fallback from `tests/bootstrap.php` to ensure tests run against a full WordPress environment.

