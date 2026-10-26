<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;

/**
 * Extracts the assertion vocabulary of the api-tester: the `expects[...]` keys
 * `Asserter::assert()` checks, and the `mail.*` and `hermes.*` keys `Runner`
 * checks. For each: where it is checked, the failure messages that say what it
 * means, and the allowed values of `shape`.
 *
 * Names match the ones CatalogExtractor reads from the case files (`status`,
 * `attributesEqual`, `mail.subject`, `hermes.expect`).
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class AssertionExtractor
{
    public const ASSERTER = 'tools/api-tester/src/Asserter.php';
    public const RUNNER = 'tools/api-tester/src/Runner.php';

    /**
     * Runner method => [variable holding the case group, assertion prefix, run options that are not assertions].
     */
    private const RUNNER_GROUPS = [
        'assertMail' => ['expect', 'mail', ['timeoutMs']],
        'runCase' => ['hermes', 'hermes', ['timeoutMs', 'requireUp']],
    ];

    /**
     * @var string
     */
    private $root;

    /**
     * @param string $root repository root
     */
    public function __construct(string $root)
    {
        $this->root = rtrim($root, '/\\');
    }

    /**
     * @return array[] per assertion, sorted by name: name, source (file), line, messages, values
     */
    public function extract(): array
    {
        $records = $this->fromAsserter();
        foreach ($this->fromRunner() as $name => $record) {
            $records[$name] = $record;
        }
        ksort($records, SORT_STRING);

        return array_values($records);
    }

    /**
     * @return array[] name => record
     */
    private function fromAsserter(): array
    {
        $method = $this->method(self::ASSERTER, 'assert');
        $finder = new NodeFinder();
        $records = [];

        foreach ($method->stmts as $statement) {
            $keys = $this->keysRead($finder, $statement, 'expects');
            $messages = $this->failureMessages($finder, $statement);
            foreach ($keys as $key => $line) {
                if (!isset($records[$key])) {
                    $records[$key] = [
                        'name' => $key,
                        'source' => self::ASSERTER,
                        'line' => $line,
                        'messages' => [],
                        'values' => [],
                    ];
                }
                $own = array_filter($messages, function ($message) use ($key) {
                    return strpos($message, $key . ':') === 0;
                });
                $records[$key]['messages'] = array_values(array_unique(array_merge(
                    $records[$key]['messages'],
                    $own ?: (count($keys) === 1 ? $messages : [])
                )));
            }
        }

        if (isset($records['shape'])) {
            $records['shape']['values'] = $this->shapeValues($finder, $method);
        }

        return $records;
    }

    /**
     * @return array[] name => record
     */
    private function fromRunner(): array
    {
        $finder = new NodeFinder();
        $records = [];

        foreach (self::RUNNER_GROUPS as $methodName => list($variable, $prefix, $options)) {
            $method = $this->method(self::RUNNER, $methodName);
            $keys = $this->keysRead($finder, $method, $variable);
            foreach ($finder->findInstanceOf($method, Node\Stmt\Foreach_::class) as $foreach) {
                if ($foreach->expr instanceof Node\Expr\Array_) {
                    foreach (array_keys(PhpSource::literal($foreach->expr) ?: []) as $key) {
                        $keys[$key] = $foreach->getLine();
                    }
                }
            }
            foreach ($keys as $key => $line) {
                if (!in_array($key, $options, true)) {
                    $records[$prefix . '.' . $key] = [
                        'name' => $prefix . '.' . $key,
                        'source' => self::RUNNER,
                        'line' => $line,
                        'messages' => [],
                        'values' => [],
                    ];
                }
            }
        }

        return $records;
    }

    /**
     * @param string $file
     * @param string $name
     * @return ClassMethod
     */
    private function method(string $file, string $name): ClassMethod
    {
        $statements = PhpSource::parse($this->root . '/' . $file);
        foreach (PhpSource::classes($statements) as $class) {
            $method = $class->getMethod($name);
            if ($method !== null) {
                return $method;
            }
        }

        throw new \RuntimeException("{$file} has no method {$name}()");
    }

    /**
     * String keys read from `$<variable>['key']` or tested with `array_key_exists('key', $<variable>)`.
     *
     * @param NodeFinder $finder
     * @param Node|Node[] $in
     * @param string $variable
     * @return int[] key => first line
     */
    private function keysRead(NodeFinder $finder, $in, string $variable): array
    {
        $keys = [];
        foreach ($finder->findInstanceOf($in, Node\Expr\ArrayDimFetch::class) as $fetch) {
            if ($this->isVariable($fetch->var, $variable) && $fetch->dim instanceof Node\Scalar\String_) {
                $keys[$fetch->dim->value] = $keys[$fetch->dim->value] ?? $fetch->getLine();
            }
        }
        foreach ($finder->findInstanceOf($in, Node\Expr\FuncCall::class) as $call) {
            if ($call->name instanceof Node\Name && $call->name->toString() === 'array_key_exists'
                && isset($call->args[1]) && $this->isVariable($call->args[1]->value, $variable)
                && $call->args[0]->value instanceof Node\Scalar\String_) {
                $key = $call->args[0]->value->value;
                $keys[$key] = $keys[$key] ?? $call->getLine();
            }
        }

        return $keys;
    }

    /**
     * @param Node $node
     * @param string $name
     * @return bool
     */
    private function isVariable(Node $node, string $name): bool
    {
        return $node instanceof Node\Expr\Variable && $node->name === $name;
    }

    /**
     * The literal text each `$failures[] = ...` message in the statement starts with.
     *
     * @param NodeFinder $finder
     * @param Node $statement
     * @return string[]
     */
    private function failureMessages(NodeFinder $finder, Node $statement): array
    {
        $messages = [];
        foreach ($finder->findInstanceOf($statement, Node\Expr\Assign::class) as $assign) {
            if ($assign->var instanceof Node\Expr\ArrayDimFetch && $assign->var->dim === null
                && $this->isVariable($assign->var->var, 'failures')) {
                $text = $this->leadingText($assign->expr);
                if ($text !== '') {
                    $messages[] = $text;
                }
            }
        }

        return $messages;
    }

    /**
     * @param Node\Expr $expr
     * @return string
     */
    private function leadingText(Node\Expr $expr): string
    {
        if ($expr instanceof Node\Scalar\String_) {
            return $expr->value;
        }
        if ($expr instanceof Node\Scalar\Encapsed) {
            $parts = '';
            foreach ($expr->parts as $part) {
                $parts .= $part instanceof Node\Scalar\EncapsedStringPart ? $part->value : '…';
            }
            return $parts;
        }
        if ($expr instanceof Node\Expr\BinaryOp\Concat) {
            return $this->leadingText($expr->left);
        }
        if ($expr instanceof Node\Expr\FuncCall && $expr->name instanceof Node\Name
            && $expr->name->toString() === 'sprintf' && isset($expr->args[0])) {
            return $this->leadingText($expr->args[0]->value);
        }

        return '';
    }

    /**
     * The strings `$shape` is compared with.
     *
     * @param NodeFinder $finder
     * @param ClassMethod $method
     * @return string[]
     */
    private function shapeValues(NodeFinder $finder, ClassMethod $method): array
    {
        $values = [];
        foreach ($finder->findInstanceOf($method, Node\Expr\BinaryOp\Identical::class) as $comparison) {
            if ($this->isVariable($comparison->left, 'shape') && $comparison->right instanceof Node\Scalar\String_) {
                $values[] = $comparison->right->value;
            }
        }
        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }
}
