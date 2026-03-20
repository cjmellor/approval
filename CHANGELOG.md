# Changelog

All notable changes to `approval` will be documented in this file.

## v2.0.0 - 2026-03-20

### Breaking Changes

- **`thenDo()` renamed to `thenCustom()`** — The callback parameter was removed (it was silently discarded). Use an `ApprovalExpired` event listener for custom expiration logic.
- **`ModelRolledBackEvent` renamed to `ModelRolledBack`** — Consistent naming with all other event classes.
- **Facade removed** — `Cjmellor\Approval\Facades\Approval` has been removed. Use `Cjmellor\Approval\Models\Approval` directly.
- **Config keys flattened** — `config('approval.approval.approval_pivot')` is now `config('approval.approval_pivot')`. Re-publish your config file.
- **Event `$approval` property typed as `Approval`** — Previously typed as `Illuminate\Database\Eloquent\Model`, now typed as `Cjmellor\Approval\Models\Approval`.
- **`pending()` scope excludes custom states** — If you use configurable states, `Approval::pending()` now only returns genuinely pending approvals (not those with a custom state set). Use `whereState('pending')` for the old behaviour.
- **Expiration actions use `ExpirationAction` enum** — The `expiration_action` column is now cast to `Cjmellor\Approval\Enums\ExpirationAction`.

### New Features

- **`ExpirationAction` enum** — Type-safe expiration actions (`Reject`, `Postpone`, `Custom`) replacing raw strings.
- **`ApprovalEvent` base class** — All events now extend a shared abstract class with typed `Approval $approval` and `?Authenticatable $user` properties.
- **`ApprovalStatus::values()` helper** — Returns all standard state values as an array.
- **`actioned_by` tracking** — Expired approvals now record who/what processed them.

### Improvements

- **100% test coverage** — 74 tests, 208 assertions covering every line.
- **Events fire after DB writes** — State change events now dispatch only after the database update succeeds, preventing listeners from acting on uncommitted state.
- **`processExpired()` resilience** — Uses `chunkById()` for bounded memory, per-approval error handling with `report()`, and filters to pending-only approvals.
- **Duplicate detection scoped to model** — `approvalModelExists()` now filters by `approvalable_type` and `approvalable_id`, preventing cross-model false positives.
- **Null guards on `rollback()` and `approve()`** — Clear error messages when the related model has been deleted.
- **`getState()` null safety** — Fixed a bug where `getState()` returned `null` instead of the standard state value after the v2 migration.
- **`callCastAttribute` uses `getCasts()`** — Supports both property and method-based cast definitions (Laravel 11+).
- **`class_uses_recursive()`** — Factory trait now detects `MustBeApproved` on parent classes.
- **`json_decode` with `JSON_THROW_ON_ERROR`** — Malformed JSON now fails loudly instead of silently becoming null.
- **Schema introspection removed from boot** — No more per-request `Schema::hasTable()`/`Schema::hasColumn()` queries.
- **Explicit `$fillable`** — Replaced `$guarded = []` with an explicit list of mass-assignable columns.
- **Migration `down()` methods** — All newer migrations now include idempotent rollback methods.
- **Laravel 13 support** — Requires `illuminate/contracts ^11.0|^12.0|^13.0`.

## v1.6.6 - 2025-02-26

### What's Changed

* Laravel 12.x Compatibility by @laravel-shift in https://github.com/cjmellor/approval/pull/77

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.6.5...v1.6.6

## v1.6.5 - 2025-02-08

### What's Changed

* feature(events): add new ApprovalCreated event by @cjmellor in https://github.com/cjmellor/approval/pull/76

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.6.4...v1.6.5

## v1.6.4 - 2025-02-04

### What's Changed

* Fix Type Error by @mojowill in https://github.com/cjmellor/approval/pull/73

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.6.3...v1.6.4

## v1.6.3 - 2025-01-30

### What's Changed

* build(deps): Bump dependabot/fetch-metadata from 2.2.0 to 2.3.0 by @dependabot in https://github.com/cjmellor/approval/pull/71
* Move spatie package tools to require instead of require-dev by @mojowill in https://github.com/cjmellor/approval/pull/72

### New Contributors

* @mojowill made their first contribution in https://github.com/cjmellor/approval/pull/72

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.6.2...v1.6.3

## v1.6.2 - 2024-12-04

### What's Changed

* Fixes foreign key by @Temepest74 in https://github.com/cjmellor/approval/pull/70

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.6.1...v1.6.2

## v1.6.1 - 2024-11-10

### What's Changed

