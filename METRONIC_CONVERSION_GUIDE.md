**Purpose**
This file documents the steps used to migrate UDW pages from the legacy Metronic (Bootstrap) layout to the Metronic Tailwind layout. It is intended to be reusable for other starterkits and projects.

**Asset Placement**
1. Copy Metronic Tailwind assets into the public tree.
`public/assets/metronic/`
2. Keep existing legacy assets in `public/assets/` for pages that are not yet converted.

**Layout Strategy**
1. Preserve the legacy layouts for unconverted pages.
`resources/views/layouts/admin/master.blade.php`
`resources/views/layouts/admin/auth.blade.php`
2. Use the new Metronic Tailwind layout for converted pages.
`resources/views/layouts/metronic/app.blade.php`
`resources/views/layouts/metronic/auth.blade.php`
3. Update high-traffic pages to extend the new layout.
Example: `@extends('layouts.metronic.app')`

**Conversion Checklist**
1. Replace Bootstrap grid and card wrappers with Tailwind grid utilities.
2. Swap Bootstrap buttons for Metronic Tailwind components.
Use `kt-btn`, `kt-btn-primary`, `kt-btn-outline`, and `kt-badge`.
3. Keep data attributes and DOM IDs used by JS and charts unchanged.
4. Replace alert/notice containers with Tailwind utility styling.
5. Keep the content and data bindings intact while changing structure.
6. Validate that Tailwind utility classes exist in the compiled Metronic CSS.
Check `public/assets/metronic/css/styles.css` for available tokens (e.g., `bg-background`, `bg-muted/30`, `bg-primary/10`, `text-foreground`, `rounded-xl`) and avoid unsupported classes like `rounded-2xl` or arbitrary color scales not in the build.

**Legacy Asset Removal**
1. Remove global legacy CSS/JS from the Metronic Tailwind layout.
2. For pages that still need legacy libraries, load only the required assets per-page.
Add page-level includes via `@push('styles')` and `@push('vendor-scripts')`.
Example for DataTables pages:
`assets/plugins/global/plugins.bundle.css`
`assets/plugins/custom/datatables/datatables.bundle.css`
`assets/plugins/global/plugins.bundle.js`
`assets/plugins/custom/datatables/datatables.bundle.js`
3. Remove these per-page includes once DataTables and modals are migrated to Tailwind equivalents.

**Testing**
1. Run format pass.
`"/Users/selase/Library/Application Support/Herd/bin/php" vendor/bin/pint --dirty`
2. Run focused tests for the converted pages.
`"/Users/selase/Library/Application Support/Herd/bin/php" artisan test --compact ...`

**Rollout Pattern**
1. Convert one page at a time and keep the legacy layout for everything else.
2. Move the next page only after UI and scripts are stable.
3. Once all pages are migrated, delete the legacy layouts and legacy assets.
