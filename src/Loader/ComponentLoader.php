<?php

namespace TwigComponentTools\TCTBundle\Loader;

use Symfony\Component\VarDumper\VarDumper;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;
use TwigComponentTools\TCTBundle\Naming\ComponentNamingInterface;
use TwigComponentTools\TCTBundle\Preprocessor\PreprocessorInterface;

class ComponentLoader implements LoaderInterface, ComponentLoaderInterface
{
    private FilesystemLoader $filesystemLoader;

    private PreprocessorInterface $preprocessor;

    private ComponentNamingInterface $componentNaming;

    private array $loadedComponents = [];

    public function __construct(
        FilesystemLoader $filesystemLoader,
        PreprocessorInterface $preprocessor,
        ComponentNamingInterface $componentNaming
    ) {
        $this->filesystemLoader = $filesystemLoader;
        $this->preprocessor     = $preprocessor;
        $this->componentNaming  = $componentNaming;
    }

    public function getSourceContext(string $name): Source
    {
        $source = $this->filesystemLoader->getSourceContext($name);

        return $this->preprocessor->processSourceContext($source);
    }

    public function getCacheKey(string $name): string
    {
        $componentName = $this->componentNaming->componentNameFromPath($name);

        if (null !== $componentName && !in_array($componentName, $this->loadedComponents)) {
            $this->loadedComponents[] = $componentName;
        }

        return $this->filesystemLoader->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->filesystemLoader->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        return $this->filesystemLoader->exists($name);
    }

    public function getLoadedComponents(): array
    {
        return $this->loadedComponents;
    }

    public function reset(): void
    {
        $this->loadedComponents = [];
    }
}
