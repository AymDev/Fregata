# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2021-05-17
### Removed
 - `v0` code (**Fregata** has been completely rewritten)
 - **PHP-DI**
- **Doctrine DBAL** integration is not coupled with the migrators anymore.

### Changed
 - multiple migrations are registerable per project

### Added
 - a **migration registry** holds the migrations list
 - CLI commands listing migrations and their content
 - migrator components: **puller**, **pusher** and **executor**
 - **Symfony**'s **DependencyInjection** component as *service container*
 - a *kernel* to configure the framework
 - a **migration context** service to get migration metadata
 - *before* and *after* **tasks**
 - **migration options** readable in the *context*
 - migrations can extend others and get the same *options*, *tasks* and *migrators* as the *parent*
 - a **Doctrine DBAL** integration to keep foreign key constraints

## [0.3.1] - 2020-06-07
### Fixed
 - adds blank line in CLI output to avoid readability issues

## [0.3.0] - 2020-06-07
### Added
 - service container (**PHP-DI**) to autowire migrators constructor arguments
 - console command shows migration progress bar
 - optional batch fetching for large datasets
 - foreign key preservation system

## [0.2.0] - 2020-05-23
### Added
 - Composer package binary

## [0.1.0] - 2020-05-21
### Added
 - Composer setup
 - Connection abstract wrapper class
 - Migrator system with interface and abstract class

[Unreleased]: https://github.com/AymDev/Fregata/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/AymDev/Fregata/compare/v0.3.1...v1.0.0
[0.3.1]: https://github.com/AymDev/Fregata/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/AymDev/Fregata/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/AymDev/Fregata/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/AymDev/Fregata/releases/tag/v0.1.0