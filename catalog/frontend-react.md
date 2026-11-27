# Catalog: frontend (React, TypeScript, Inertia)

This file is the React and TypeScript form; tool names here are that stack's.

Every project converges to these patterns; code that differs is debt (`01-principles.md`, "Who decides what"). Counts are text matches in tracked files, run from the repository root on the main branch. `<src>` is the frontend source folder.

**Reading the Rule column.** Every guard here is a lint rule (`04-guards.md` C1, "Frontend") unless the cell says otherwise: a built-in rule is named, a group name in brackets is its place in the worked example `reference-project/examples/frontend/eslint.config.js` (an example that does not run: the starter has no frontend), and "local plugin" is a small rule file in the project for what selectors cannot express. The written rules of the worked example are in `reference-project/examples/frontend/resources/js/AGENTS.md`. "Review; rubric item N" is a judgement, numbered in `templates/repo-docs.md`.

## Version floor per row

Step 2 opens with the platform PRs up to this floor (`05-program.md` D2). The floor is the release that introduced the feature, to the kit's best knowledge: check each against the release notes in step 1.

| Platform part | Floor | The rows that need it |
| --- | --- | --- |
| React | 19 | "Component" (`ref` as a prop) |
| TypeScript | 5.6 | "Type checker" (`noUncheckedSideEffectImports`) |
| The linter (ESLint) | 9.24 | flat config; bulk suppressions for the baseline of step 2 |
| typescript-eslint | 8 | "Typed linting" (`projectService`), `no-restricted-types` |
| The React hooks lint plugin | 6 | "React compiler rules" |
| The formatter (Prettier) | 3 | "Formatting", with the kit's config (`templates/tool-configs.md`) |
| The unused-code tool (knip) | 5 | "Unused code" |
| Inertia, where the project uses it | 2 | "Poll" (the poll hook); the HTTP hook of "Write that returns data" needs the release that ships it |

## Strictness

Rows that need a write, a form or a flash message are skipped where the UI has none (a read-only admin UI), as the Livewire rows of the backend catalog are.

