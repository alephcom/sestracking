# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Performance
- Added database indexes on `recipient_events.event_at`, `recipient_events.recipient_id`, `emails.sent_at`, and `emails.source` — eliminates full-table scans on the largest tables, the single biggest factor in slow dashboard loads at scale
- Added MySQL `FULLTEXT` indexes on `emails.subject` and `email_recipients.address` for future full-text search support
- Dashboard counter query rewritten from `whereHas` (nested `EXISTS` subqueries traversing two relationship levels) to a direct 3-table JOIN with `GROUP BY` — executed as a single efficient query
- Dashboard chart query now pushes `DATE(DATE_ADD(event_at, INTERVAL N MINUTE))` grouping into SQL on MySQL, so the database returns one row per day/event-type instead of every individual event row; SQLite falls back to the original PHP-grouping path
- Dashboard API responses cached for 5 minutes per project/date/timezone combination via Laravel's cache layer
- `emailsReport`: replaced `withCount` correlated subqueries + eager-loaded recipients with a single JOIN query that computes opens, clicks, recipient count, and email status in SQL
- `recipientsReport`: replaced `whereHas` (EXISTS subquery) with a direct JOIN; fixed non-portable double-quoted string literals that could silently misfire in strict MySQL mode
- `sendersReport`: replaced full PHP aggregation loop (loading all emails and recipients into memory) with a two-level SQL aggregation — inner subquery produces one row per email with computed status, outer query groups by sender
- `bouncedRecipientsReport`: replaced `whereHas` + deep `with(['recipient.email.project'])` eager-load with a direct 4-table JOIN selecting only the columns needed for the response
- Activity `eventType` filter rewritten from `whereHas` (nested EXISTS through `hasManyThrough`) to a `whereIn` subquery joining `email_recipients → recipient_events` directly

### Fixed
- Suppress Sass deprecation warnings (`color-functions`, `global-builtin`, `import`) emitted by Bootstrap 5's SCSS when built with Sass 1.77+; configured `quietDeps` and `silenceDeprecations` in `webpack.mix.js`

### Added
- Mobile offcanvas navigation: hamburger button in the navbar on small screens opens a full Bootstrap 5 offcanvas panel with all nav sections (Dashboard, Activity, Reports, Settings, Admin)
- Dashboard project selector replaced with a checkbox dropdown — individual projects can be toggled without Ctrl+click; "All Projects" auto-deselects when specific projects are chosen and vice versa
- Date Range label with calendar icon above the dashboard date inputs
- Loading spinner in the dashboard counters area while data is being fetched
- Inline dismissible `alert-danger` error messages on the dashboard (replaces blocking `alert()` dialogs)
- Record count ("Showing 1–10 of 243 results") displayed above pagination on the Activity page
- Enter-key support in the Activity search field triggers a search

### Changed
- Dashboard counter cards redesigned: each stat now shows a large contextual icon (envelope, check-circle, eye, mouse-pointer, exclamation-circle) with a matching color, laid out in a fluid responsive grid
- Dashboard "Bounce" chart color changed from near-invisible light gray (`#c8c8c8`) to amber (`#f59e0b`); "Click" color changed to purple (`#8b5cf6`) for clearer line distinction
- Activity filter bar reorganized into two rows — search + date range + clear button on row 1; event type + search + export on row 2 — eliminating the cramped four-equal-column layout
- Changing the date range or event type filter on the Activity page now auto-triggers a data reload instead of requiring a separate Search button click
- Activity status column uses Bootstrap badges (`Delivered`, `Bounced`, `Complained`, etc.) instead of `fa-dot-circle` icons
- Activity "Message" column shows subject as semibold text with the destination address(es) as small muted text below, reducing row height
- Activity empty state shows a centered inbox icon with guidance text instead of a plain text message
- Activity modal timestamps show formatted local time only; raw UTC string moved to the `title` tooltip attribute accessible on hover
- Dashboard and Activity main content area widened to `col-12` on mobile so it fills the screen when the sidebar is hidden
- Chart.js tooltip and interaction options corrected from v2 API format to v3 (`plugins.tooltip`, `interaction`) — chart tooltips were silently broken

