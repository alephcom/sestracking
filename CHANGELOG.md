# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[0.2.1]: https://github.com/yourusername/sestracking/releases/tag/v0.2.1
[0.2.0]: https://github.com/yourusername/sestracking/releases/tag/v0.2.0
[0.1.0]: https://github.com/yourusername/sestracking/releases/tag/v0.1.0

