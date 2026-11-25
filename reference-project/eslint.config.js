import fs from "node:fs";
import { createRequire } from "node:module";
import path from "node:path";
import { fileURLToPath } from "node:url";

import js from "@eslint/js";
import eslintConfigPrettier from "eslint-config-prettier";
import jsxA11y from "eslint-plugin-jsx-a11y";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import storybook from "eslint-plugin-storybook";
import tailwindcss from "eslint-plugin-tailwindcss";
import { defineConfig } from "eslint/config";
import globals from "globals";
import tseslint from "typescript-eslint";

const configDirectory = path.dirname(fileURLToPath(import.meta.url));

const a11yRecommended = jsxA11y.flatConfigs.recommended.rules;
const a11yStrict = jsxA11y.flatConfigs.strict.rules;

// Lucide keeps deprecated aliases that re-export the canonical icon, so two
// names draw the same glyph and a reviewer comparing files cannot tell whether
// a difference was intended.
const lucideAliasRules = Object.entries({
    AlertCircle: "CircleAlert",
    AlertTriangle: "TriangleAlert",
    CheckCircle: "CircleCheck",
    CheckCircle2: "CircleCheck",
    HelpCircle: "CircleHelp",
    Loader2: "LoaderCircle",
    XCircle: "CircleX",
}).map(([deprecated, canonical]) => ({
    selector: `ImportDeclaration[source.value="lucide-react"] > ImportSpecifier[imported.name="${deprecated}"]`,
    message: `Import ${canonical} from lucide-react instead of the deprecated ${deprecated} alias.`,
}));

// A component is a plain function with typed props: const X = ({ a }: Props) => ...
const functionComponentTypes = Object.fromEntries(
    ["FC", "React.FC", "FunctionComponent", "React.FunctionComponent", "VFC", "React.VFC"].map(
        (name) => [
            name,
            {
                message:
                    "Type the props on the function (const X = ({ a }: Props) => ...) instead of annotating it with FC.",
            },
        ]
    )
);

// A component is an arrow function (react/function-component-definition). That rule only recognizes a function that
// returns JSX, so a PascalCase function declaration that builds JSX and returns something else (a layout render prop)
// is caught here.
const componentDeclarationRule = {
    selector: "FunctionDeclaration[id.name=/^[A-Z]/]:has(JSXElement, JSXFragment)",
    message:
        "A component is an arrow function with typed props: const X = ({ a }: XProps) => ... (AGENTS.md).",
};

const hookDeclarationRule = {
    selector: ":matches(FunctionDeclaration, FunctionExpression)[id.name=/^use[A-Z]/]",
    message: "A hook is an arrow function: const useX = (...) => ... (AGENTS.md).",
};

const reactNamespaceHookRule = {
    selector: ':matches(MemberExpression[object.name="React"], TSQualifiedName[left.name="React"])',
    message:
        'Import what you use from react by name (import { useState, type ReactNode } from "react") instead of reaching through React.',
};

// The JSX runtime needs no React in scope, so react is imported by name only.
const reactNamedImportsOnly = {
    name: "react",
    importNames: ["default"],
    message:
        'Import what you use from react by name (import { useState, type ReactNode } from "react"); the JSX runtime needs no React in scope.',
};

// DESIGN.md: a caller's className is for layout; color, border, radius,
// padding and font size belong to a cva variant on the component. This checks
// the string literals in className and in cn(); a template literal or a
// variable slips through.
const uiComponents = [
    "Alert",
    "AlertDescription",
    "AlertDialogContent",
    "AlertTitle",
    "Badge",
    "Button",
    "Card",
    "CardContent",
    "CardDescription",
    "CardFooter",
    "CardHeader",
    "CardTitle",
    "DialogContent",
    "DropdownMenuContent",
    "Heading",
    "Input",
    "InputGroup",
    "InputGroupText",
    "PopoverContent",
    "SheetContent",
    "Text",
    "Textarea",
];
const lookClass =
    "(bg-|text-(foreground|muted-foreground|subtle|primary|secondary|destructive|success|info|warning|card-foreground|popover-foreground|overlay-foreground|2xs|xs|sm|base|lg|xl|2xl|caption)(\\s|$|\\/)|border|rounded|shadow|p[xytblr]?-\\d)";
// DESIGN.md: an arbitrary px or rem length is a theme value waiting to be
// named, or a value on Tailwind's spacing scale (`w-87.5` is 350px). Lengths
// that depend on context (calc, %, vh, cqi, grid templates, transition lists)
// are not flagged.
const arbitraryLengthRule = {
    selector: `:matches(JSXAttribute[name.name="className"], CallExpression[callee.name=/^(cn|cva|clsx)$/]) Literal[value=/[a-z]-\\[-?\\d*\\.?\\d+(px|rem)\\]/]`,
    message:
        "An arbitrary px or rem length is a theme variable in app.css, or a step on the spacing scale (w-87.5 is 350px). Context-dependent lengths (calc, %, vh, cqi) are fine (DESIGN.md, Tailwind).",
};

const uiOverrideRule = {
    selector: `JSXOpeningElement[name.name=/^(${uiComponents.join("|")})$/] JSXAttribute[name.name="className"] Literal[value=/(^|\\s|:)${lookClass}/]`,
    message:
        "Color, border, radius, padding and font size are a cva variant on the component, not a className override; className is for layout (DESIGN.md, Components). A one-off look is a variant named after its role, or a cva next to the feature that owns it.",
};

// DESIGN.md, Forms: FormLayout owns the distance between sections, FormSection the heading and the distance between
// its fields, FormField the label, hint and error. These selectors read one file at a time, so a Card or separator
// that another component wraps around a form is not seen.
const formSpacingClass = "/(^|\\s|:)(space-[xy]-|gap-)/";
const formFieldNames =
    "InputField|InputFieldWithLimit|FormField|SwitchField|DateField|IconField|TextField|TextareaField|FormTextField";