### Added
- Welcome page at `/` explaining the app's purpose, features, and linking to the GitHub repository; shows Log In button for guests and Dashboard link for authenticated users
- Google and Microsoft SSO via `laravel/socialite` and `socialiteproviders/microsoft`; set `GOOGLE_CLIENT_ID` / `MICROSOFT_CLIENT_ID` env keys to enable each provider independently
- SSO buttons on the login page are conditionally rendered — only providers with credentials configured in `.env` are shown
- New `provider`, `provider_id`, and `avatar` columns on `users` table to support OAuth accounts
- Existing email/password accounts are automatically linked on first SSO login by matching email address
- New `SocialAuthController` handles OAuth redirect and callback for both providers
- Modernised login page: centred card layout with branded logo, Bootstrap 5 input groups, full-width sign-in button, and SSO divider
- Updated `signin.scss` with dark-blue gradient background aligned to app primary colour, wider card (440px), and brand-styled SSO buttons

### Fixed
- Sentry error tracking support via `sentry/sentry-laravel`; set `SENTRY_LARAVEL_DSN` in `.env` to enable (optional)
- SES webhook now accepts direct payloads that use `notificationType` (e.g. Delivery) in addition to `eventType`; previously these were logged as "Unknown webhook payload format"
- Email subjects longer than 255 characters (e.g. containing long URLs) no longer cause a database error; `subject` column changed from `VARCHAR(255)` to `TEXT`
- Invalid webhook requests (malformed JSON, missing/invalid SNS Message field, unknown payload format) now trigger Sentry warning events with contextual data when `SENTRY_LARAVEL_DSN` is configured
- New `SOCIALITE_AUTO_CREATE_USERS` env flag (default `false`) controls whether SSO can auto-register users with no existing account; when disabled, only pre-existing or invited users may sign in via SSO

### Fixed
- Root URL `/` now shows the public welcome page; dashboard moved to `/dashboard` so unauthenticated visitors are no longer immediately redirected to login

## [0.4.0] - 2025-12-14

### Added
- User invitation system: Administrators can now invite users by email without setting a password
- Invited users receive an email with a secure token link to set their own password
- Invitation acceptance page where users can set their password and automatically log in
- Password field in users table is now nullable to support invited users
- Collapsible sidebar functionality with toggle button in navbar
- Sidebar collapses to icon-only mode (70px width) showing tooltips on hover
- Sidebar state (collapsed/expanded) persists across page reloads using localStorage
- Smooth CSS transitions for sidebar collapse/expand animations

### Changed
- User creation form now supports two modes: "Create with Password" and "Invite by Email"
- Sidebar width is now configurable via CSS variable `--sidebar-width` (default: 260px)

## [0.3.3] - 2025-12-14

### Added
- Bounced Recipients Report tab on Reports page
- Report shows all bounced email recipients with bounce type, bounce subtype, timestamp, project, email subject, and sender
- CSV export support for bounced recipients report

### Fixed
- Webhook controller now correctly extracts bounced recipients from `bounce.bouncedRecipients[].emailAddress` array
- Bounce event timestamp extraction now uses `bounce.timestamp` instead of generic timestamp fallback
- Email addresses are properly trimmed and lowercased when processing bounce events
- Added CSV header mappings for bounced recipients report export

## [0.3.2] - 2025-12-14

### Fixed
- Production error: `Duplicate entry for key 'recipient_events_sns_message_id_unique'`
- Changed unique constraint on `recipient_events` table from `sns_message_id` alone to composite (`sns_message_id`, `recipient_id`, `type`)
- This allows the same SNS message to create events for multiple recipients (one per recipient)
- Updated `RecipientEvent::create()` calls to use `firstOrCreate()` to gracefully handle duplicates
- Removed early duplicate check that was preventing multi-recipient events from being processed correctly

## [0.3.1] - 2025-12-14

### Fixed
- Production error: `Field 'opens' doesn't have a default value` in emails table
- Added migration to ensure `opens` and `clicks` columns have default values (0) in `emails` table
- Migration alters existing columns to add defaults or adds columns with defaults if missing

## [0.3.0] - 2025-12-14

### Fixed
- Production error: `Field 'status' doesn't have a default value` in emails table
- Added migration to alter existing `status` column to nullable in `emails` table
- Migration handles both cases: alters existing NOT NULL column to nullable, or adds column if missing

## [0.2.9] - 2025-12-14

### Fixed
- Production error: `Field 'destination' doesn't have a default value` when column exists but is NOT NULL
- Added migration to alter existing `destination` column to nullable in `emails` table
- Migration handles both cases: alters existing NOT NULL column to nullable, or adds column if missing

## [0.2.8] - 2025-12-14

