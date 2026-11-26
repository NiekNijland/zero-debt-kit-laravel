# Tool configs to copy

Formatting is a code pattern like any other: every project formats the same way, so a file looks the same in every repository and a move between projects produces no formatting diff. These configs are the default for a project that has none of its own: copy them as they are in step 2 (`05-program.md` D2); the one line that differs between projects is named under the frontend formatter, and the rules that can change behavior are listed at the end. The configs show the end state: every rule on.

**A config the organisation manages is not replaced.** Where the formatter (or linter) config is hosted by the organisation and fetched from a URL, that URL stays the source: never copy the file into the repository, never commit it, never drop the URL from the script that runs the formatter. Step 1 asks (`02-step-1-discover.md` 1.1) and the profile names the URL. The kit's concern is then only that the check runs on the whole tree, check-only, and blocks. The strictness rules the hosted config lacks are decision rows (an organisation-hosted config held 2 of the 21 rules, `declare_strict_types` and `visibility_required`, so expect nineteen decision rows to start); the counting rows of "Rules that can change behavior" stay, and a rule that is already on there is found by measuring (a counting row at 0 confirmed by a parser). A measured "no file changes under the kit's config" is not a reason to swap: the swap is a governance question, not a formatting one. Elsewhere in a project that has its own repository-local config, a rule it wants changed is a change to the kit, not a local override.

## PHP formatter (`pint.json`)

For a project without a hosted config the file is committed in the repository: a config that lives elsewhere changes the check without a commit. A hosted config is the organisation's decision (above), and its owner changes it deliberately.

```json
{
  "preset": "laravel",
  "rules": {
    "phpdoc_line_span": {
      "const": "single",
      "property": "single"
    },
    "phpdoc_align": {
      "align": "left",
      "spacing": 1
    },
    "no_empty_phpdoc": true,
    "no_useless_else": true,
    "declare_strict_types": true,
    "array_syntax": {
      "syntax": "short"
    },
    "blank_line_before_statement": {
      "statements": [
        "return",
        "exit"
      ]
    },
    "blank_line_after_opening_tag": true,
    "cast_spaces": {
      "space": "single"
    },
    "class_attributes_separation": {
      "elements": {
        "const": "one",
        "method": "one",
        "property": "one"
      }
    },
    "concat_space": {
      "spacing": "one"
    },
    "function_typehint_space": true,
    "linebreak_after_opening_tag": true,
    "no_blank_lines_after_class_opening": true,
    "no_blank_lines_after_phpdoc": true,
    "no_extra_blank_lines": true,
    "no_trailing_comma_in_singleline": true,
    "no_unused_imports": true,
    "no_whitespace_in_blank_line": true,
    "no_whitespace_before_comma_in_array": true,
    "not_operator_with_successor_space": true,
    "fully_qualified_strict_types": true,
    "global_namespace_import": {
      "import_classes": true,
      "import_constants": true,
      "import_functions": true
    },
    "no_unneeded_import_alias": true,
    "use_arrow_functions": true,
    "statement_indentation": false,
    "indentation_type": true,
    "nullable_type_declaration_for_default_null_value": true,
    "return_type_declaration": true,
    "single_quote": true,
    "standardize_not_equals": true,
    "trailing_comma_in_multiline": true,
    "no_break_comment": {
      "comment_text": "no break"
    },
    "multiline_whitespace_before_semicolons": {
      "strategy": "no_multi_line"
    },
    "no_empty_statement": true,
    "method_chaining_indentation": true,
    "single_blank_line_at_eof": true,
    "declare_equal_normalize": {
      "space": "none"
    },
    "blank_lines_before_namespace": {
      "max_line_breaks": 2,
      "min_line_breaks": 2
    },
    "no_leading_namespace_whitespace": true,
    "blank_line_after_namespace": true,
    "binary_operator_spaces": {
      "default": "single_space"
    },
    "operator_linebreak": {
      "position": "beginning"
    },
    "constant_case": {
      "case": "lower"
    },
    "visibility_required": {
      "elements": ["const", "property", "method"]
    },
    "strict_comparison": true,
    "final_class": true,
    "final_internal_class": true,
    "final_public_method_for_abstract_class": true,
    "protected_to_private": true,
    "date_time_immutable": true,
    "mb_str_functions": true,
    "ordered_class_elements": {
      "order": [
        "use_trait",
        "case",
        "constant",
        "constant_public",
        "constant_protected",
        "constant_private",
        "property_public",
        "property_protected",
        "property_private",
        "construct",
        "destruct",
        "magic",
        "phpunit",
        "method_abstract",
        "method_public_static",
        "method_public",
        "method_protected_static",
        "method_protected",
        "method_private_static",
        "method_private"
      ],
      "sort_algorithm": "none"
    },
    "ordered_interfaces": true,
    "ordered_traits": true,
    "self_accessor": true,
    "self_static_accessor": true,
    "modernize_types_casting": true,
    "no_superfluous_elseif": true,
    "no_multiple_statements_per_line": true,
    "array_push": true,
    "backtick_to_shell_exec": true,
    "lowercase_keywords": true,
    "lowercase_static_reference": true,
    "single_line_empty_body": false,
    "new_with_parentheses": {
      "anonymous_class": true,
      "named_class": true
    },
    "yoda_style": false,
    "phpdoc_tag_type": {
      "tags": {
        "inheritdoc": "annotation"
      }
    },
    "single_space_around_construct": true
  }
}
```