const formControlNames = `Label|FieldError|Checkbox|RadioGroup|Select|Switch|DatePicker|${formFieldNames}`;
const formLooseChild = `:matches(JSXElement[openingElement.name.name=/^(${formFieldNames})$/], JSXElement[openingElement.name.name=/^(div|fieldset|section|ul|ol)$/]:has(JSXElement[openingElement.name.name=/^(${formControlNames})$/]):not(:has(JSXElement[openingElement.name.name="FormSection"])))`;
const formStructureRules = [
    {
        selector: `JSXOpeningElement[name.name="form"] > JSXAttribute[name.name="className"] Literal[value=${formSpacingClass}]`,
        message:
            'A form sets no space-* or gap-* of its own: render FormLayout, which owns the distance between sections (spacing="fields" for a form without sections) (DESIGN.md, Forms).',
    },
    {
        selector: `JSXOpeningElement[name.name=/^(FormLayout|FormSection|FormField|InputField|InputFieldWithLimit)$/] > JSXAttribute[name.name="className"] Literal[value=${formSpacingClass}]`,
        message:
            "The distances in a form are set once, in FormLayout, FormSection and FormField; className is for width and position (DESIGN.md, Forms).",
    },
    {
        selector: `JSXElement:has(> JSXOpeningElement[name.name="FormLayout"]:not(:has(JSXAttribute[name.name="spacing"]))) > ${formLooseChild}`,
        message:
            "A field, or a hand-built group of fields in a div or fieldset, in a form with sections sits in a FormSection with a title, so it gets the field distance and not the section distance (DESIGN.md, Forms).",
    },
    {
        selector: `JSXElement:has(> JSXOpeningElement[name.name="FormLayout"]:not(:has(JSXAttribute[name.name="spacing"]))) > JSXExpressionContainer > :matches(LogicalExpression, ConditionalExpression) > ${formLooseChild}`,
        message:
            "A field, or a hand-built group of fields in a div or fieldset, in a form with sections sits in a FormSection with a title, so it gets the field distance and not the section distance (DESIGN.md, Forms).",
    },
    {
        selector:
            ':matches(JSXElement[openingElement.name.name="Card"] JSXOpeningElement[name.name="FormLayout"]:not(:has(JSXAttribute[name.name="spacing"])), JSXElement[openingElement.name.name="Card"] JSXOpeningElement[name.name="FormSection"], JSXElement:has(> JSXOpeningElement[name.name="FormLayout"]:not(:has(JSXAttribute[name.name="spacing"]))) > JSXElement[openingElement.name.name="Card"] > JSXOpeningElement)',
        message:
            "A form with sections stands on the page, not in a Card (DESIGN.md, Forms). A Card inside a FormSection that lists rows is fine.",
    },
    {
        selector:
            'JSXElement[openingElement.name.name=/^(form|FormLayout|FormSection)$/] JSXOpeningElement[name.name="LabeledSeparator"]',
        message:
            "A form is divided by FormSection; LabeledSeparator is for a labeled divider in a list or dialog (DESIGN.md, Forms).",
    },
];

// Class names are composed with cn() from lib/classnames, which drops falsy parts and lets a later class win (tailwind-merge);
// a template literal keeps "undefined" and both conflicting classes.
const classNameTemplateRule = {
    selector:
        'JSXAttribute[name.name="className"] > JSXExpressionContainer > TemplateLiteral[expressions.length>0]',
    message:
        'Compose class names with cn("base", condition && "extra") from @/lib/classnames, not a template literal (resources/js/Components/ui/AGENTS.md).',
};

// Page props are the page component's parameters: const X = ({ a }: XProps) => ...; export default X.
// Shared props are typed once (types/index.d.ts), so usePage().props needs no type argument.
const usePageGenericRule = {
    selector: 'CallExpression[callee.name="usePage"][typeArguments]',
    message:
        "Do not cast usePage with a type argument. A page takes its own props as parameters (const X = ({ a }: XProps) => ...; export default X); shared props are typed in types/index.d.ts and read with usePage().props.",
};

const useHttpNameRule = {
    selector: 'VariableDeclarator[id.name="http"][init.callee.name="useHttp"]',
    message:
        "Name a useHttp instance after what it does (save, previewRequest, importRequest), not http: http is the read-only client from @/lib/http (resources/js/AGENTS.md).",
};

// A poll that reloads a page prop is Inertia's usePoll; any other poll is usePoller (createPoller from lib/poller outside
// React), which backs off, pauses in a hidden tab and never overlaps. A timer (setInterval, or a setTimeout that arms
// the next round) around a request is neither.
const routerPollRule = {
    selector:
        'CallExpression:matches([callee.name=/^set(Interval|Timeout)$/], [callee.property.name=/^set(Interval|Timeout)$/]) CallExpression:matches([callee.object.name=/^(router|http|axios)$/], [callee.name="fetch"])',
    message:
        'Poll a page prop with usePoll(interval, { only }, { autoStart: false, mode: "rest" }) and anything else with usePoller (hooks/use-poller) or createPoller (lib/poller), never a timer around a request (resources/js/AGENTS.md).',
};

// A read whose answer the component shows is useJsonQuery (hooks/use-json-query): it keeps the answer with the key it
// was asked for, drops a late one and aborts on unmount. A request an event or a one-time mount action sends lives in a
// function outside the effect (useEffectEvent), never in an effect body with its own loading state.
const httpInEffectRule = {
    selector:
        'CallExpression[callee.name=/^use(Layout)?Effect$/] CallExpression[callee.object.name="http"][callee.property.name=/^(get|query|post)$/]',
    message:
        "Read JSON with useJsonQuery(key, load) from hooks/use-json-query, not with http inside an effect (resources/js/AGENTS.md).",
};

// A timer that is cleared and armed again in the same function is a hand-written debounce; useDebounce owns that timer
// (and its cleanup on unmount), and a JSON search box is useJsonQuery(key, load, { delay }).
const timeoutCall = (name) => `:matches([callee.name="${name}"], [callee.property.name="${name}"])`;
const ownStatement = (inner) => `:statement:has(${inner}):not(:has(:function ${inner}))`;
const handWrittenDebounceRule = {
    selector: `${ownStatement(`CallExpression${timeoutCall("clearTimeout")}`)} ~ ${ownStatement(
        `AssignmentExpression > CallExpression.right${timeoutCall("setTimeout")}`
    )}`,
    message:
        "Debounce with useDebounce from hooks/use-debounce (a JSON search box is useJsonQuery(key, load, { delay })), not by clearing and arming a timer yourself (resources/js/AGENTS.md).",
};

// The viewport is one of three named hooks (useIsMobile below 768px, useIsDesktop from 1024px, useIsWide from 80rem);
// useMediaQuery is what they are built on, and a component that asks the browser its own question is a fourth way.
const mediaQueryHookImport = {
    name: "@/hooks/use-media-query",
    message:
        "Ask the viewport with useIsMobile, useIsDesktop or useIsWide (@/hooks); a new breakpoint is a new use-is-* hook beside them (resources/js/AGENTS.md).",
};