### Fixed
- Production error: `Field 'destination' doesn't have a default value` in emails table
- Added migration to ensure `destination` column exists in `emails` table
- Column is added as nullable for backward compatibility

## [0.2.7] - 2025-12-14

### Fixed
- Webhook now handles both SNS-wrapped and direct SES notification formats
- Fixed "SNS notification missing MessageId" error for direct SES notifications
- Webhook detects payload format automatically and generates MessageId for direct SES notifications
- Added migration to ensure `sent_at` column exists in `emails` table
- Fixes "Column not found: 1054 Unknown column 'sent_at'" error in production

### Changed
- Webhook controller now supports receiving SES notifications directly (without SNS wrapper)
- MessageId generation for direct SES notifications uses mail messageId, eventType, and timestamp for uniqueness

## [0.2.6] - 2025-12-14

### Fixed
- Production error: `Undefined array key "MessageId"` in SesWebhookController
- Added validation for SNS notification MessageId before accessing it
- Added validation for SNS Message field before decoding JSON
- Improved error handling and logging for invalid webhook payloads
- Webhook now returns proper error responses instead of throwing exceptions

## [0.2.5] - 2025-12-14

### Added
- Artisan command `user:make-super-admin` to make existing users super admins
- Command accepts user email or ID as identifier
- `--remove` option to remove super admin status
- Useful for production deployments and user management

## [0.2.4] - 2025-12-14

### Added
- Comprehensive migration to ensure all expected columns exist in all tables
- `ensure_all_table_columns_exist` migration checks all tables and adds missing columns
- Covers all application tables: users, projects, project_user, emails, email_recipients, recipient_events, project_requests

### Fixed
- Production database schema synchronization - ensures all tables have all expected columns
- Prevents column-related errors by automatically adding missing columns
- Migration is idempotent and safe to run multiple times

## [0.2.3] - 2025-12-14

### Fixed
- Production database error: `Column not found: 1054 Unknown column 'project_user.created_at'`
- Added migration to add missing `created_at` and `updated_at` columns to `project_user` pivot table
- Fixes issue where production databases created before timestamps were added to pivot table

## [0.2.2] - 2025-12-13

### Fixed
- Production migration safety - all table creation migrations now check if tables exist before creating them
- Prevents migration errors when deploying to production databases with existing tables
- Migrations are now idempotent and safe to run multiple times
- Fixed `SQLSTATE[42S01]: Base table or view already exists` errors in production deployments

### Changed
- All `Schema::create()` calls in migrations now use `Schema::hasTable()` checks for production safety
- Migrations can now be safely run on both fresh and existing databases

## [0.2.1] - 2025-12-13

### Added
- Comprehensive reporting system with three report types:
  - Email Report: Lists all emails with status, opens, and clicks
  - Recipients Report: Aggregates recipient data with total emails, opens, and clicks
  - Senders Report: Aggregates sender data with total opens, clicks, and status counts per sender
- Report filtering by date range and project (multi-select support)
- CSV export functionality for all report types
- Bootstrap 5 JavaScript bundle integration for tab functionality

### Changed
- Reports page UI redesigned with modern card layouts and improved spacing
- Report criteria section uses Bootstrap 5 form-select for better styling
- Improved z-index and positioning for cards to prevent layering issues

### Fixed
- Reports page tabs now work correctly with Bootstrap JS integration
- Report criteria section display issues fixed
- Card z-index and positioning issues resolved
- Select element styling improved for multiple selection

## [0.2.0] - 2025-12-13

### Added
- Project request system - regular users can now request new projects instead of creating them directly
- Project requests table and model to track pending, approved, and rejected project requests
- Project request approval workflow for super admins
- Project request views: create request form, requests list, and approval/rejection interface
- Automatic project creation when requests are approved by super admins
- Automatic assignment of requester as admin when project is approved
- Optional rejection reason field for rejected project requests
- "Request Project" link in sidebar for non-super-admin users
- "Project Requests" link in admin sidebar for super admins

### Changed
- **BREAKING**: Only super admins can directly create projects - all other users must request projects
- ProjectPolicy `create()` method now restricts direct project creation to super admins only
- Project request approval form includes user assignment interface similar to project creation

### Security
- Enhanced access control - project creation restricted to super admins
- Project requests require super admin approval before projects are created
- Regular users can only request projects, not create them directly

## [0.1.0] - 2025-12-13

