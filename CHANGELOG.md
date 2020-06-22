# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.2] - 2020-06-23
### Fixed
 - prevents *connections* from generating multiple database connections.

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

[Unreleased]: https://github.com/AymDev/Fregata/compare/v0.3.2...HEAD
[0.3.2]: https://github.com/AymDev/Fregata/compare/v0.3.1...v0.3.2
[0.3.1]: https://github.com/AymDev/Fregata/compare/v0.3.0...v0.3.1
[0.3.0]: https://github.com/AymDev/Fregata/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/AymDev/Fregata/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/AymDev/Fregata/releases/tag/v0.1.0