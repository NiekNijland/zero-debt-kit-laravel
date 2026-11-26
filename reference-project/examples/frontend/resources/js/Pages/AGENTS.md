# PAGES

Each kind of page has one file to copy. Copy the file, do not assemble a page from
parts. `page-structure.test.ts` in this folder fails when a create, edit or form page skips its
shell, when a page renders `AdminLayout` itself, or when a file outside `Components/ui` declares
a component that `Components/ui` already has.

A page has no `components/` folder: `page-structure.test.ts` fails on any file under `Pages/**/components/`. Feature UI goes in `Components/features/<feature>/`, a generic building block in `Components/ui/<name>/`.

## Overview (app)

Copy `Pages/Product/Index.tsx`. The shell is `FormPage` with the create button in `actions`.
`ResourceFilters` with
`useResourceFilters` hold search and filters in the URL. The list is an `AnimatedGrid` of
`ResourceCard`s, with an `EmptyState` for no items and another for no matches. The controller pages
the list with `PaginatedData::transform` at `pagination.default_per_page` and keeps the filters with
`withQueryString()`; the page puts `Pagination` under the grid. No load-more button or infinite
scroll on an overview. Export the page
through `whenLoaded` with a `ListPageSkeleton`, because the sidebar links to it with `component=`.

## Create and edit (app)

Copy `Pages/Product/Create.tsx` for the page and
`Components/features/products/product-form/product-form.tsx` for the form. The page owns the
shell: `FormPage` with title, icon, breadcrumbs, `requiredLegend` when the form mixes required
and optional fields, and `footer={<FormActions ... />}`. The primary
action lives only in that footer, bound to the form through `formId`; extra buttons go in as children, a checkbox as `leading`. The page keeps a `processing` state that the
form reports through `onProcessingChange`. The form is a `<FormLayout id={formId}>` with
`useForm`, split into `FormSection`s (`title`, optional `hint`, `collapsible` with `summary` for
rarely used settings; every section has a `title`, the opening one is "General" from
`common.sections.general`). `FormLayout` renders the
`<form noValidate>` and owns the distance between sections, so the form sets no `space-y-*` and
sits in no `Card`. A text field is an `InputField` (`type="textarea"` exists); any other control
goes in a `FormField`, so no loose `Label` plus control plus `FieldError`. `required` comes from
the FormRequest. The measures are in DESIGN.md, Forms.

## Show (app)

Copy `Pages/Product/Show.tsx`. The shell is `Authenticated`, not `FormPage`: the title sits
with its icon above the content, and the actions are a "..." `DropdownMenu` next to it with edit
and delete; delete opens a `ConfirmationDialog`. Nothing destructive sits on an edit page.

## Overview (admin and landlord)

Copy `Pages/Admin/Users/Index.tsx`. The shell is `AdminIndexLayout` from
`Layouts/admin/admin-index-layout.tsx`: `title`, `breadcrumbs`, optional `description`, `notice`,
`filters`, `resultCount`, `isEmpty`, `empty` and `pagination`, with the table as children, and
`headerActions` for the buttons in the shell header. It renders `AdminLayout`, the `Head` and the
`h1` (through `AdminIndexPage`), so the page renders none of those.

## Create and edit (admin and landlord)

Copy `Pages/Landlord/Tenants/Create.tsx`. The shell is `AdminFormPage` from
`Layouts/admin/admin-form-page.tsx`: `title`, optional `description`, `breadcrumbs`,
`requiredLegend` when the form mixes required and optional fields, optional `headerActions`, and
`footer={<FormActions ... />}`. It renders `AdminLayout`, the `Head` and the `h1`, so the page
renders none of those. A form that is shared between a create and an edit page, such as
`Components/features/coupons/coupon-form.tsx`, takes a `layout` render prop and calls it with
title, breadcrumbs, footer and children; the page answers with `AdminFormPage`. The form component
holds only the `FormLayout` and its `FormSection`s, never the layout.

Unsaved changes go through the shell, never through the page: pass `dirty` to `AdminFormPage` or
`TenantPage` (a shared form hands it to its `layout` render prop). The shell then asks before the
user leaves, shows the footer only while there is a change and puts the one "unsaved changes" line
in it. A create page always adds `persistentFooter`, so its submit is there from the start; an
edit page adds it only when its footer has actions that work without a change, such as print. Do not add `useNavigationGuard`, `LeaveDialog` or a saved/unsaved text of your
own.
