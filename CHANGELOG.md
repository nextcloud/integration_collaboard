# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

### Updated

- Use OAuth flow to authenticate
- Support localisation in German, French, Italian, Swedish and Spanish
- Support opening boards in Nextcloud
- Changed API and Domain URLs configuration. Removed user-level configuration.
- Use public API endpoints where possible @MB-Finski
- Login flow now matches that of the Collaboard website @MB-Finski

### Fixed

- Participating projects not being shown
- Projects being not ordered by last update
- Login no longer possible through all endpoints, use Authenticate instead @MB-Finski

## 1.0.6 - 2023-08-01

### Updated

- Updated collaboard APP API version

## 1.0.5 - 2023-07-13

### Fixed

- Fix wrong param when checking if Talk is enabled [#12](https://github.com/nextcloud/integration_collaboard/pull/12) @julien-nc

## 1.0.4 - 2023-04-28

### Added

- Added displaying notice of API error [#5](https://github.com/nextcloud/integration_collaboard/pull/5) @julien-nc

### Fixed

- Fix projects list loading [#4](https://github.com/nextcloud/integration_collaboard/pull/4) @andrey18106 
- Fix thumbnails [#6](https://github.com/nextcloud/integration_collaboard/pull/6) @julien-nc

### Updated

- Updated npm packages versions

## 1.0.3 – 2023-03-14
* update npm pkgs
* mention unsupported SSO
* add NC 27 compat
* add screenshots

## 1.0.0 – 2022-11-12
### Added
* the app