// The debounce hook is hooks/use-debounce; a second debounce package would be a second way.
const debouncePackageImport = {
    name: "use-debounce",
    message: "Debounce with useDebounce from @/hooks/use-debounce (resources/js/AGENTS.md).",
};

// A visit sent with router is tracked with useVisit (hooks/use-visit): track(options) around the options and processing for
// the button. A form submitted through useForm has form.processing. A useState that onStart or onFinish sets back to
// false is a second copy of that state.
const visitStatePlugin = {
    rules: {
        "no-visit-state": {
            meta: {
                type: "problem",
                schema: [],
                messages: {
                    state: "Show that a visit is running with useVisit from @/hooks/use-visit (track(options), processing), or form.processing for useForm, not with a useState that onStart or onFinish sets (resources/js/AGENTS.md).",
                },
            },
            create: (context) => ({
                "Property[key.name=/^on(Start|Finish)$/] CallExpression[callee.name=/^set[A-Z]/][arguments.0.raw=/^(true|false)$/]":
                    (node) => context.report({ node, messageId: "state" }),
            }),
        },
    },
};

// A page takes its props as parameters (const X = ({ a }: XProps) => ...). It names the whole object only when it hands
// that object on: {...props} to the component that renders the page, or a hook that reads the props by key.
const pagePropsPlugin = {
    rules: {
        "destructure-props": {
            meta: {
                type: "suggestion",
                schema: [],
                messages: {
                    destructure:
                        "Take the props of a page as parameters: const X = ({ a, b }: XProps) => ...; name the whole object only to hand it on ({...props}, a hook that takes it) (resources/js/AGENTS.md).",
                },
            },
            create: (context) => ({
                "Program > VariableDeclaration > VariableDeclarator[id.name=/^[A-Z]/] > ArrowFunctionExpression":
                    (node) => {
                        const [parameter] = node.params;

                        if (parameter?.type !== "Identifier") {
                            return;
                        }

                        const handedOn = context.sourceCode
                            .getDeclaredVariables(node)
                            .filter((variable) => variable.name === parameter.name)
                            .flatMap((variable) => variable.references)
                            .some(({ identifier }) => {
                                const { parent } = identifier;
                                const readsOneProp =
                                    parent.type === "MemberExpression" &&
                                    parent.object === identifier &&
                                    !parent.computed;
                                const destructures =
                                    parent.type === "VariableDeclarator" &&
                                    parent.init === identifier &&
                                    parent.id.type === "ObjectPattern";

                                return !readsOneProp && !destructures;
                            });

                        if (!handedOn) {
                            context.report({ node: parameter, messageId: "destructure" });
                        }
                    },
            }),
        },
    },
};

// A test fakes useForm with createUseForm(router) from __mocks__/inertia/use-form, which keeps the real state and sends
// each submit through the mocked router; a hand-written fake drifts from the real hook (resources/js/AGENTS.md, Tests).
const useFormMockRule = {
    selector:
        'Property[key.name="useForm"]:not(:has(> CallExpression.value[callee.property.name="createUseForm"]), :has(> CallExpression.value[callee.name="createUseForm"]))',
    message:
        "Fake useForm with createUseForm(router) from @/__mocks__/inertia/use-form (resources/js/AGENTS.md, Tests).",
};

// @inertiajs/react has one fake, wired in vitest.setup.ts for every test file (__mocks__/inertia/react). A mock of
// its own in a test file replaces it with a copy that drifts (resources/js/AGENTS.md, Tests).
const inertiaMockRule = {
    selector:
        'CallExpression[callee.object.name="vi"][callee.property.name=/^(mock|doMock)$/]:matches([arguments.0.value="@inertiajs/react"], [arguments.0.source.value="@inertiajs/react"])',
    message:
        "Use the shared fake: read router from @inertiajs/react and set the page and answers with setPage, setPageProps and setHttp from @/__mocks__/inertia/react (resources/js/AGENTS.md, Tests).",
};

// A backend string enum with #[TypeScript] is generated into types/generated.ts, and that enum is the type: a union
// that lists its values again drifts the day a case is added, and its string comparisons cannot be found from the enum.
const generatedEnumSource = fs.readFileSync(
    path.resolve(path.dirname(fileURLToPath(import.meta.url)), "resources/js/types/generated.ts"),
    "utf8"
);
const generatedEnumUnionRules = [
    "InvoiceStatus",
    "OrderStatus",
    "ShipmentStatus",
].map((name) => {
    const body = generatedEnumSource.match(new RegExp(`export enum ${name} \\{([^}]*)\\}`))?.[1];

    if (body === undefined) {
        throw new Error(`${name} is not an enum in resources/js/types/generated.ts`);
    }

    const values = [...body.matchAll(/= "([^"]+)"/g)].map((match) => match[1]).join("|");

    return {
        selector: `TSUnionType[types.length>=2]:not(:has(> :not(TSLiteralType[literal.value=/^(${values})$/], TSNullKeyword, TSUndefinedKeyword)))`,
        message: `Use the generated ${name} from @/types/generated instead of a union of its values (resources/js/AGENTS.md, Backend enums).`,
    };
});

// A test that is focused, skipped, left as a todo or expected to fail hides what the suite covers; a snapshot
// pins markup nobody reads. A test asserts the behavior it names (resources/js/AGENTS.md, Tests).
const focusedTestRules = [
    {
        selector:
            ":matches(MemberExpression[object.name=/^(it|test|describe|bench)$/][property.name=/^(only|skip|todo|skipIf|runIf|fails)$/], CallExpression[callee.name=/^(fit|xit|xtest|fdescribe|xdescribe)$/])",
        message:
            "No focused, skipped, todo or expected-to-fail test: every test runs and passes, or is deleted (resources/js/AGENTS.md, Tests).",
    },
    {
        selector:
            "CallExpression[callee.property.name=/^(toMatch(Inline|File)?Snapshot|toThrowErrorMatching(Inline)?Snapshot)$/]",
        message:
            "No snapshot test: assert the role, name or text the behavior is about (resources/js/AGENTS.md, Tests).",
    },
];

