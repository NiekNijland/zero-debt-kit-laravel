# FEATURE COMPONENTS

Domain-driven React modules. Each feature is a mini-application.

## STRUCTURE

One directory per domain, for example:

```
features/
├── order/             # Order lines, totals, invoices
├── products/          # Product list, product form, attributes
├── customers/         # Customers and their addresses
├── warehouses/        # Warehouses and their stock
├── checkout/          # Basket and checkout
└── ...
```

## LAYERS

Imports go downwards only. ESLint (`no-restricted-imports` in `eslint.config.js`) fails
an import that points up.

```
types -> lib -> hooks -> Components/ui -> features -> Layouts -> Pages
```

- `features` is `Components/features`, `Components/admin`, `Components/layout`,
  `Features` and `Providers`. Features may import each other.
- A `Components/ui` component that needs feature code is feature UI: move it into the
  feature (see `products/components/stock-badge`).
- A hook that shows a toast or reads feature state is a feature hook: it lives in that
  feature's `hooks/`, not in `@/hooks`.
- A type that `types/` needs lives in `types/`; the feature re-exports it
  (`OrderLine` in `types/order.ts`).

## ADDING NEW FEATURE

1. Create directory: `features/{name}/`
2. Create subdirectories as needed:
    - `components/` - Feature-specific components
    - `context/` - React Context for state
    - `hooks/` - Feature-specific hooks
    - `utils/` or `services/` - Business logic
    - `tests/` - Co-located tests
    - `mocks/` - Test mocks

## CONVENTIONS

- Import modules by their own path (`@/Components/features/order/hooks/use-delete-order`). There are no `index.ts` barrels: they defeat tree-shaking, hide unused exports from knip and invite import cycles, and ESLint (`names/kebab-case`) fails a new one.
- Name files and folders in kebab-case (`use-delete-order.ts`); see FILE NAMES in `resources/js/AGENTS.md`.
- Co-locate stories: `{component}.stories.tsx`
- Co-locate tests: `{hook}.test.ts`
- Use global hooks from `@/hooks/` for shared logic
- Use `@/lib/` utilities (cn, datetime)
- Format numbers, percentages, money and file sizes with `useNumberFormat()` from `@/hooks`; outside a component, pass the locale code to `@/lib/number-format`
- Turn a date into text with `useShortDate()` or `useHumanReadableDate()` from `@/hooks`, or a named formatter from `@/lib/datetime` (`formatDayMonth`, `formatMonthYear`, `formatTime`, ...) with the locale from `useDateFnsLocale()`. A new notation is a new named formatter in `lib/datetime`, the one module that imports `format` and the other formatters of `date-fns` (ESLint `no-restricted-imports`) and that uses `Intl.DateTimeFormat`, `toLocaleString`, `toLocaleDateString` or `toLocaleTimeString` (ESLint `no-restricted-properties`). The browser's time zone and the name of a zone come from there too (`browserTimeZone`, `canonicalZone`, `formatTimeZoneName`).

## WHERE TO LOOK

| Task                   | Location                                                       |
| ---------------------- | -------------------------------------------------------------- |
| Order line rendering | `order/components/` |
| Order status badge | `order/components/order-status-badge/` |
| Product form | `products/product-form/` |
| Stock per warehouse | `warehouses/components/stock-table/` |