The 21 rules that enforce a pattern rather than a layout are the strictness list: `declare_strict_types`, `visibility_required` and the nineteen from `strict_comparison` to `lowercase_static_reference`. The `min` ratchet row `formatter-rules` counts the ones that are on (`FORMATTER_RULES` in the ratchet script), and `--final` holds it at 21 (`tools/README.md`, "The rows that ship"). Rule names and their risky marks were read in the formatter release the floor table names (`catalog/backend-laravel.md`, "Version floor per row"); `visibility_required` is marked deprecated there in favor of `modifier_keywords` and still runs: verify in step 1 against the installed version, and report a rule the installed release no longer has as a kit change.

## Frontend formatter (`.prettierrc`)

```json
{
    "semi": true,
    "trailingComma": "es5",
    "bracketSpacing": true,
    "bracketSameLine": false,
    "arrowParens": "always",
    "printWidth": 100,
    "tabWidth": 4,
    "useTabs": false,
    "plugins": ["prettier-plugin-organize-imports", "prettier-plugin-tailwindcss"]
}
```

One line differs by technique: a project that uses Tailwind keeps the entry `prettier-plugin-tailwindcss` and adds the key `tailwindStylesheet` with the path of its stylesheet; a project without Tailwind removes that one entry. Nothing else changes.

## PHP refactoring tool (`rector.php`)

The sets are the pattern; the paths are the project's. Worked example of the file's shape: `reference-project/rector.php`.

```php
return RectorConfig::configure()
    ->withPaths([/* every folder the analyser reads except the route files: the source, bootstrap, config, database, public, the tests */])
    ->withImportNames(removeUnusedImports: true)
    ->withComposerBased(laravel: true)
    ->withSets([
        LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
        LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        LaravelSetList::LARAVEL_COLLECTION,
        LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
        LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
        LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
        LaravelSetList::LARAVEL_FACTORIES,
        LaravelSetList::LARAVEL_IF_HELPERS,
        LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
    )
    ->withPhpSets();
```

- `withPhpSets()` without an argument takes the language sets up to the PHP version of the manifest; `withComposerBased(laravel: true)` takes the framework rules of the installed framework version. Neither names a version, so an upgrade needs no edit here.
- The route files are not in the paths: importing names turns every controller reference of a route file into an import line, and a route file reads best with the names where the routes are. The formatter and the analyser do read the route files.
- No `withSkip()`, no `withSkipPath()`: a rule that miscompiles an idiom is handled by fixing the idiom by hand before the pass (`05-program.md` D2, step 2). That holds for the rules that add the override marker too: the kit prescribes the marker (`catalog/backend-laravel.md`, "Overriding a method"). The ratchet row `refactor-skip` holds every skip entry at zero; the `min` row `refactor-sets` counts the 18 switches above (`REFACTOR_SETS` in the ratchet script) and `--final` holds it at 18. `earlyReturn` is not among them: Rector 2.9 deprecates the argument ("no longer applied"; its rules moved into `codeQuality`), so a flag for it would be counted and do nothing.
- The tool runs before the formatter (`composer lint`, `catalog/tests-database-ci-docs.md`, "Composer scripts"): the `codingStyle` set and the formatter touch the same constructs, and the dry run of the checks step is clean only on a tree the formatter has formatted after it. A rule of the two that keeps undoing the other is reported as a kit change, never skipped.