// A set of values the frontend owns is an `as const` object with a union type; an enum only comes from the backend,
// generated into types/generated.ts. React 19 passes ref as a prop, so a component takes it like any other prop.
const sourceShapeRules = [
    {
        selector: "TSEnumDeclaration",
        message:
            "No hand-written enum: a backend enum gets #[TypeScript] and is imported from @/types/generated; values the frontend owns are an `as const` object with a union type (resources/js/AGENTS.md, Backend enums).",
    },
    {
        selector:
            ':matches(CallExpression[callee.name="forwardRef"], CallExpression[callee.property.name="forwardRef"])',
        message:
            "Take ref as a prop (React 19): const X = ({ ref, ...props }: XProps) => ..., not forwardRef.",
    },
];

const httpWriteRule = ["post", "put", "patch", "delete"].map((property) => ({
    object: "http",
    property,
    message:
        "Change data with a router visit and back() on the server; a write whose answer the caller needs uses useHttp and a JSON response. lib/http sends only read-only JSON queries and uploads with progress, through the method named for each (resources/js/AGENTS.md).",
}));

// Each browser API has one door, so that blocked storage, a missing matchMedia and a login redirect behave the same
// everywhere: a call anywhere else goes through the door.
const browserDoors = {
    localStorage: "lib/browser-storage (readStorage, writeStorage, removeStorage, clearStorage)",
    sessionStorage: "lib/browser-storage (readStorage, writeStorage, removeStorage, clearStorage)",
    matchMedia: "lib/media-query (matchesMedia, subscribeToMedia)",
    fetch: "lib/http for JSON, lib/fetch-download for a file",
    XMLHttpRequest: "lib/http",
};
const browserGlobalRules = Object.entries(browserDoors).map(([name, door]) => ({
    name,
    message: `Use ${door}; it is the one place that touches this API (resources/js/AGENTS.md).`,
}));
// A window or document subscription is useWindowEvent / useDocumentEvent (hooks), or listenToWindow /
// listenToDocument (lib/dom-events) outside React: one door that removes what it added. A listener on an element
// (a ref, a media query list) stays on that element.
const domEventRules = [
    ...["addEventListener", "removeEventListener"].flatMap((property) =>
        ["window", "document", "globalThis"].map((object) => ({
            object,
            property,
            message:
                "Listen with useWindowEvent or useDocumentEvent from @/hooks, or listenToWindow or listenToDocument from @/lib/dom-events outside React (resources/js/AGENTS.md).",
        }))
    ),
];
const domEventGlobalRules = ["addEventListener", "removeEventListener"].map((name) => ({
    name,
    message:
        "Listen with useWindowEvent or useDocumentEvent from @/hooks, or listenToWindow or listenToDocument from @/lib/dom-events outside React (resources/js/AGENTS.md).",
}));
const browserPropertyRules = [
    ...domEventRules,
    ...Object.entries(browserDoors).flatMap(([property, door]) =>
        ["window", "globalThis"].map((object) => ({
            object,
            property,
            message: `Use ${door}; it is the one place that touches this API (resources/js/AGENTS.md).`,
        }))
    ),
    {
        object: "Intl",
        property: "NumberFormat",
        message:
            "Format numbers with useNumberFormat() or lib/number-format, which cache the formatter per locale (resources/js/Components/features/AGENTS.md).",
    },
    ...[
        { object: "Intl", property: "DateTimeFormat" },
        { object: "Intl", property: "RelativeTimeFormat" },
        { property: "toLocaleString" },
        { property: "toLocaleDateString" },
        { property: "toLocaleTimeString" },
    ].map((restriction) => ({
        ...restriction,
        message:
            "Turn a date or time zone into text with a named formatter from lib/datetime (formatClock, formatTimeZoneName, ...), and a number with useNumberFormat(); add a formatter there for a new notation (resources/js/Components/features/AGENTS.md).",
    })),
];

// lib/datetime is the one module that turns a date into text, so every notation has one name and one look.
const dateFnsFormatters = [
    "format",
    "formatDistance",
    "formatDistanceStrict",
    "formatDistanceToNow",
    "formatDistanceToNowStrict",
    "formatISO",
    "formatRelative",
    "intlFormat",
    "intlFormatDistance",
    "lightFormat",
];
const dateFormatMessage =
    "Turn a date into text with useShortDate, useHumanReadableDate or a named formatter from lib/datetime (formatDayMonth, formatMonthYear, ...); add a named formatter there for a new notation (resources/js/Components/features/AGENTS.md).";
const dateFnsFormatImports = [
    { name: "date-fns", importNames: dateFnsFormatters, message: dateFormatMessage },
    ...dateFnsFormatters.map((name) => ({ name: `date-fns/${name}`, message: dateFormatMessage })),
];
const layerImportPatterns = (layers) => [
    {
        group: layers.flatMap((layer) => [`@/${layer}`, `@/${layer}/**`]),
        message:
            "A layer only imports from the layers below it: types, lib, hooks, Components/ui, features, Layouts, Pages.",
    },
];
const libLayers = ["hooks", "Components", "Features", "Layouts", "Pages"];

// A function a test captured from a mock is narrowed and called (requireCallable from __mocks__/callable, a typed
// vi.fn<Fn>() and its mock.calls), so a wrong argument fails tsc; Reflect.apply calls anything with anything.
const reflectApplyRule = {
    object: "Reflect",
    property: "apply",
    message:
        "Call the function directly: narrow it with requireCallable from @/__mocks__/callable or type the mock with vi.fn<Fn>() (resources/js/AGENTS.md, Tests).",
};

const queryVisitRule = {
    object: "router",
    property: "get",
    message:
        "A list that filters, searches or sorts through the URL uses useResourceQuery (hooks/use-resource-query); any other visit is router.visit (resources/js/AGENTS.md).",
};