### Added
- Multi-select project support on dashboard - users can now select multiple projects to view combined data
- Super admin user class with admin access to all projects
- Per-project role management - users can have different roles (admin/user) for different projects
- Boolean `super_admin` flag in users table for super admin status
- Database seeder now creates 10 projects with 200 emails each and 2 users per project

### Changed
- **BREAKING**: Removed Vue.js and Bootstrap Vue dependencies, converted entire frontend to vanilla JavaScript
- **BREAKING**: Changed from global user roles to per-project roles stored in `project_user` pivot table
- **BREAKING**: Replaced user role enum with `super_admin` boolean flag in users table
- Dashboard project selector now supports multiple project selection
- Installation instructions updated - compiled assets are now included, no need to run npm commands for basic usage
- Frontend now uses Chart.js directly instead of vue-chartjs
- Frontend now uses Bootstrap 5 native JavaScript instead of Bootstrap Vue
- SQLite compatibility fixes - replaced MySQL-specific CONVERT_TZ with PHP-based timezone handling

### Removed
- All DDEV references from documentation and configuration
- Vue.js 2.7 and all Vue-related dependencies
- Bootstrap Vue components
- vue-chartjs dependency
- vue2-daterange-picker dependency
- Global user role system (replaced with per-project roles)

### Fixed
- SQLite compatibility issue with CONVERT_TZ function
- Timezone conversion now works with both MySQL and SQLite databases
- Fixed TypeError when handling timezone offset in dashboard API

### Security
- Enhanced project access control with per-project role validation
- Super admins have full access while regular users are restricted to assigned projects

## Architecture Changes

### Frontend Stack
- **Before**: Vue.js 2.7 + Bootstrap Vue + vue-chartjs
- **After**: Vanilla JavaScript + Bootstrap 5 + Chart.js 3.x

### Permission System
- **Before**: Global user roles (admin/user) with admins having access to all projects
- **After**: 
  - Super admins (boolean flag) have access to all projects
  - Regular users have per-project roles (admin/user) stored in pivot table
  - Users can be admin for some projects and regular users for others

### Database Schema Changes
- Added `role` column to `project_user` pivot table (enum: 'admin', 'user')
- Replaced `role` enum in `users` table with `super_admin` boolean flag
- Migration includes data migration for existing installations

## Migration Guide

### For Fresh Installs
Simply run the migrations as usual:
```bash
php artisan migrate
php artisan db:seed
```

### For Existing Installations
1. Run the migrations to add `super_admin` flag and migrate `role` column:
   ```bash
   php artisan migrate
   ```

2. Update your frontend assets (if modifying Vue components):
   ```bash
   npm install
   npm run production
   ```
   Note: Compiled assets are included, so this is only needed if you modify frontend code.

3. Review user permissions - existing admin users will need to be assigned super_admin flag or per-project admin roles

## Contributors
- Initial structure based on [SES Dashboard](https://github.com/Nikeev/sesdashboard) by Nikeev (MIT License)

[0.4.0]: https://github.com/alephcom/sestracking/releases/tag/v0.4.0
[0.3.3]: https://github.com/alephcom/sestracking/releases/tag/v0.3.3
[0.3.2]: https://github.com/alephcom/sestracking/releases/tag/v0.3.2
[0.3.1]: https://github.com/alephcom/sestracking/releases/tag/v0.3.1
[0.3.0]: https://github.com/alephcom/sestracking/releases/tag/v0.3.0
[0.2.9]: https://github.com/alephcom/sestracking/releases/tag/v0.2.9
[0.2.8]: https://github.com/alephcom/sestracking/releases/tag/v0.2.8
[0.2.7]: https://github.com/alephcom/sestracking/releases/tag/v0.2.7
[0.2.6]: https://github.com/alephcom/sestracking/releases/tag/v0.2.6
[0.2.5]: https://github.com/alephcom/sestracking/releases/tag/v0.2.5
[0.2.4]: https://github.com/alephcom/sestracking/releases/tag/v0.2.4
[0.2.3]: https://github.com/alephcom/sestracking/releases/tag/v0.2.3
[0.2.2]: https://github.com/alephcom/sestracking/releases/tag/v0.2.2
[0.2.1]: https://github.com/alephcom/sestracking/releases/tag/v0.2.1
[0.2.0]: https://github.com/alephcom/sestracking/releases/tag/v0.2.0
[0.1.0]: https://github.com/alephcom/sestracking/releases/tag/v0.1.0

