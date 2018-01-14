# Changelog

All notable changes to `colinodell/json5` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased][unreleased]

## [1.0.4] - 2018-01-14
### Changed
 - Modified the internal pointer and string manipulations to use bytes instead of characters for better performance (#4)

## [1.0.3] - 2018-01-14
### Fixed
 - Fixed check for PHP 7+

## [1.0.2] - 2018-01-14
This release contains massive performance improvements of 98% or more, especially for larger JSON inputs!

### Added
 - On PHP 7.x: parser will try using `json_decode()` first in case normal JSON is given, since this function is much faster (#1)

### Fixed
 - Fixed multiple performance issues (#1)
 - Fixed bug where `JSON_OBJECT_AS_ARRAY` was improperly taking priority over `assoc` in some cases

## [1.0.1] - 2017-11-11
### Removed
 - Removed accidentally-public constant

## 1.0.0 - 2017-11-11
### Added
 - Initial commit

[unreleased]: https://github.com/colinodell/json5/compare/1.0.4...HEAD
[1.0.4]: https://github.com/colinodell/json5/compare/1.0.3...1.0.4
[1.0.3]: https://github.com/colinodell/json5/compare/1.0.2...1.0.3
[1.0.2]: https://github.com/colinodell/json5/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/colinodell/json5/compare/1.0.0...1.0.1
