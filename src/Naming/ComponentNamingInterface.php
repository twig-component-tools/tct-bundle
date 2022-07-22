<?php

namespace TwigComponentTools\TCTBundle\Naming;

interface ComponentNamingInterface
{
    public function componentNameFromPath(string $path): ?string;

    public function pathFromComponentName(string $componentName): string;

    public function getEntryName(string $componentName, ?string $entrypointName, array $extraAttributes, string $type);

    public function getComponentRegex(): string;
}