## PHP analyser and type checker (`phpstan.neon`, `tsconfig.json`)

The analyser's strictness is a set of switches, and the ratchet row `analyser-switches` counts the ones that are on (`ANALYSER_SWITCHES` in the ratchet script). There are 18 in all: two includes and five parameters in the analyser config, eleven compiler options in the type-checker config. Copy both blocks as they are in step 2; the paths, the type-coverage numbers and the disallowed-call rules are the project's, and a switch that is off for its own pull request is counted only when it is on (`05-program.md` D2). Worked example of the PHP file: `reference-project/phpstan.neon`.

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - vendor/phpstan/phpstan/conf/bleedingEdge.neon
    - vendor/nesbot/carbon/extension.neon
    - vendor/phpstan/phpstan-strict-rules/rules.neon
    - vendor/tomasvotruba/type-coverage/config/extension.neon

parameters:
    paths:
        - app
        - bootstrap
        - config
        - database
        - routes

    level: 10

    checkTooWideReturnTypesInProtectedAndPublicMethods: true
    checkMissingOverrideMethodAttribute: true
    reportAnyTypeWideningInVarTag: true
    checkOctaneCompatibility: true
    noEnvCallsOutsideOfConfig: true

    type_coverage:
        return: 100
        param: 100
        property: 100
        constant: 100
```

The two includes the row counts are `conf/bleedingEdge.neon` (the checks of the next major, today) and the date library's `extension.neon`; the other three includes and the `level` are not counted by this row. A baseline file and an `ignoreErrors` entry stay absent (`catalog/backend-laravel.md`, "Types").

**The analyser config for the tests** (`phpstan-tests.neon`) includes the file above, so it has every switch too, and adds the test paths, the Pest extensions and the dead-code detector. It sets one toggle off, because the bleeding-edge include turns it on and it fails every Pest expectation as a call on an internal class (`method.internalClass`):

```neon
includes:
    - phpstan.neon
    - vendor/pestphp/pest/extension.neon
    - vendor/shipmonk/dead-code-detector/rules.neon

parameters:
    featureToggles:
        internalTag: false

    paths:
        - tests

    level: 10
