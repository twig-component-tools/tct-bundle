<?php

namespace TwigComponentTools\TCTBundle\Naming;

class AtomicDesignComponentNaming implements ComponentNamingInterface
{
    private const TYPES = [
        'q' => 'Quark',
        'a' => 'Atom',
        'm' => 'Molecule',
        'o' => 'Organism',
        'p' => 'Page',
        't' => 'Template',
    ];

    private string $typeOptions;
    private array $typeAliases;

    public function __construct()
    {
        $this->typeOptions = array_reduce(self::TYPES, fn($carry, $type) => $carry.$type[0]);
        $this->typeAliases = array_map(fn($type) => '@' . strtolower($type), self::TYPES);
        $this->typeAliases[] = '@components';
    }

    /**
     * Return the component name if it fits one of the component aliases (e.g. @components or @page)
     */
    public function componentNameFromPath(string $path): ?string
    {
        $parts = explode('/', $path);

        $intersection = array_intersect($this->typeAliases, $parts);

        if (empty($intersection)) {
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

    public function getEntryName(
        string $componentName,
        ?string $entrypointName,
        array $extraAttributes,
        string $type
    ): string {
        return $componentName.$type;
    }

    /**
     * Discovers Pascal-case atomic design components.
     *
     * Examples:
     * QIcon
     * AButton
     * MContentCard
     * OPageHeader
     *
     * Group 1: Component Name
     *
     * @see https://regex101.com/r/3ujquL/3
     */
    public function getComponentRegex(): string
    {
        return "/<([$this->typeOptions][A-Z][a-zA-Z]+)[\/\s>]/ms";
    }
}
