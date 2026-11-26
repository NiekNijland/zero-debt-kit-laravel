<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

/**
 * A controller is named `<Verb>Controller` and sits in a folder named after what it acts on: the verb is one of a list, the class is not in the
 * controller root, and every verb of the list is used by at least one controller (a verb nobody uses is stale).
 */
final readonly class ControllerNameGuard
{
    /**
     * @param list<string> $verbs the verbs a controller may carry
     * @param string $namespace the controller root namespace, without a trailing backslash
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $folder,
        private array $verbs,
        private string $namespace,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];
        $used = [];
        $controllers = 0;

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->folder) as $file) {
            foreach (array_keys($this->inspector->declaredTypes($file)) as $type) {
                $controllers++;
                $message = $this->inType($type);

                if ($message === null) {
                    $used[basename(str_replace('\\', '/', $type), 'Controller')] = true;
                } else {
                    $violations[] = $type . ': ' . $message;
                }
            }
        }

        if ($controllers === 0) {
            $violations[] = $this->folder . ': no controller was read, so the guard has read nothing: is the folder renamed?';
        }

        foreach (array_diff($this->verbs, array_keys($used)) as $verb) {
            $violations[] = 'the verb ' . $verb . ' is on the list and no controller carries it: remove it from the list';
        }

        return $violations;
    }

    /**
     * @param string $type the fully qualified name of a controller
     */
    public function inType(string $type): ?string
    {
        $short = basename(str_replace('\\', '/', $type));
        $namespace = mb_substr($type, 0, -mb_strlen($short) - 1);
        $verb = mb_substr($short, 0, -mb_strlen('Controller'));

        if ($namespace === $this->namespace) {
            return 'a controller sits in the controller root: put it in a folder named after what it acts on';
        }

        if (! str_ends_with($short, 'Controller')) {
            return 'a controller is named <Verb>Controller';
        }

        if (! in_array($verb, $this->verbs, true)) {
            return 'the verb ' . $verb . ' is not one of ' . implode(', ', $this->verbs) . ': name the controller after one of them';
        }

        return null;
    }
}
