<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\EncryptedPrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\InterpolatedStringPart;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;

final readonly class BroadcastChannelGuard
{
    private const array CHANNEL_CLASSES = [Channel::class, PrivateChannel::class, PresenceChannel::class, EncryptedPrivateChannel::class];

    /**
     * @param string $source the folder with the code that opens channels, relative to the root
     * @param string $registry the file that registers the channels, relative to the root
     * @param string $testFolder the folder of the channel tests, relative to the root
     */
    public function __construct(
        private PhpFileInspector $inspector,
        private string $root,
        private string $source,
        private string $registry,
        private string $testFolder,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $registered = $this->registered();
        $violations = [];

        foreach ($this->inspector->phpFiles($this->root . '/' . $this->source) as $file) {
            foreach (self::channelsIn((string) file_get_contents($file)) as $line => $prefix) {
                $where = mb_substr($file, mb_strlen($this->root) + 1) . ':' . $line;

                if ($prefix === null) {
                    $violations[] = $where . ': a broadcast channel whose name does not start with text: start it with the prefix of a channel in ' . $this->registry . ", as in new PrivateChannel('warehouse.' . \$warehouse->getRouteKey())";
                } elseif (! in_array($prefix, $registered, true)) {
                    $violations[] = $where . ': the broadcast channel ' . $prefix . ' is not registered: add Broadcast::channel(\'' . $prefix . '.{model}\', ...) with its authorization to ' . $this->registry;
                }
            }
        }

        foreach ($registered as $prefix) {
            $test = $this->testFolder . '/' . Str::studly($prefix) . 'ChannelTest.php';

            if (! is_file($this->root . '/' . $test)) {
                $violations[] = $this->registry . ': the channel ' . $prefix . ' has no authorization test: add ' . $test . ' that joins it as the owner and is refused as someone else';
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function registered(): array
    {
        $file = $this->root . '/' . $this->registry;

        return is_file($file) ? self::registeredIn((string) file_get_contents($file)) : [];
    }

    /**
     * @return list<string>
     */
    public static function registeredIn(string $source): array
    {
        $prefixes = [];

        foreach ((new NodeFinder())->findInstanceOf(self::statements($source), StaticCall::class) as $call) {
            $first = $call->args[0] ?? null;

            if ($call->class instanceof Name && $call->class->getLast() === 'Broadcast' && $call->name instanceof Identifier && $call->name->toString() === 'channel' && $first instanceof Node\Arg) {
                $prefix = self::prefix($first->value);

                if ($prefix !== null) {
                    $prefixes[] = $prefix;
                }
            }
        }

        return $prefixes;
    }

    /**
     * @return array<int, string|null>
     */
    public static function channelsIn(string $source): array
    {
        $channels = [];

        foreach ((new NodeFinder())->findInstanceOf(self::statements($source), New_::class) as $new) {
            $first = $new->args[0] ?? null;

            if ($new->class instanceof Name && in_array(ltrim($new->class->toString(), '\\'), self::CHANNEL_CLASSES, true) && $first instanceof Node\Arg) {
                $channels[$new->getStartLine()] = self::prefix($first->value);
            }
        }

        return $channels;
    }

    private static function prefix(Node $name): ?string
    {
        while ($name instanceof Concat) {
            $name = $name->left;
        }

        $text = match (true) {
            $name instanceof String_ => $name->value,
            $name instanceof InterpolatedString && $name->parts[0] instanceof InterpolatedStringPart => $name->parts[0]->value,
            default => '',
        };

        $prefix = explode('.', $text)[0];

        return $prefix === '' ? null : $prefix;
    }

    /**
     * @return array<Node>
     */
    private static function statements(string $source): array
    {
        return PhpSource::resolved($source);
    }
}
