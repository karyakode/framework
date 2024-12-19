<?php
namespace Flame\Core\Contracts;

interface DependencyResolverInterface
{
    public function resolve(string $class, array $params = [], bool $isFacade = true): object;
}
