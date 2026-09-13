<?php

namespace App\Http\Controllers;

use InvalidArgumentException;
use ReflectionMethod;
use ReflectionNamedType;

abstract class Controller
{
    /**
     * {locale} プレフィックスをコントローラ引数の先頭に流し込まない。
     * ルートパラメータはメソッド引数名（および型）で渡す。
     */
    public function callAction($method, $parameters)
    {
        $usedKeys = [];
        $args = [];
        $reflection = new ReflectionMethod($this, $method);

        foreach ($reflection->getParameters() as $parameter) {
            $name = $parameter->getName();

            if (array_key_exists($name, $parameters)) {
                $args[] = $parameters[$name];
                $usedKeys[$name] = true;
                continue;
            }

            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
                $class = $type->getName();
                foreach ($parameters as $key => $value) {
                    if (isset($usedKeys[$key]) || ! is_object($value)) {
                        continue;
                    }
                    if ($value instanceof $class) {
                        $args[] = $value;
                        $usedKeys[$key] = true;
                        continue 2;
                    }
                }
            }

            foreach ($parameters as $key => $value) {
                if ($key === 'locale' || isset($usedKeys[$key]) || is_object($value)) {
                    continue;
                }
                $args[] = $value;
                $usedKeys[$key] = true;
                continue 2;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            if ($parameter->allowsNull()) {
                $args[] = null;
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'Unable to resolve parameter $%s for %s::%s',
                $name,
                static::class,
                $method
            ));
        }

        return $this->{$method}(...$args);
    }
}
