<?php

namespace TwigComponentTools\TCTBundle\Naming;

class AtomicDesignComponentNaming implements ComponentNamingInterface
{
    private const TYPES = [
        'q' => 'Quark',
        'a' => 'Atom',
        'm' => 'Molecule',
        'o' => 'Organism',
    ];

    private string $typeOptions;

    public function __construct()
    {
        $this->typeOptions = array_reduce(self::TYPES, fn ($carry, $type) => $carry.$type[0]);
    }

    public function componentNameFromPath(string $path): ?string
    {
        $parts = explode('/', $path);

        if ($parts[0] !== '@components') {
            return null;
        }

        return str_replace('.twig', '', $parts[count($parts) - 1]);
    }

    public function pathFromComponentName(string $componentName): string
    {
        return implode(DIRECTORY_SEPARATOR, [
            '@components',
            self::TYPES[strtolower($componentName[0])],
            $componentName,
            "{$componentName}.twig",
        ]);
    }

    public function selectorFromName(string $name, string $entryName): string
    {
        return join([
            '.',
            strtolower($name[0]),
            '-',
            lcfirst(substr($name, 1)),
        ]);
    }

    public function getEntryName(
        string $componentName,
        ?string $entrypointName,
        array $extraAttributes,
        string $type
    ): string {
        return $componentName.$type;
    }

    /**
     * Discovers Pascal-case atomic design components
     *
     * Examples:
     * QIcon
     * AButton
     * MContentCard
     * OPageHeader
     *
     * @see https://regex101.com/r/3ujquL/2
     */
    public function getComponentRegex(): string
    {
        return "/<([{$this->typeOptions}][A-Z][a-zA-Z]+\b)\s*([a-zA-Z]+=.+\")?\s*\/?>/msU";
    }
}