// File and folder names under resources/js are kebab-case, and no folder holds an index barrel: an import names the
// module that defines the thing. The layer folders are fixed vocabulary, `__mocks__` mirrors module paths, and an
// Inertia page keeps the PascalCase name its controller renders (Inertia::render('Order/Index')); only the
// components, parts and stories folders inside Pages follow the rule.
const kebabCase = /^[a-z0-9]+(-[a-z0-9]+)*$/;
const layerFolders = new Set([
    "Components",
    "Features",
    "Layouts",
    "Pages",
    "Providers",
    "__mocks__",
]);
const pagePlainFolders = new Set(["components", "parts", "stories"]);
const fileNamePlugin = {
    rules: {
        "kebab-case": {
            meta: {
                type: "problem",
                schema: [],
                messages: {
                    name: "Name files and folders in kebab-case (use-order-filters.ts, order-line/order-line.tsx); `{{ name }}` is not.",
                    barrel: "No index barrel: import the module that defines the name (@/Components/ui/button/button), not the folder.",
                },
            },
            create: (context) => ({
                Program: (node) => {
                    const parts = path
                        .relative(path.join(context.cwd, "resources/js"), context.filename)
                        .split(path.sep);
                    const file = parts.pop();
                    const loc = { line: 1, column: 0 };
                    const inPages = parts[0] === "Pages";
                    let checked = !inPages;
                    for (const [index, folder] of parts.entries()) {
                        if (!checked) {
                            checked = pagePlainFolders.has(folder);
                            continue;
                        }
                        const fixed =
                            (index === 0 && layerFolders.has(folder)) ||
                            (parts[0] === "__mocks__" &&
                                (folder === "Components" || folder.startsWith("@")));
                        if (!fixed && !kebabCase.test(folder)) {
                            context.report({
                                node,
                                loc,
                                messageId: "name",
                                data: { name: folder },
                            });
                        }
                    }
                    const [stem, ...rest] = file.split(".");
                    if (stem === "index" && !file.endsWith(".d.ts")) {
                        context.report({ node, loc, messageId: "barrel" });
                    }
                    if (!checked || file.endsWith(".d.ts")) return;
                    for (const part of [stem, ...rest.slice(0, -1)]) {
                        if (!kebabCase.test(part)) {
                            context.report({ node, loc, messageId: "name", data: { name: file } });
                            break;
                        }
                    }
                },
            }),
        },
    },
};

// A write to location (location.href = ..., window.location.assign(...), location.reload()) leaves the Inertia app.
const locationObject = ':matches([object.name="location"], [object.property.name="location"])';
const locationWritePlugin = {
    rules: {
        "no-location-write": {
            meta: {
                type: "problem",
                schema: [],
                messages: {
                    write: "Leave the app through navigateToUrl or reloadPage from @/lib/full-page-navigation; a redirect the server decides is Inertia::location (resources/js/AGENTS.md).",
                },
            },
            create: (context) => {
                const report = (node) => context.report({ node, messageId: "write" });

                return {
                    [`AssignmentExpression > MemberExpression.left${locationObject}`]: report,
                    'AssignmentExpression > MemberExpression.left[object.name=/^(window|globalThis|document)$/][property.name="location"]':
                        report,
                    [`CallExpression > MemberExpression.callee[property.name=/^(assign|replace|reload)$/]${locationObject}`]:
                        report,
                };
            },
        },
    },
};

// A file the user saves leaves through downloadBlob in lib/fetch-download, the one place that builds a link with a
// download name, so the clean-up of the object URL lives in one place.
const downloadLinkPlugin = {
    rules: {
        "no-download-link": {
            meta: {
                type: "problem",
                schema: [],
                messages: {
                    link: "Hand a file to the user with downloadBlob from @/lib/fetch-download (resources/js/AGENTS.md).",
                },
            },
            create: (context) => {
                const report = (node) => context.report({ node, messageId: "link" });

                return {
                    'CallExpression[callee.object.name="document"][callee.property.name="createElement"][arguments.0.value="a"]':
                        report,
                    'AssignmentExpression > MemberExpression.left[property.name="download"]':
                        report,
                };
            },
        },
    },
};

// A success the server decided is Inertia::flash('success', ...) before back() (the central FlashToasts in
// Layouts/layout.tsx shows it: the one success toast is the one whose title is flash.success), never a second toast
// from the client. A client-only notice (a copy to the clipboard, a
// download that starts, an answer that came back as JSON) is a toast without a variant.
const successToastPlugin = {
    rules: {
        "no-success-toast": {
            meta: {
                type: "problem",
                schema: [],
                messages: {
                    success:
                        "Report a success with Inertia::flash('success', ...) and back() on the server; the central toast in Layouts/layout.tsx shows it. A notice with no server visit behind it (clipboard, download) is toast({ title }) without a variant (resources/js/AGENTS.md).",
                },
            },
            create: (context) => ({
                'CallExpression[callee.name="toast"] > ObjectExpression:not(:has(Property[key.name="title"][value.object.name="flash"][value.property.name="success"])) Property[key.name="variant"] Literal[value="success"]':
                    (node) => context.report({ node, messageId: "success" }),
            }),
        },
    },
};

