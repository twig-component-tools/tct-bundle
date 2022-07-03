<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

class Component
{
    public int $originalLength;

    public bool $isSelfClosing;

    public int $originalEndPos;

    private array $blocks = [];

    private array $attributes = [];

    private ?string $inner = null;

    /**
     * @throws \Exception
     */
    public function __construct(
        public string $openingTag,
        public string $name,
        public string $filePath,
        public int $originalStartPos,
        string $code
    ) {
        $startTagLength = strlen($openingTag);

        $this->isSelfClosing = '/' === $openingTag[$startTagLength - 2];
        $this->originalEndPos = $this->originalStartPos + $startTagLength;

        if (!$this->isSelfClosing) {
            $closingTag = "</$name>";
            $endOfOpeningTag = $this->originalStartPos + $startTagLength;
            $startOfClosingTag = strpos($code, $closingTag, $endOfOpeningTag);

            $this->originalEndPos = $startOfClosingTag + strlen($closingTag);
            $this->inner = substr($code, $endOfOpeningTag, $startOfClosingTag - $endOfOpeningTag);
        }

        $this->originalLength = $this->originalEndPos - $this->originalStartPos;

        $this->parseAttributes();
        $this->parseBlocks();
        $this->setDefaultBlock();
    }

    private function parseAttributes(): void
    {
        $attributesString = str_replace(["<$this->name", '/', '>'], '', $this->openingTag);
        $attributeMatches = [];
        preg_match_all('/(.*)="(.*)"/', $attributesString, $attributeMatches, PREG_SET_ORDER);

        foreach ($attributeMatches as $match) {
            $name = trim($match[1]);
            $value = $match[2];
            $this->attributes[$name] = $value;
        }
    }

    private function parseBlocks(): void
    {
        $blockMatches = [];
        preg_match_all('/<block.*name="(.*)">(.*)<\/block>/', $this->inner, $blockMatches, PREG_SET_ORDER);

        foreach ($blockMatches as $match) {
            $name = trim($match[1]);
            $inner = $match[2];
            $this->blocks[$name] = [uniqid($name), $inner];
        }
    }

    private function setDefaultBlock(): void
    {
        if (empty($this->blocks) && !empty($this->inner)) {
            $this->blocks['default'] = [uniqid('default'), $this->inner];
        }
    }

    public function render(): string
    {
        if ($this->isSelfClosing) {
            return $this->renderInclude();
        }

        return $this->renderEmbed();
    }

    private function renderInclude(): string
    {
        $parameterMap = $this->getTwigParameterMap();

        return "{% include '$this->filePath' with { props: { $parameterMap } } %}";
    }

    private function getTwigParameterMap(): string
    {
        $attributeObject = [];

        foreach ($this->attributes as $key => $value) {
            $isVar = str_starts_with($value, '{{') && str_ends_with($value, '}}');

            if ($isVar) {
                $value = trim(substr($value, 2, -2));
            }

            $escape = $isVar ? '' : '\'';

            if (!$isVar) {
                $value = str_replace('\'', '\\\'', $value);
            }

            $attributeObject[] = "$key: $escape$value$escape";
        }

        return implode(', ', $attributeObject);
    }

    private function renderEmbed(): string
    {
        $parameterMap = $this->getTwigParameterMap();

        $parts = array_filter([
            $this->getAllBlockSets(),
            "{% embed '$this->filePath' with { props: { $parameterMap } } %}",
            $this->getAllBlockPrints(),
            '{% endembed %}',
        ]);

        return join(PHP_EOL, $parts);
    }

    private function getAllBlockSets(): ?string
    {
        $parts = [];

        foreach ($this->blocks as [$id, $inner]) {
            $parts[] = $this->getBlockSet($id, $inner);
        }

        return join(PHP_EOL, $parts);
    }

    private function getBlockSet(string $id, string $contents): string
    {
        return "{%- set $id -%}$contents{%- endset -%}";
    }

    private function getAllBlockPrints(): ?string
    {
        $parts = [];

        foreach ($this->blocks as $name => [$id, $inner]) {
            $parts[] = "{%- block $name -%}{{- $id -}}{%- endblock -%}";
        }

        return join(PHP_EOL, $parts);
    }
}
