# Provider Dashboard Chart Style Implementation Plan

> **For agentic workers:** Execute this scoped plan directly; the coordinator handles the independent review. Do not delegate recursively.

**Goal:** Match the supplied chart reference on the provider dashboard while preserving the existing data and typography.

**Architecture:** Add one optional `variant` prop to the existing Blade SVG chart. Provider financial data selects `grouped-bars` (slate/cyan), and consumption selects `dotted-line` (emerald); the default shared chart stays unchanged. Preserve permission-filtered series, values, units, native tooltips, data tables, empty state, and dark mode.

**Tech Stack:** Laravel Blade, existing Tailwind utilities, server-rendered SVG, Pest.

### Implementation and checks

- [x] Add focused rendering tests in `tests/Unit/DashboardChartViewTest.php` for grouped geometry, optional collection series, zero bars, dotted line tooltips, and a single positive month.
- [x] Run `php vendor/bin/pest tests/Unit/DashboardChartViewTest.php`; confirm new tests fail because the variants are absent.
- [x] Update `resources/views/components/dashboard-chart.blade.php` with the opt-in variants, five pale dotted grid levels, muted axes, and unchanged inherited fonts. Center month groups; use a short dotted segment for one consumption month.
- [x] Opt in only the two calls in `resources/views/provider/dashboard.blade.php`.
- [x] Repeat the focused tests, run `npm run build` for new Tailwind utilities, and inspect the scoped diff.

**Verification:** Focused Pest suite: 9 tests passed, 52 assertions. Frontend build passed; it reported the existing optional `fontaine` font-fallback warning. PHP test-file syntax and scoped diff whitespace checks passed. No connected browser tool or installed browser/Playwright runtime was found, so a live desktop/mobile/light/dark preview was not performed. UI/UX search scripts could not run because the available `python.exe` is an inaccessible Windows App Execution Alias; applied the loaded skill's contrast, accessible values, and responsive-region guidance directly.

Work directly in the authorized checkout, preserve unrelated changes, make no commit, and never run `php artisan test`.
