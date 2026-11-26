# UI COMPONENTS

Atomic, reusable components based on shadcn/ui + Radix primitives. Which component to use for
which job, and how they should look, is in [`DESIGN.md`](../../../../DESIGN.md).

## STRUCTURE

Each component lives in a kebab-case directory:

```
ui/{component-name}/
├── {component-name}.tsx        # Implementation
└── {component-name}.stories.tsx # Storybook documentation
```

## ADDING NEW COMPONENT

1. Create directory: `ui/{kebab-case-name}/`
2. Create component: `{name}.tsx`
3. Create story: `{name}.stories.tsx` (REQUIRED)
4. Use Radix primitives from the `radix-ui` meta package (`import { Dialog as DialogPrimitive } from "radix-ui"`), as `shadcn add` generates them

## CONVENTIONS

- Use `cn()` from `@/lib/classnames` for class merging; ESLint fails a template literal in `className`
- Use `cva()` from `class-variance-authority` for variants
- Props type: `{ComponentName}Props`, a `type`, never an `interface`
- `ref` is a prop like any other (React 19), never `forwardRef`
- Support `className` prop for customization

## COLORS

Never write a literal color. The token table, the naming rules and when to use which role
live in [`DESIGN.md`](../../../../DESIGN.md#colors), together with the rest of the design rules.

## STORYBOOK

All components must have stories. Basic template:

```tsx
import type { Meta, StoryObj } from "@storybook/react-vite";
import { ComponentName } from "./component-name";

const meta: Meta<typeof ComponentName> = {
    title: "Components / {Group} / {Name}",
    component: ComponentName,
};
export default meta;

type Story = StoryObj<typeof ComponentName>;

export const Default: Story = {
    args: {/* props */},
};
```

## FORMS

Validation lives in the Laravel FormRequest, never in the browser. A form with
fields is a `FormLayout`: it renders `<form noValidate>`, so the browser's
constraint validation never blocks a submit or shows its own bubble, and it owns
the distance between sections. A form that only wraps the header, body and
footer of a dialog and sets no gap stays a plain `<form noValidate>`. The structure `FormLayout` > `FormSection` >
`FormField` and its measures are in DESIGN.md, Forms.

Mark a field required by reading its FormRequest, not by guessing. One `required`
prop drives both halves — the red asterisk and `aria-required` — on `InputField`
and `Label`, so the two cannot drift apart:

```tsx
<InputField id="name" label={t("...")} error={errors.name} required />
```

For a control that is not an `InputField`, wrap it in a `FormField` with
`required`; it marks the label and hands the control `aria-required`. `Label` with `asChild` cannot take
`required` (Radix clones a single child) — those callers render `RequiredMarker`
inside their own child.

Add the legend, `common.required_legend`, once per form that mixes required and
optional fields. A form where everything is required does not need it: an
asterisk on every field distinguishes nothing.

Two rules that are easy to get wrong:

- **A conditional rule needs a conditional prop.** A field that is required on
  create and nullable on update is marked on the create form only.
- **The marker is an element, not `::after` content.** Generated content is
  exposed to assistive tech by every current browser and cannot be hidden again,
  so the asterisk would be read as part of the name.

## FIELD ERRORS

`fieldAria` derives the ids and returns the ARIA set as one object to spread,
because the parts are worthless separately — an id nothing points at, or an
`aria-describedby` naming an element that was never rendered, both read as no
error at all. `FieldError` renders the message and returns `null` when there is
none, so no call site needs a conditional.

```tsx
<FormField id="product-description" label={t("...")} error={errors.name} required>
    {(control) => <Textarea {...control} />}
</FormField>
```

`FormField` calls `fieldAria` and renders the label, hint and `FieldError`; the
function gets the `id` and the ARIA set to spread. Call `fieldAria` yourself only
for a control that has no label of its own in the layout, such as a button that
describes itself with an error.

`InputField` is a `FormField` around a text input. Both need an `id`: without
one there is no error id and the message is silently left unassociated.

Reserve `role="alert"` for something that appears in response to an action. Text
that is simply part of the page reads in order and needs no live region.

## EXISTING COMPONENTS

Key components available:

- Layout: `sidebar`, `box`
- Forms: `button`, `input`, `password-input`, `select`, `checkbox`,
  `radio-group`, `form-layout`, `form-section`, `form-field`, `input-field`,
  `field-error`, `switch-field`
- Feedback: `alert-dialog`, `toast`, `tooltip`, `progress`
- Display: `avatar`, `badge`, `heading`, `file`
