<?php

/**
 * Projects4Me Copyright (c) 2026. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
 */

namespace GaiaDev\Extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use RuntimeException;

/**
 * Reads a PHP source file into its syntax tree with nikic/php-parser, and offers
 * the few tree queries the code extractors share, so none of them touches the
 * parser directly. Source files are parsed, never included.
 *
 * @author Hammad Hassan <gollomer@gmail.com>
 * @package GaiaDev\Extractor
 * @license http://www.gnu.org/licenses/agpl.html AGPLv3
 */
class PhpSource
{
    /**
     * @param string $path
     * @return Node[] the file's top-level statements
     */
    public static function parse(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException("Source file not found: {$path}");
        }

        try {
            $statements = (new ParserFactory())->create(ParserFactory::PREFER_PHP7)->parse(file_get_contents($path));
        } catch (\PhpParser\Error $e) {
            throw new RuntimeException("{$path}: " . $e->getMessage(), 0, $e);
        }

        return $statements ?: [];
    }

    /**
     * @param Node[] $statements
     * @return ClassLike[] classes, interfaces and traits declared in the file
     */
    public static function classes(array $statements): array
    {
        return array_values(array_filter(
            (new NodeFinder())->findInstanceOf($statements, ClassLike::class),
            function (ClassLike $class) {
                return $class->name !== null;
            }
        ));
    }

    /**
     * @param Node[] $statements
     * @return string namespace of the file, '' when it has none
     */
    public static function namespaceOf(array $statements): string
    {
        foreach ($statements as $statement) {
            if ($statement instanceof Node\Stmt\Namespace_) {
                return $statement->name ? $statement->name->toString() : '';
            }
        }

        return '';
    }

    /**
     * @param ClassLike $class
     * @return string[] names of the public, non-static, non-magic methods in source order
     */
    public static function publicMethods(ClassLike $class): array
    {
        $names = [];
        foreach ($class->getMethods() as $method) {
            $name = $method->name->toString();
            if ($method->isPublic() && !$method->isStatic() && strpos($name, '__') !== 0) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The default value of a property, when it is a literal.
     *
     * @param ClassLike $class
     * @param string $name property name without the dollar sign
     * @return mixed|null
     */
    public static function propertyDefault(ClassLike $class, string $name)
    {
        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $prop) {
                if ($prop->name->toString() === $name && $prop->default !== null) {
                    return self::literal($prop->default);
                }
            }
        }

        return null;
    }

    /**
     * @param ClassLike $class
     * @return array name => literal value of the class constants
     */
    public static function constants(ClassLike $class): array
    {
        $constants = [];
        foreach ($class->stmts as $statement) {
            if ($statement instanceof Node\Stmt\ClassConst) {
                foreach ($statement->consts as $const) {
                    $constants[$const->name->toString()] = self::literal($const->value);
                }
            }
        }

        return $constants;
    }

    /**
     * Evaluate a literal expression: scalars, booleans and arrays of literals.
     * Anything else (calls, class constants, `::class`) gives null.
     *
     * @param Node\Expr $expr
     * @return mixed|null
     */
    public static function literal(Node\Expr $expr)
    {
        if ($expr instanceof Node\Scalar\String_ || $expr instanceof Node\Scalar\LNumber
            || $expr instanceof Node\Scalar\DNumber) {
            return $expr->value;
        }
        if ($expr instanceof Node\Expr\ConstFetch) {
            $constant = strtolower($expr->name->toString());
            return ['true' => true, 'false' => false][$constant] ?? null;
        }
        if ($expr instanceof Node\Expr\Array_) {
            $result = [];
            foreach ($expr->items as $item) {
                if ($item === null) {
                    continue;
                }
                $value = self::literal($item->value);
                if ($item->key === null) {
                    $result[] = $value;
                } else {
                    $result[self::literal($item->key)] = $value;
                }
            }
            return $result;
        }

        return null;
    }

    /**
     * @param Node\Name|null $name
     * @return string|null
     */
    public static function nameToString($name)
    {
        return $name === null ? null : $name->toString();
    }
}
