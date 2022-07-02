<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

class Component
{
    public int $originalLength;

    public bool $selfClosing;

    public int $originalEndPos;

    private array $blocks = [];

    public function __construct(
        public string $tag,
        public string $name,
        public string $attributes,
        public int $originalStartPos
    ) {
        $this->originalLength = strlen($tag);
        $this->originalEndPos = $originalStartPos + $this->originalLength;
        $this->selfClosing = '/' === $tag[$this->originalLength - 2];
    }

    public function render(): string
    {

    }
}