# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]
### Fixed
- Resolved a critical SQL column collision in the scheduler where `SELECT *` joins caused the Template ID to overwrite the Schedule ID, potentially updating the wrong database records.