// Rules the whole tree is at 0 for. They are named here, one by one, so that a preset that changes cannot drop
// one: the core rules hold for every linted file (the Node scripts too), the typed ones for every TypeScript file.
const settledCoreRules = Object.fromEntries(
    [
        "block-scoped-var",
        "default-case-last",
        "grouped-accessor-pairs",
        "guard-for-in",
        "logical-assignment-operators",
        "no-caller",
        "no-constructor-return",
        "no-div-regex",
        "no-else-return",
        "no-eval",
        "no-extend-native",
        "no-extra-bind",
        "no-extra-label",
        "no-inner-declarations",
        "no-iterator",
        "no-label-var",
        "no-labels",
        "no-lone-blocks",
        "no-lonely-if",
        "no-multi-str",
        "no-new-func",
        "no-new-wrappers",
        "no-object-constructor",
        "no-octal-escape",
        "no-proto",
        "no-return-assign",
        "no-self-compare",
        "no-sequences",
        "no-template-curly-in-string",
        "no-unmodified-loop-condition",
        "no-unneeded-ternary",
        "no-unreachable-loop",
        "no-useless-call",
        "no-useless-computed-key",
        "no-useless-concat",
        "no-useless-rename",
        "no-warning-comments",
        "operator-assignment",
        "prefer-exponentiation-operator",
        "prefer-numeric-literals",
        "prefer-object-has-own",
        "prefer-regex-literals",
        "prefer-template",
        "symbol-description",
        "unicode-bom",
        "yoda",
    ].map((name) => [name, "error"])
);
const settledTypedRules = Object.fromEntries(
    [
        "consistent-type-exports",
        "default-param-last",
        "no-generated-empty-object-type",
        "no-meaningless-void-operator",
        "no-mixed-enums",
        "no-non-null-asserted-nullish-coalescing",
        "no-unnecessary-boolean-literal-compare",
        "no-unnecessary-parameter-property-assignment",
        "no-unnecessary-qualifier",
        "no-unnecessary-template-expression",
        "no-unnecessary-type-arguments",
        "no-unsafe-enum-assignment",
        "no-unused-private-class-members",
        "no-useless-constructor",
        "no-useless-default-assignment",
        "no-useless-empty-export",
        "prefer-enum-initializers",
        "prefer-literal-enum-member",
        "prefer-readonly",
        "prefer-reduce-type-parameter",
        "prefer-return-this-type",
        "related-getter-setter-pairs",
        "require-array-sort-compare",
        "return-await",
        "unified-signatures",
        "use-unknown-in-catch-callback-variable",
    ].map((name) => [`@typescript-eslint/${name}`, "error"])
);
// eslint-plugin-react's recommended set is spread into the block below, but that block's own `rules` key replaces it;
// the rules of that set (and the others the tree is at 0 for) are therefore named here.
const settledReactRules = Object.fromEntries(
    [
        "checked-requires-onchange-or-readonly",
        "jsx-boolean-value",
        "jsx-fragments",
        "jsx-key",
        "jsx-no-comment-textnodes",
        "jsx-no-duplicate-props",
        "jsx-no-script-url",
        "jsx-no-target-blank",
        "jsx-no-undef",
        "jsx-pascal-case",
        "jsx-props-no-spread-multi",
        "no-access-state-in-setstate",
        "no-arrow-function-lifecycle",
        "no-children-prop",
        "no-danger-with-children",
        "no-deprecated",
        "no-did-mount-set-state",
        "no-did-update-set-state",
        "no-direct-mutation-state",
        "no-find-dom-node",
        "no-invalid-html-attribute",
        "no-is-mounted",
        "no-namespace",
        "no-redundant-should-component-update",
        "no-render-return-value",
        "no-string-refs",
        "no-this-in-sfc",
        "no-unescaped-entities",
        "no-unsafe",
        "no-unused-class-component-methods",
        "no-unused-state",
        "no-will-update-set-state",
        "require-render-return",
        "self-closing-comp",
        "style-prop-object",
        "void-dom-elements-no-children",
    ].map((name) => [`react/${name}`, "error"])
);

// A file over 400 lines is split by what it does, a test file by the behavior it covers. Stories, mocks and the vendored shadcn sidebar are exempt by
// category; there is no list of files that may stay long.
const MAX_FILE_LINES = 400;