* build(deps): Bump dependabot/fetch-metadata from 2.1.0 to 2.2.0 by @dependabot in https://github.com/cjmellor/approval/pull/65
* Add comprehensive test for nested array attributes in approval process by @cjmellor in https://github.com/cjmellor/approval/pull/69

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.6.0...v1.6.1

## v1.6.0 - 2024-06-12

### What's Changed

* build(deps): Bump dependabot/fetch-metadata from 1.6.0 to 2.0.0 by @dependabot in https://github.com/cjmellor/approval/pull/61
* build(deps): Bump dependabot/fetch-metadata from 2.0.0 to 2.1.0 by @dependabot in https://github.com/cjmellor/approval/pull/62
* Factory support by @Temepest74 in https://github.com/cjmellor/approval/pull/64

### New Contributors

* @Temepest74 made their first contribution in https://github.com/cjmellor/approval/pull/64

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.5.0...v1.6.0

## v1.5.0 - 2024-03-18

### What's Changed

* feat: Extract Foreign Key from Payload by @cjmellor in https://github.com/cjmellor/approval/pull/57

> [!IMPORTANT]
This release requires that you're using >= PHP 8.2. If you're not, stick to the previous version.

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.4.5...v1.5.0

## v1.4.5 - 2024-02-28

### What's Changed

* fix: Rolling Back Model Fields with JSON Data by @cjmellor in https://github.com/cjmellor/approval/pull/55
* Laravel 11.x Compatibility by @laravel-shift in https://github.com/cjmellor/approval/pull/56

### New Contributors

* @laravel-shift made their first contribution in https://github.com/cjmellor/approval/pull/56

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.4.4...v1.4.5

## v1.4.4 - 2024-02-21

### What's Changed

* feat: Bypass Approving a Rollback by @cjmellor in https://github.com/cjmellor/approval/pull/53

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.4.3...v1.4.4

## v1.4.3 - 2024-02-17

### What's Changed

* feat: Audit Approvals by @cjmellor in https://github.com/cjmellor/approval/pull/51
* Add model and user to all event constructors by @nunodonato in https://github.com/cjmellor/approval/pull/49

### New Contributors

* @nunodonato made their first contribution in https://github.com/cjmellor/approval/pull/49

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.4.2...v1.4.3

## v1.4.2 - 2023-11-13

### What's Changed

- added support for 'include attributes list'. by @gerb-ster in https://github.com/cjmellor/approval/pull/46
- Bugfix array attributes by @gerb-ster in https://github.com/cjmellor/approval/pull/47

### New Contributors

- @gerb-ster made their first contribution in https://github.com/cjmellor/approval/pull/46

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.4.1...v1.4.2

## v1.4.1 - 2023-10-12

### What's Changed

- fix: Force data approval by @cjmellor in https://github.com/cjmellor/approval/pull/43

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.4.0...v1.4.1

## v1.4.0 - 2023-10-10

### What's Changed

- build(deps): Bump stefanzweifel/git-auto-commit-action from 4 to 5 by @dependabot in https://github.com/cjmellor/approval/pull/40
- feat: Rollback Approvals by @cjmellor in https://github.com/cjmellor/approval/pull/41

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.3.1...v1.4.0

## v1.3.1 - 2023-10-02

### What's Changed

- build(deps): Bump actions/checkout from 3 to 4 by @dependabot in https://github.com/cjmellor/approval/pull/37
- fix: Use with morphMaps by @cjmellor in https://github.com/cjmellor/approval/pull/39

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.3.0...v1.3.1

## v1.3.0 - 2023-08-22

### What's Changed

- Add conditional helper methods by @cjmellor in https://github.com/cjmellor/approval/pull/34

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.2.0...v1.3.0

## v1.2.0 - 2023-08-20

### What's Changed

- build(deps): Bump dependabot/fetch-metadata from 1.5.1 to 1.6.0 by @dependabot in https://github.com/cjmellor/approval/pull/32
- Add Events by @cjmellor in https://github.com/cjmellor/approval/pull/33

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.1.5...v1.2.0

## v1.1.5 - 2023-06-25

### What's Changed

- build(deps): Bump dependabot/fetch-metadata from 1.3.6 to 1.4.0 by @dependabot in https://github.com/cjmellor/approval/pull/28
- build(deps): Bump dependabot/fetch-metadata from 1.4.0 to 1.5.1 by @dependabot in https://github.com/cjmellor/approval/pull/30
- fix getting `approvalable_type` by @mtawil in https://github.com/cjmellor/approval/pull/31

### New Contributors

- @mtawil made their first contribution in https://github.com/cjmellor/approval/pull/31

**Full Changelog**: https://github.com/cjmellor/approval/compare/v1.1.4...v1.1.5

## 0.0.1 - 2022-05-07

- Initial release
