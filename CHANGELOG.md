# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Added

- Filament 3.2+ compatibility alongside Filament 5 (RGB palettes on v3, OKLCH on v5)
- `FilamentCompat` helper for panel / color-format differences

### Changed

- Composer constraint: `filament/filament: ^3.2|^5.0`, PHP `^8.1`

## [1.0.0] - 2026-07-23

### Added

- Color Themes navigation item and page for Filament panels
- Default themes: ForestGreen, Office Blue, Midtone
- Persistent middleware that applies the selected primary color
- English and Russian translations
- Configurable session key, default theme, and navigation options
- Filament 5 support