export default defineConfig(
    { linterOptions: { reportUnusedDisableDirectives: "error" } },
    {
        ignores: ["resources/js/types/generated.ts"],
    },
    js.configs.recommended,
    ...tseslint.configs.recommendedTypeChecked,
    ...tseslint.configs.stylisticTypeChecked,
    {
        languageOptions: {
            parserOptions: { projectService: true, tsconfigRootDir: configDirectory },
        },
    },
    { rules: { ...settledCoreRules, "one-var": ["error", "never"] } },
    {
        files: [
            "resources/js/**/*.{ts,tsx}",
            ".storybook/**/*.{ts,tsx}",
            "vitest.setup.ts",
            "vite.config.ts",
        ],
        rules: settledTypedRules,
    },
    {
        files: ["resources/js/**/*.{js,jsx,ts,tsx}"],
        ...react.configs.flat.recommended,
        ...react.configs.flat["jsx-runtime"],
        languageOptions: {
            ...react.configs.flat.recommended.languageOptions,
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
        settings: {
            // Without this mapping jsx-a11y only sees native elements, so <Button>
            // was never checked for a name.
            "jsx-a11y": { components: { Button: "button" } },
            // eslint-plugin-react's "detect" calls an API ESLint 10 removed, which crashes every component rule.
            react: {
                version: createRequire(import.meta.url)("react/package.json").version,
            },
            tailwindcss: {
                functions: ["cn", "cva"],
                // The values in a compound variant's selector keys are variant
                // names, not classes; the plugin cannot tell them apart.
                ignoredKeys: ["defaultVariants", "compoundVariants"],
                cssConfigPath: path.resolve(
                    path.dirname(fileURLToPath(import.meta.url)),
                    "resources/css/app.css"
                ),
            },
        },
        plugins: {
            ...react.configs.flat.recommended.plugins,
            "react-hooks": reactHooks,
            "jsx-a11y": jsxA11y,
            tailwindcss,
        },
        rules: {
            ...reactHooks.configs.flat["recommended-latest"].rules,
            // .storybook/main.ts spreads Vite's alias option, which is typed as an object or an array.
            "@typescript-eslint/no-misused-spread": "error",
            ...settledReactRules,
            "react/jsx-curly-brace-presence": ["error", "never"],
            "react/jsx-max-depth": ["error", { max: 8 }],
            "react-hooks/incompatible-library": "error",
            "react-hooks/unsupported-syntax": "error",
            ...jsxA11y.flatConfigs.recommended.rules,
            // prettier-plugin-tailwindcss already sorts class names on format, so that rule is left out.
            ...Object.fromEntries(
                Object.entries(tailwindcss.configs.recommended.rules).filter(
                    ([name]) => name !== "tailwindcss/classnames-order"
                )
            ),
            "@typescript-eslint/no-unnecessary-condition": "error",
            "@typescript-eslint/switch-exhaustiveness-check": "error",
            "@typescript-eslint/no-non-null-assertion": "error",
            // `a || b` on a string, number or boolean means "empty falls back" here; `??` would keep "" and 0.
            "@typescript-eslint/prefer-nullish-coalescing": ["error", { ignorePrimitives: true }],
            "@typescript-eslint/consistent-type-definitions": ["error", "type"],
            "@typescript-eslint/no-unused-vars": [
                "error",
                {
                    args: "after-used",
                    argsIgnorePattern: "^_",
                    varsIgnorePattern: "^_",
                    caughtErrorsIgnorePattern: "^_",
                    destructuredArrayIgnorePattern: "^_",
                    ignoreRestSiblings: true,
                },
            ],
            // From jsx-a11y's strict set. The recommended set catches the wrong
            // element; this catches the right element with no name, which is the
            // shape most accessibility defects here have taken.
            "jsx-a11y/control-has-associated-label": [
                "error",
                {
                    // A datalist option is not a control, and depth 5 is enough
                    // to find label text nested behind an icon or a sr-only span.
                    ignoreElements: ["option"],
                    // A childless component child (a lucide icon) counts as a label
                    // unless it matches controlComponents, so <Button><X /></Button>
                    // passed. The wildcard makes every such child a non-label.
                    controlComponents: ["*"],
                    depth: 5,
                },
            ],
            // A form takes the keydown of its fields, so every field of a form shares one Enter handler. A focusable
            // separator is the ARIA window splitter, a widget in ARIA 1.2 that aria-query still lists as structure; a
            // disabled control inside a tooltip takes its tab stop on a span around it.
            "jsx-a11y/no-noninteractive-element-interactions": [
                "error",
                {
                    ...a11yRecommended["jsx-a11y/no-noninteractive-element-interactions"][1],
                    form: ["onKeyDown"],
                },
            ],
            "jsx-a11y/no-noninteractive-tabindex": [
                "error",
                { tags: ["span"], roles: ["tabpanel", "separator"], allowExpressionValues: true },
            ],
            // Safari's VoiceOver drops the list role of a list with `list-style: none` or `display: contents`.
            "jsx-a11y/no-redundant-roles": ["error", { ul: ["list"], ol: ["list"] }],
            // From jsx-a11y's strict set: no exceptions for which element may take which role.
            "jsx-a11y/interactive-supports-focus":
                a11yStrict["jsx-a11y/interactive-supports-focus"],
            "jsx-a11y/no-interactive-element-to-noninteractive-role": "error",
            "jsx-a11y/no-noninteractive-element-to-interactive-role": "error",
            "jsx-a11y/anchor-ambiguous-text": "error",
            "jsx-a11y/lang": "error",
            "no-console": "error",
            // A function is a const arrow (const formatX = (...) => ...); one that needs `this` or `yield` is a
            // function expression. Overload signatures keep their declaration, which the rule allows.
            "func-style": ["error", "expression"],
            // On a custom component autoFocus is a prop whose meaning that
            // component defines; the DOM attribute is still flagged.
            "jsx-a11y/no-autofocus": ["error", { ignoreNonDOM: true }],
            "react-hooks/rules-of-hooks": "error",
            "react-hooks/exhaustive-deps": "error",
            "react-hooks/capitalized-calls": "error",
            "no-restricted-syntax": [
                "error",
                ...lucideAliasRules,
                arbitraryLengthRule,
                usePageGenericRule,
                useHttpNameRule,
                classNameTemplateRule,
                routerPollRule,
                httpInEffectRule,
                handWrittenDebounceRule,
                useFormMockRule,
                inertiaMockRule,
                componentDeclarationRule,
                hookDeclarationRule,
                reactNamespaceHookRule,
                ...generatedEnumUnionRules,
                ...focusedTestRules,
                ...sourceShapeRules,
            ],
            "@typescript-eslint/no-restricted-types": ["error", { types: functionComponentTypes }],
            "react/function-component-definition": [
                "error",
                { namedComponents: "arrow-function", unnamedComponents: "arrow-function" },
            ],
            // `not-prose` is a hook for the typography plugin; the rtl selectors come from
            // react-day-picker's own class names.
            "tailwindcss/no-custom-classname": [
                "error",
                {
                    whitelist: [
                        "not-prose",
                        "rtl:.*",
                    ],
                },
            ],
            "tailwindcss/enforces-shorthand": "error",
            "tailwindcss/no-unnecessary-arbitrary-value": "error",
            "padding-line-between-statements": [
                "error",
                {
                    blankLine: "always",
                    prev: "*",
                    next: "return",
                },
            ],
        },
    },
    {
        files: [
            "resources/js/**/*.{ts,tsx}",
            ".storybook/**/*.{ts,tsx}",
            "vitest.setup.ts",
            "vite.config.ts",
        ],
        rules: { "no-restricted-properties": ["error", reflectApplyRule] },
    },
    // lib/http defines the transport; everything else goes through the named methods. The browser APIs go through their
    // doors, and leaving the app with a full page load goes through lib/full-page-navigation (a redirect the server
    // decides is Inertia::location). A test, story or mock may use them directly, and so may the file that is the door.
    {
        files: ["resources/js/**/*.{ts,tsx}"],
        ignores: [
            "**/*.{test,stories}.{ts,tsx}",
            "**/{mocks,__mocks__}/**",
            "resources/js/lib/{browser-storage,media-query,fetch-download,http,number-format,datetime,full-page-navigation,dom-events}.ts",
        ],
        plugins: { navigation: locationWritePlugin, downloads: downloadLinkPlugin },
        rules: {
            "downloads/no-download-link": "error",
            "no-restricted-globals": ["error", ...browserGlobalRules, ...domEventGlobalRules],
            "no-restricted-properties": [
                "error",
                ...httpWriteRule,
                ...browserPropertyRules,
                reflectApplyRule,
            ],
            "navigation/no-location-write": "error",
        },
    },
    {
        files: ["resources/js/Pages/**/*.tsx"],
        ignores: ["**/*.{test,stories}.tsx"],
        plugins: { pages: pagePropsPlugin },
        rules: { "pages/destructure-props": "error" },
    },
    // The server flashes a success and only the central toast renders it; a running visit is useVisit, not a useState.
    {
        files: ["resources/js/**/*.{ts,tsx}"],
        ignores: ["**/*.{test,stories}.{ts,tsx}", "**/{mocks,__mocks__}/**"],
        plugins: { toasts: successToastPlugin, visits: visitStatePlugin },
        rules: { "toasts/no-success-toast": "error", "visits/no-visit-state": "error" },
    },
    // Components and pages visit through useResourceQuery or router.visit; the hooks and lib folders are where
    // that one hook calls router.get.
    {
        files: ["resources/js/{Components,Features,Layouts,Pages,Providers}/**/*.{ts,tsx}"],
        ignores: ["**/*.{test,stories}.{ts,tsx}", "**/{mocks,__mocks__}/**"],
        rules: {
            "no-restricted-properties": [
                "error",
                ...httpWriteRule,
                ...browserPropertyRules,
                queryVisitRule,
                reflectApplyRule,
            ],
        },
    },
    {
        files: ["resources/js/**/*.{ts,tsx}"],
        rules: {
            "no-restricted-imports": [
                "error",
                {
                    paths: [
                        ...dateFnsFormatImports,
                        reactNamedImportsOnly,
                        debouncePackageImport,
                        mediaQueryHookImport,
                    ],
                },
            ],
        },
    },
    // A hook is a named export (import { useCan } from "@/hooks/use-can"), so a mock names it too. Pages and lazily
    // loaded components keep the default export Inertia and lazy() resolve; a story's meta is its default export.
    {
        files: ["resources/js/hooks/**/*.{ts,tsx}", "resources/js/**/use-*.{ts,tsx}"],
        ignores: ["**/*.stories.{ts,tsx}"],
        rules: {
            "no-restricted-exports": [
                "error",
                {
                    restrictDefaultExports: {
                        direct: true,
                        named: true,
                        defaultFrom: true,
                        namedFrom: true,
                        namespaceFrom: true,
                    },
                },
            ],
        },
    },
    // Layers import downwards only:
    // types -> lib -> hooks -> Components/ui -> features -> Layouts -> Pages.
    ...Object.entries({
        "resources/js/types/**": ["lib", "hooks", "Components", "Features", "Layouts", "Pages"],
        "resources/js/lib/**": libLayers,
        "resources/js/hooks/**": ["Components", "Features", "Layouts", "Pages"],
        "resources/js/Components/ui/**": [
            "Components/features",
            "Components/admin",
            "Components/layout",
            "Features",
            "Layouts",
            "Pages",
        ],
        "resources/js/{Components/features,Components/admin,Components/layout,Features,Providers}/**":
            ["Layouts", "Pages"],
        "resources/js/Layouts/**": ["Pages"],
    }).map(([files, layers]) => ({
        files: [files],
        rules: {
            "no-restricted-imports": [
                "error",
                {
                    paths: [
                        ...dateFnsFormatImports,
                        reactNamedImportsOnly,
                        debouncePackageImport,
                        mediaQueryHookImport,
                    ],
                    patterns: layerImportPatterns(layers),
                },
            ],
        },
    })),
    {
        files: ["resources/js/lib/datetime.ts"],
        rules: {
            "no-restricted-imports": [
                "error",
                {
                    paths: [reactNamedImportsOnly, debouncePackageImport, mediaQueryHookImport],
                    patterns: layerImportPatterns(libLayers),
                },
            ],
        },
    },
    {
        files: ["resources/js/**/*.{ts,tsx}"],
        plugins: { names: fileNamePlugin },
        rules: { "names/kebab-case": "error" },
    },
    {
        files: ["resources/js/**/*.{ts,tsx}"],
        ignores: [
            "**/*.stories.{ts,tsx}",
            "**/mocks/**",
            "**/__mocks__/**",
            "resources/js/Components/ui/sidebar/**",
        ],
        rules: { "max-lines": ["error", { max: MAX_FILE_LINES }] },
    },
    {
        files: ["resources/js/**/*.{jsx,tsx}"],
        // Inside Components/ui a class on a primitive is where a look is defined.
        ignores: ["resources/js/Components/ui/**"],
        rules: {
            "no-restricted-syntax": [
                "error",
                ...lucideAliasRules,
                arbitraryLengthRule,
                uiOverrideRule,
                ...formStructureRules,
                usePageGenericRule,
                useHttpNameRule,
                classNameTemplateRule,
                routerPollRule,
                httpInEffectRule,
                handWrittenDebounceRule,
                useFormMockRule,
                inertiaMockRule,
                componentDeclarationRule,
                hookDeclarationRule,
                reactNamespaceHookRule,
                ...generatedEnumUnionRules,
                ...focusedTestRules,
                ...sourceShapeRules,
            ],
        },
    },
    // A type assertion tells TypeScript to stop checking; narrow instead (a type guard, `satisfies`, `instanceof`, a
    // parser in lib for JSON and other untyped input). `as const` is not an assertion and stays allowed.
    {
        files: [
            "resources/js/**/*.{ts,tsx}",
            ".storybook/**/*.{ts,tsx}",
            "vitest.setup.ts",
            "vite.config.ts",
        ],
        rules: {
            "@typescript-eslint/consistent-type-assertions": ["error", { assertionStyle: "never" }],
        },
    },
    // category-exemptions: the blocks below switch a rule off by what a file is (declaration merging, the debug logger,
    // tests and stories), never by a file's name. The off and warn count of the ratchet script stops at this line.
    // Declaration merging into a package's types only works with interface.
    {
        files: ["resources/js/types/*.d.ts"],
        rules: { "@typescript-eslint/consistent-type-definitions": "off" },
    },
    {
        files: ["resources/js/lib/debug.ts"],
        rules: {
            "no-console": "off",
        },
    },
    {
        files: ["**/*.test.{ts,tsx}", "**/*.stories.{ts,tsx}"],
        rules: {
            "no-console": "off",
            // A story's canvas wrapper sizes itself with whatever width shows
            // the component; that is not app layout.
            "no-restricted-syntax": [
                "error",
                ...lucideAliasRules,
                usePageGenericRule,
                classNameTemplateRule,
                routerPollRule,
                httpInEffectRule,
                handWrittenDebounceRule,
                useFormMockRule,
                inertiaMockRule,
                componentDeclarationRule,
                hookDeclarationRule,
                reactNamespaceHookRule,
                ...focusedTestRules,
            ],
            "jsx-a11y/control-has-associated-label": "off",
            "jsx-a11y/anchor-is-valid": "off",
            // A render counter bumps a module-level variable while rendering on
            // purpose; counting renders is what the test measures.
            "react-hooks/immutability": "off",
            // vi.mocked(router).visit and expect(router.post) read a method off its object on purpose.
            "@typescript-eslint/unbound-method": "off",
        },
    },
    // The tooling files follow the same TypeScript rules as the app.
    {
        files: [".storybook/**/*.{ts,tsx}", "vitest.setup.ts", "vite.config.ts"],
        rules: {
            "@typescript-eslint/no-unnecessary-condition": "error",
            "@typescript-eslint/switch-exhaustiveness-check": "error",
            "@typescript-eslint/no-non-null-assertion": "error",
            "@typescript-eslint/prefer-nullish-coalescing": ["error", { ignorePrimitives: true }],
            "@typescript-eslint/consistent-type-definitions": ["error", "type"],
        },
    },
    // The scripts are plain Node modules without types, so the type-aware rules have nothing to read.
    {
        files: ["scripts/**/*.mjs"],
        extends: [tseslint.configs.disableTypeChecked],
        languageOptions: { globals: globals.node },
    },
    ...storybook.configs["flat/recommended"],
    eslintConfigPrettier
);
