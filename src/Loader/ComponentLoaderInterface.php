<?php

namespace TwigComponentTools\TCTBundle\Loader;

interface ComponentLoaderInterface
{
    public function getLoadedComponents(): array;

    public function reset(): void;
}