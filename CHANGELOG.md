# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Improved
- 2024-05-23: Optimized `AIPS_Logger::get_logs` to use tail reading for large log files, improving performance from O(N) to O(1) for files larger than 100KB.

### Fixed
- 2024-05-22: Removed redundant HTTP response code check in `AIPS_Generator::generate_and_upload_featured_image` to improve code quality and maintainability.
