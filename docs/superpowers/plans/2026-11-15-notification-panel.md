# Notification Panel Implementation Plan

> **For agentic workers:** Execute the authorized implementation inline; the coordinator handles independent review. No commit requested.

**Goal:** Replace the consumer header flyout with a compact, accessible notification panel matching the supplied reference.

**Architecture:** Keep the PHP portion of `resources/views/components/⚡notification-sheet.blade.php` byte-for-byte unchanged. Use the existing Flux dropdown and native popover for anchoring, dismissal, and keyboard behavior. Keep all current Livewire actions, links, inbox scope, counts, empty messages, and pagination.

**Tech Stack:** Laravel Blade, Livewire, Flux, Tailwind, Plus Jakarta Sans, AquaFlow brand tokens.

- [x] Update `tests/Feature/NotificationSheetTest.php` to assert the accessible popover contract and run `php vendor/bin/pest tests/Feature/NotificationSheetTest.php` against forced SQLite `:memory:`; verified one failure for the absent new panel ID, then five tests passed with 24 assertions.
- [x] Change only the component markup: a responsive 420px card, header/count, All/Unread controls, mark-all action, circular Flux icons, wrapping titles/bodies, times, unread dots, existing per-row actions, empty states, and pagination. Use Flux pagination to fit the panel's container width.
- [x] Run the targeted Pest file and `npm run build`; compare the component's original PHP bytes with Git HEAD. Build passed with the existing optional `fontaine` warning; PHP block identical at 1,765 bytes; PHP syntax and diff whitespace checks passed.
- [x] Render a temporary fixture preview outside the repository. Verified desktop, 375px mobile, dark mode, long unbroken text, 150-row middle-page pagination, next/previous, filter/read/all-read actions, empty unread state, focus, Escape/outside/focus-leaving dismissal, and real Livewire updates retaining the open popover. No horizontal overflow or browser errors.
- [x] Independent review completed; pagination overflow corrected and rechecked. Nonconsumer modal, external provider billing edit, and untracked `tools/` untouched. Verification used isolated fixtures rather than an authenticated account session. Flux retains its existing generic `aria-haspopup` behavior.

### Shared panel for all roles

- [x] Add a parameterized role-dashboard test proving the same accessible panel renders for Super Admin, Water Provider, and Consumer without another consumer's inbox. Keep the staff empty state and omit inactive controls. Verified two expected staff-panel failures before implementation, then eight tests passed with 46 assertions.
- [x] Extract the existing dropdown shell into `resources/views/components/notification-panel.blade.php`, with unread-count prop, optional filters slot, body slot, and existing styled empty state. Consumer PHP remains byte-identical at 1,765 bytes and actions remain in the same Livewire component.
- [x] Replace the nonconsumer header modal in `resources/views/layouts/app/sidebar.blade.php` with the shared panel, retaining the consumer permission condition.
- [x] Targeted notification tests passed (8 tests, 46 assertions), frontend build and four PHP syntax checks passed, and the consumer PHP block remained identical. Isolated browser checks verified consumer slot updates/actions/pagination and the staff empty panel at desktop and 375px dark mode, with no overflow or JavaScript errors. Independent Astra review found no blocking issues. Temporary preview stopped. No staff feed, permissions, events, database changes, or unrelated edits.
