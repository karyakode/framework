<?php
namespace Kodhe\Pulen\Framework\Contracts;

interface DependencyResolverInterface
{
    public function resolve(string $class, array $params = [], bool $isFacade = true): object;
}