```

The include of `conf/bleedingEdge.neon` stays in `phpstan.neon`, so the row `analyser-switches` and the contract test (`04-guards.md` C4, point 15) read it there; the toggle is the one thing the test config turns off, and a stub file cannot do it (stub validation does not see a vendor class named in `@mixin`). Verified on Pest 5 with the bleeding-edge include: 1,040 `method.internalClass` errors without the toggle, none with it. The services of the test config (the Pest type extensions) are in `catalog/tests-database-ci-docs.md`, "Top level in tests". An extension class that a `services:` block names is found through the autoloader: after adding one, `composer dump-autoload -o` (the skeleton optimizes the autoloader, which does not know a new class until it is dumped).


```json
{
    "compilerOptions": {
        "strict": true,
        "noUncheckedIndexedAccess": true,
        "noImplicitOverride": true,
        "noImplicitReturns": true,
        "noFallthroughCasesInSwitch": true,
        "noUncheckedSideEffectImports": true,
        "noUnusedLocals": true,
        "noUnusedParameters": true,
        "allowJs": false,
        "allowUnreachableCode": false,
        "allowUnusedLabels": false
    }
}
```

These eleven options merge into the project's own `compilerOptions` (module, JSX, paths and the rest stay the project's). The block holds no comment: a switch that is commented out is not counted, and the row would drop. Verify a copy with the expression of the script (`ANALYSER_SWITCHES`): `grep -o -E "$ANALYSER_SWITCHES" phpstan.neon tsconfig.json | wc -l` prints 18, which is the target `--final` holds the row at.

## Dependency bot (`renovate.json`)

One bot in every project, on every host: Renovate. The file is committed at the repository root.

```json
{
    "$schema": "https://docs.renovatebot.com/renovate-schema.json",
    "extends": ["config:recommended", "helpers:pinGitHubActionDigests"],
    "rangeStrategy": "bump",
    "commitMessagePrefix": "<TICKET> chore(deps):",
    "packageRules": [
        {
            "matchUpdateTypes": ["major"],
            "enabled": false
        },
        {
            "matchManagers": ["github-actions"],
            "matchUpdateTypes": ["major"],
            "enabled": true
        }
    ]
}
```

`rangeStrategy` set to `bump` raises the lower limit in the manifest with every update, which is what `composer bump` does by hand (`catalog/tests-database-ci-docs.md`, "Manifest floor"). Majors are off: a major is a PR of the program or of the owner, and the dashboard row `dependencies-majors-behind` shows it. The one exception is the `github-actions` manager (a rule after the first one, which wins): an action's runtime deprecations arrive as majors, so a pinned action must be able to take them, and `helpers:pinGitHubActionDigests` keeps every `uses:` line pinned to a commit sha and moves the sha with each release (the version stays in the comment after it). A project on Bitbucket or GitLab has no such manager and both lines do nothing there; the file stays the same on every host. `<TICKET>` is the ticket the profile names for the bot, so the commit-message check passes (`07-process.md` section 10). The option names were not read in an installed copy of the bot: verify in step 1 against the bot's documentation.

## Rules that can change behavior

A whole-tree format commit is reviewed as generated, so nothing in it may change what the code does. This is the one list of the rules that can, and the one procedure for them.

**The procedure.** In the format commit of step 2 each PHP formatter rule of the table is off (`"<rule>": false`), so the row `formatter-rules` starts below 21. A ratchet row counts the sites the rule would change; new code adds none. The rule goes on in a PR of its own, after the test foundation, in the step the table names, under "Behavior that changes on purpose", with the runtime check (`05-program.md` D4, point 5). That PR's diff is the formatter's own rewrite of the remaining sites, read as hand-written. Then the formatter check is the guard and the counting row stays, at 0.

| Rule | What changes | The row that counts its sites | Lands |
| --- | --- | --- | --- |
| `declare_strict_types` | scalar coercion becomes a `TypeError` at every call written in the file | `strict-types-missing` | end of step 4 |
| `strict_comparison` | `==` and `!=` become `===` and `!==`: a comparison that relied on type juggling (`'1' == 1`, `null == false`) flips | `loose-comparisons` | end of step 4, after `declare_strict_types` |
| `date_time_immutable` | `DateTime` becomes `DateTimeImmutable`: code that modified a date in place now discards the result | `mutable-dates` | step 8, with the row "Current time and dates" |
| `mb_str_functions` | the byte functions become their multibyte forms: lengths, offsets and cuts change for every string that is not plain ASCII | `byte-string-functions` | step 8, after the date rule and before the class-closing sub-step (`05-program.md` D8), by the paragraph below |
| `final_class`, `final_internal_class`, `protected_to_private`, `final_public_method_for_abstract_class` | a concrete class that is extended or doubled, and a public method of an abstract class that is overridden, becomes a fatal error; a protected member of a final class that extends nothing becomes private | `open-classes` (dashboard row with its cap) | step 8, together, as the class-closing sub-step (`05-program.md` D8) |

The row definitions: `tools/README.md`, "Adding a row", point 6.

**`mb_str_functions` is the most dangerous rule of the config.** It is right for text and wrong for bytes: a hash, a binary payload, a length checked against a storage limit, an offset into a file or a protocol frame. The formatter cannot tell them apart. Before the rule goes on, every site of the row `byte-string-functions` is decided:

- text a person reads: the framework's string helper, or the multibyte function written by hand;
- bytes: the one byte helper, a `final` class in the domain-free support folder with static methods (`Bytes::length()`, `Bytes::slice()`, `Bytes::position()`) that call the multibyte functions with the encoding `'8bit'`, which counts bytes and which the rule leaves alone. The helper has its own test with a multibyte string.

A site nobody can classify is a decision row; it is never left for the formatter. The rule goes on when the row is 0, and its PR holds no rewrite.

**Idioms the refactoring tool's sets miscompile on a Pest project.** The first whole-tree pass changes three idioms (tests fail while the analyser stays green): (a) `typeDeclarations` adds `: Expectation` and `use Pest\Mixins\Expectation` to an arrow function that returns `expect(...)`: that mixin is a docblock class, the runtime object is `Pest\Expectation`, so the call is a `TypeError` when it runs and silent when it does not; (b) it types the parameter of `Http::fake(function ($request)` as `array`; (c) `deadCode` removes a condition on a variable captured by reference (`use (&$flag)`) that is assigned after the closure is made, in tests and in source. Fix each idiom by hand before the pass; after the pass grep the whole diff for each class (a wrong return type on a closure no test calls passes the suite) and read the diff of removed conditions by hand.

**Idioms the framework sets miscompile.** The `rector-laravel` sets rewrite an idiom into a facade call or a container call that throws where it runs. No skip is allowed, so each idiom is replaced by a spelling the sets leave alone, before the first pass. Each row was run in a project:

| The idiom | What the set writes | What it breaks | The spelling that stays |
| --- | --- | --- | --- |
| `$app['env'] = 'production';` (`ContainerArrayAccessToMethodCallRector`) | `$app->bind('env', 'production');` | `BindingResolutionException` when the environment is read | `$app->detectEnvironment(static fn (): string => 'production');` |
| `unlink($path);` (`UnlinkFuncCallToFileFacadeDeleteRector`) | `File::delete($path);` | "A facade root has not been set" in a test of the unit tier, which boots no application | `new Filesystem()->delete($path);` (`Illuminate\Filesystem\Filesystem`, a plain class); a guard or helper that deletes a temporary file goes through the one support class that owns temporary files |
| `$_SERVER['PATH']` (`ServerVariableToRequestFacadeRector`) | `Request::server('PATH')` | the same facade error, in a unit test | `Arr::get($_SERVER, 'PATH')` |
| a class name in a string, for example in a guard's list of booting base classes (`StringClassNameToClassConstantRector`) | `X::class` | a guard that lists a booting class as `X::class` becomes a support type of that class, and its own test fails it | build the name from two parts (`'Illuminate\\Foundation\\Testing\\' . 'RefreshDatabase'`), and in an example string that holds source code write the backslash in front of a constant, or the string changes meaning when the constant is joined to it |

A copy of a guard's test goes through the same sets: after the pass, run the guard's failing examples, because an example whose string was rewritten can pass for the wrong reason. A new idiom that a run finds is added to this table.


Three more can change behavior and are not formatter rules that can be switched off per site:

- The `typeDeclarations` set (PHP refactoring tool): an added parameter or return type coerces or rejects a value that passed unchecked before. Its first pass is the one the full suite is compared around (step 2); its diff on a class that no test names is read as hand-written.
- `ordered_class_elements` and `ordered_traits` (PHP formatter): the order of properties is the order of a cast to an array and of a serialized object, and the order of traits decides which one wins a method both declare. Count the classes that are cast, serialized or iterated as objects, and the classes that use two traits with a method of the same name, and read their diff in the format commit.
- `prettier-plugin-organize-imports` (frontend formatter): it reorders imports. Count the files that import for a side effect (`git grep -lE "^import ['\"]" -- <src>`) and read their diff: the order of a stylesheet or a polyfill can matter.

Every other rule above rewrites form only, to the kit's best knowledge. A rule a run finds to change behavior is added to this list when the run closes.

## Considered and rejected

Read in a public starter kit for the same stack and deliberately not adopted:

- **Automatic eager loading of relations.** It hides the N+1 that strict models make visible. The kit has one way to load a relation: an explicit `with()` at the query (`catalog/backend-laravel.md`, "Relation loading").
- **Running the dependency update on every `composer update`.** An update script hooked to the package manager rewrites the manifest when nobody asked; the kit's update is one named command a person runs (`catalog/tests-database-ci-docs.md`, "Dependency update").
- **A layout without controllers.** The kit's entry point for a request is a single-action controller in the framework layer, where someone who knows the framework looks for it.
- **`new_with_parentheses` set to false.** The kit writes `new Foo()` with parentheses, as the framework's own preset does.
- **That kit's package manager and bundler.** Those are techniques; the kit prescribes patterns.
- **The `strict` and `laravel` architecture presets, as whole presets.** `strict` forbids every abstract class and every protected method, which the framework's own extension points are made of; `laravel` pins classes to the stock folders, against the kit's module layout. A preset cannot be called in part, so neither is called, and the kit's own guards hold the assertions it adopts (`catalog/tests-database-ci-docs.md`, "What the presets assert").
- **A third-party package that switches the framework's strictness on.** The switches are written out as calls in the application service provider, where a reader and a config test find them (`catalog/backend-laravel.md`, "Runtime strictness").

## Another stack

One committed formatter config and one codemod set per language. TypeScript, backend or frontend: the `.prettierrc` above. A language without a config here: the formatter of its tooling table (`catalog/other-stacks.md`, "Tooling in another stack") with the formatter's defaults, committed in step 2 and written back here when the run closes, so the next project of that stack copies it.