| Item | Target | Count the old way | Rule |
| --- | --- | --- | --- |
| Linter on the whole tree | zero errors, zero warnings, `--max-warnings 0`, every rule `error`, `reportUnusedDisableDirectives: error` | `npx eslint <src> --format json`, counted by rule and severity | the checks step; ratchet rows `lint-config-off`, `lint-config-ignores` (it counts glob entries too) and `lint-baseline` (the bulk suppressions of step 2, counted per suppressed finding) |
| Settled rules | every rule of the linter and its plugins that the tree is at zero for is switched on by name, in a group per kind of file (core, typed, components), so a preset that changes, or a block whose own `rules` key replaces a spread preset, cannot drop one. A new rule joins its group once the tree is at zero for it | print every available rule, switch each on in a scratch config, keep the ones with zero findings | the named groups themselves (`settledCoreRules`, `settledTypedRules`, `settledReactRules`); a contract test that every rule of the scratch run with zero findings is in a group |
| Swallowed catch | zero. One definition for every count: a catch clause is swallowed when its body neither rethrows, nor reports through the project's one reporter, nor sets a value the caller reads. An empty body, a body that holds only a comment and a body that holds only a `console` call are all swallowed | `git grep -nE 'catch ?(\([^)]*\))? ?\{ ?\}' -- <src> \| wc -l` finds the empty bodies written on one line only; the other forms need the syntax tree (a script or a lint rule), so a number in the plan names the forms it counted | `no-empty` for an empty body (the rule allows a body with a comment: unverified here, from the rule's documentation); the comment-only and console-only forms: review |
| Disable comments | zero | `git grep -nE 'eslint-disable' -- <src> \| wc -l` | ratchet row `lint-disable`; `reportUnusedDisableDirectives` |
| Typed linting | `projectService` on; `no-unnecessary-condition`, `switch-exhaustiveness-check`, `no-non-null-assertion`, `prefer-nullish-coalescing`, `consistent-type-definitions` | enable in a scratch config and count | the rules as `error` |
| Type checker | `strict`, `noUncheckedIndexedAccess`, `noImplicitOverride`, `noImplicitReturns`, `noFallthroughCasesInSwitch`, `noUnusedLocals`, `noUnusedParameters`, `noUncheckedSideEffectImports`, and `allowJs`, `allowUnreachableCode` and `allowUnusedLabels` set to `false`; config, stories, setup and scripts included | `npx tsc --noEmit --incremental false --<flag> \| grep -c 'error TS'` per flag | the type check in the checks step; during the burn-down one keyed baseline per flag that is not at zero, checked by `scripts/keyed-baseline.sh --format tsc` and counted by the ratchet row `type-baseline` (`04-guards.md` C5); the ratchet row `analyser-switches` holds the flags that are on |
| Suppressions | zero `@ts-ignore`, `@ts-expect-error`, `@ts-nocheck` | `git grep -nE '@ts-(ignore\|expect-error\|nocheck)' -- <src> \| wc -l` | ratchet row `ts-suppressions`; `@typescript-eslint/ban-ts-comment` |
| Type escapes | no `any`, no `as` (assertion style `never`), no `as unknown as`, no `!` | `git grep -nE ': any\|as any\|as unknown as' -- <src> \| wc -l` | `no-explicit-any`, `consistent-type-assertions`, `no-non-null-assertion` |
| Unused code | the unused-code tool with no ignore list and every issue type an error. Without a config it reports every file as unused: name the entries an Inertia project needs (the bundler's entry points and the pages glob). A binary that only another package manager's script runs (a PHP manifest script calling `npx`) is reported as an unused dependency, and `ignoreBinaries` does not clear that finding: run the tool from a script of the manifest the unused-code tool reads (the unlisted `php` in that script is then the one binary to ignore, which is that key's real use). Run the tool to a fixed point: a deletion makes more files, exports and packages unused. Its "Configuration hints" are fixed first, not baselined | run it with the ignore list removed | the tool in the checks step; the ratchet row `unused-ignore` over its ignore list (`tools/README.md`, "Adding a row"); during the burn-down a keyed baseline of its findings (`scripts/keyed-baseline.sh --format knip`), counted by `type-baseline` |
| React compiler rules | `rules-of-hooks`, `exhaustive-deps`, refs and set-state-in-effect as errors | from the linter JSON | the rules as `error` |
| Formatting | the formatter with the kit's config checks the whole tree in CI (source, stories, scripts, config files) and fails; nothing writes or commits | `npx prettier --check <paths>` | the checks step (`04-guards.md` C4) |
| File size | at most 400 lines, tests included; exempt by category only (generated types, vendored UI component) | `git ls-files -z '<src>/*.ts' '<src>/*.tsx' \| xargs -0 wc -l \| awk '$2!="total" && $1>400' \| wc -l` | `max-lines` |
| Function size | at most 80 lines per function, as the backend's method limit | functions over the limit, from the linter with the rule on in a scratch config | `max-lines-per-function` with blank lines and comments skipped; components are held by the next row instead. Not in the starter |
| Component logic | at most 40 lines of logic in a component: the lines from the start of its body to its `return`, the markup not counted. More logic is a hook | components over the limit, by the local plugin in a scratch config | a local plugin (`components/max-logic-lines`). Not in the starter |

## Layers

The folders directly under `<src>`, lowest layer first. This set is the kit's, as the module folders are for the backend; a project adds no layer to it without an owner decision row. Not in the starter, whose layers are not in one table.

| Layer (folder) | Holds | May import |
| --- | --- | --- |
| `types` | the types generated from the backend, and shared declarations | nothing of the project |
| `lib` | pure functions, formatters, type guards (one module per untyped library payload) and the doors to browser APIs; imports types only from `types`, so a type needed from a component is a structural parameter type | `types` |
| `hooks` | hooks that know no domain | `lib`, `types` |
| `components/ui` | UI primitives, vendored or own: no domain, no data fetching | `lib`, `types` |
| `components` | shared components | the four above |
| `features/<domain>` | the components, hooks and reducers of one domain | the five above; never another feature |
| `layouts` | page shells | all above |
| `pages` | one file per page: reads its props and composes | all above; never another page |

## One way per task

| Task | The one way | Count the old way | Rule |
| --- | --- | --- | --- |
| Component | const arrow with typed props; no `FC`; no function declaration (converted by codemod); `ref` is a prop like any other, never `forwardRef` | `git grep -nE 'React\.FC\|: FC<\|FunctionComponent' -- <src> \| wc -l`; `git grep -nE '^(export (default )?)?function [A-Z]' -- <src> \| wc -l`; `git grep -n 'forwardRef' -- <src> \| wc -l` | `no-restricted-types`, `react/function-component-definition`, `func-style`, `no-restricted-syntax` (`sourceShapeRules`) |
| Set of values the frontend owns | an `as const` object with a union type of its values; an `enum` only comes from the backend through the generated types | `git grep -nE '^(export )?(declare )?(const )?enum ' -- <src> \| wc -l`, minus the generated file | `no-restricted-syntax` (`sourceShapeRules`) |
| Hook | `const useX = ...`, named export, React hooks imported by name | `git grep -nE 'function use[A-Z]\|React\.use[A-Z]' -- <src> \| wc -l` | `no-restricted-syntax`, `no-restricted-exports` |
| File names | kebab-case, no barrel `index.ts` | `git ls-files <src> \| grep -cE '(^\|/)index\.tsx?$'`; files with capitals | local plugin `names/kebab-case` |
| Layers | the layer table above: a file imports from its own layer and from the layers above its row, never from a row below | per-layer import scan | `no-restricted-imports` per layer |
| Page props | parameters of the page component, destructured; shared props read from the page hook | `git grep -E 'usePage<' -- <src> \| wc -l`; pages that take one `props` object and only read its fields | `no-restricted-syntax`, local plugin `pages/destructure-props` |
| Write that returns nothing | a router visit; the server flashes a translated message; one central toast shows it | `git grep -nE 'variant: "success"' -- <src> \| wc -l` | local plugin `toasts/no-success-toast` |
| Write that returns data | one HTTP hook; no direct write calls on the HTTP module; the hook instance is named after what it does, never `http` | `git grep -nE 'http\.(post\|put\|patch\|delete)' -- <src> \| wc -l`; `git grep -nE 'const http = useHttp' -- <src> \| wc -l` | `no-restricted-properties`, `no-restricted-syntax` |
| Read JSON for a component | one query hook; never an HTTP call in an effect | HTTP calls inside `useEffect` | `no-restricted-syntax` |
| Visit in progress | one hook; no hand-kept loading state around a visit | state set in `onStart`/`onFinish` | local plugin `visits/no-visit-state` |
| Form | the router library's form hook; one layout component set that owns spacing, so a form holds fields and no layout classes | hand-rolled form state (`useState` per field beside a submit handler); layout classes on form children | `no-restricted-syntax` selectors on the form components (`formStructureRules`) |
| Poll | the framework's poll hook for page props, one poller for the rest; no `setInterval` around a request | `git grep -n setInterval -- <src> \| grep -v test \| wc -l` | `no-restricted-syntax` |
| Lists with URL filters | one hook is the only caller of the query visit | direct query visits | `no-restricted-properties` |
| Debounce | one hook; no hand-written timers, no extra package | timers cleared and re-armed in one function | `no-restricted-syntax`, `no-restricted-imports` |
| Browser storage, media queries, fetch | one file each ("doors") | `git grep -nE 'localStorage\|sessionStorage\|matchMedia\|fetch\(' -- <src> \| grep -v test \| wc -l` | `no-restricted-globals`, `no-restricted-properties` |
| DOM events on window or document | one helper pair; typed custom events | `git grep -nE '(window\|document)\.addEventListener' -- <src> \| wc -l` | `no-restricted-properties`, `no-restricted-globals` |
| Viewport size | named breakpoint hooks | direct media-query hook imports | `no-restricted-imports` |
| Dates and numbers as text | one date module, one number module; relative time included | `git grep -nE 'Intl\.(NumberFormat\|RelativeTimeFormat)\|toLocaleString\(\|from "date-fns"' -- <src> \| wc -l` | `no-restricted-properties`, `no-restricted-imports` |
| Downloads | one helper | hand-built download links | local plugin `downloads/no-download-link` |
| Navigation | the router; no `location` writes | `location.href =` and friends | local plugin `navigation/no-location-write` |
| Backend enums and broadcast payloads | generated types; compare with enum members; typed event listener | hand-written unions of enum values; casts on event payloads | `no-restricted-syntax` generated from the enum list |
| Logging | no `console`; one debug logger; failures to the error tracker | `git grep -n 'console\.' -- <src> \| grep -v test \| wc -l` | `no-console` |
| State machines | a pure reducer over a discriminated union, with its own test; the hook only holds the value | reducers declared inside a hook or a component; `useState` holding a status string beside related state | review; rubric item 17 |
| Big contexts | split by what changes together (state, actions, interruptions) | contexts whose value has more than ten fields, by parser | review; rubric item 17. The count is a dashboard row the project adds, with its cap, so it cannot grow (`tools/README.md`, "Adding a row") |
| Icons, colors, UI overrides | one icon import path; color tokens only, never a raw color; no visual class overrides on UI primitives (a caller's classes are for layout); no arbitrary lengths | findings of the three rules and of the color script in a scratch run | `no-restricted-imports` for the icon path; `no-restricted-syntax` selectors for overrides and arbitrary lengths (`arbitraryLengthRule` in the worked example); a color check script in the checks step |
| Comments | none; one only when a future reader would act differently because of it | the regex of the row `comments` (`tools/README.md`) | ratchet row `comments`, which covers the frontend source folder (`04-guards.md` C6) |
| Logic without a test | a hook and every file under a `hooks`, `utils` or `lib` folder has a test next to it | dashboard row `frontend-logic-without-test` | the dashboard as a blocking step, with its key in the caps file and the ratchet row `cap-frontend-logic-without-test` (`04-guards.md` C3) |
| Component without a test | a component outside the pages and the UI primitives has a test next to it; pure UI primitives are covered by stories | dashboard row `frontend-components-without-test` | the same, with `cap-frontend-components-without-test` |
| Tests that run, no snapshots | no focused, skipped, todo, conditional or expected-to-fail test; no snapshot assertion | `git grep -nE '\.(only\|skip\|todo\|skipIf\|runIf\|fails)\(\|toMatch(Inline\|File)?Snapshot' -- <src> \| wc -l` | `no-restricted-syntax` (`focusedTestRules`), ratchet row `frontend-test-skip` |

The frontend test conventions are in `catalog/tests-database-ci-docs.md`, "Tests (frontend)". When each of these happens is in `05-program.md` D2: the strict config with its baselines in step 2, the baselines to zero in step 6, the patterns and splits in step 8.

## Lint rules and codemods that keep a convention

- **Doors.** A door (storage, media queries, window events, the router's `get` and `reload`, `Intl`) is a `no-restricted-properties` and `no-restricted-globals` list. The file that owns a door gets its own block with the same lists minus its entry, so `lint-config-off` and `lint-config-ignores` do not move. Flat config keeps the options of an earlier block when a later one gives only a severity, so a block that wants "no restrictions" gives a smaller real list. Tests get the formatting restriction and `fetch`, not the doors (they stub storage and assert on the fake router).
- **Review-only rules that become selectors once the tree is at zero**: `TSEnumDeclaration`; `Identifier[name='forwardRef']`; `MemberExpression[object.name='React'][property.name=/^use[A-Z]/]`; `ExportAllDeclaration` with `ExportNamedDeclaration[source]` (a barrel is a re-export); `ExportDefaultDeclaration` (pages and declaration files get their own block); `ArrowFunctionExpression > Identifier.params[name='props']` for pages. Moving a rule from "review checks that" to a selector takes minutes then.
- **Function declaration to const arrow** is a small codemod on the TypeScript compiler API, not a lint fix (`react/function-component-definition --fix` covers components only, `func-style` has no fixer): header text from the node, `export default` becomes `const X` plus `export default X`, a generic gets `<T,>` in `.tsx`; tests and vendored primitives are in scope. The rule that keeps it (`func-style`: expression) lands in the same PR, or the pattern grows back.
- **Component logic of at most 40 lines** is measurable without a plugin: a script over every PascalCase const arrow with a block body counts from the body start to its last `return`, blank lines skipped. Without the script or a rule it stays review item with its number.
- **Moving files** is one map-driven tool for every specifier form (`tools/move-ts.mjs` of the kit, copied to `tools/codemods/`), never a regex per run; a move of components empties the counted list of layer violations in the same PR.
- **Baseline files at zero.** `eslint --prune-suppressions` leaves `{}`; the file and every mention of it (the guides, the guard over the guide paths, the contract helper that pins the check line) are deleted, and the ratchet rows keep their optional paths so a returning file is still counted. The guard over the guides lists no baseline file by name.
