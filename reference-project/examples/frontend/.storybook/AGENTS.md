# STORYBOOK CONFIGURATION

Component documentation with Laravel/Inertia mocking layer.

## MOCKING LAYER

Storybook runs without Laravel backend. Key mocks:

| File                          | Purpose                                    |
| ----------------------------- | ------------------------------------------ |
| `mocks/inertiajs-react.tsx`   | Mocks `usePage`, `useForm`, `Link`, `Head` |
| `mocks/laravel-echo-react.ts` | Mocks `useEcho` and the connection status  |
| `plugins/ziggy.tsx`           | Mocks Laravel route generation             |
| `theme-decorator.tsx`         | Injects the theme CSS variables            |

## STORY PARAMETERS

Override mocked data per story:

```tsx
export const CustomProps: Story = {
    parameters: {
        inertia: {
            sharedProps: {/* usePage().props */},
        },
        ziggy: {
            routes: {/* route definitions */},
        },
    },
};
```

## DECORATORS (Auto-applied)

1. **ThemeDecorator**: Sets `--color-primary` etc. on `:root`
2. **inertiaDecorator**: Provides mock Inertia state
3. **ziggyDecorator**: Enables `route()` function
4. **tooltipDecorator**: Wraps every story in a `TooltipProvider`, as the app shell does

## COMMANDS

```bash
npm run storybook        # Dev server on :6006
npm run build-storybook  # Static build
```

## CONVENTIONS

- Stories co-located: `{component}.stories.tsx`
- Use `title: "Components / {Group} / {Name}"` or `title: "Features / {Feature} / {Name}"`, with spaces around every `/`
- Include multiple variants (Default, Disabled, Loading, etc.)
