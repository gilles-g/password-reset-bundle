# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added
- **Password Reset Feature**: Complete password reset functionality with customizable email templates
  - `PasswordResetService` for managing password reset flow
  - `PasswordResetManager` for secure token generation and validation
  - `PasswordResetToken` value object with selector/verifier pattern
  - Default HTML and text email templates
  - Configurable email settings (from, subject, templates)
  - Configurable token lifetime (default: 1 hour)
  - Security features: cryptographically secure tokens, constant-time comparison, single-use tokens
  - Comprehensive documentation with usage examples
  - Unit tests (21 tests) and integration tests (3 tests)

### Dependencies
- Added `symfony/mailer` (^6.0|^7.0)
- Added `symfony/mime` (^6.0|^7.0)
- Added `twig/twig` (^3.0)
- Added `symfony/twig-bundle` (^6.0|^7.0) to dev dependencies

### Documentation
- Added password reset configuration guide
- Added usage examples and API reference
- Added security best practices section
- Added template customization guide
- Enhanced README with comprehensive examples

## Previous Releases

See Git history for previous changes.
