<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

use SimpleXMLElement;

class Component
{
    public int $originalLength;

    public bool $isSelfClosing;

    public bool $usesDefaultBlock;

    public int $originalEndPos;

    private array $blocks = [];

    private ?string $inner = null;

    private SimpleXMLElement $simpleXMLElement;

    private mixed $simpleXMLBLocks;

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

        $this->isSelfClosing  = '/' === $openingTag[$startTagLength - 2];
        $this->originalEndPos = $this->originalStartPos + $startTagLength;

        if (!$this->isSelfClosing) {
            $closingTag        = "</$name>";
            $endOfOpeningTag   = $this->originalStartPos + $startTagLength;
            $startOfClosingTag = strpos($code, $closingTag, $endOfOpeningTag);

            $this->originalEndPos = $startOfClosingTag + strlen($closingTag);
            $this->inner          = substr($code, $endOfOpeningTag, $startOfClosingTag - $endOfOpeningTag);
        }

        $this->originalLength = $this->originalEndPos - $this->originalStartPos;
        $fullComponentCode    = substr($code, $this->originalStartPos, $this->originalLength);

        if ($this->isSelfClosing) {
            $fullComponentCode = str_replace('/>', "></$name>", $fullComponentCode);
        }

        $this->simpleXMLElement = new SimpleXMLElement($fullComponentCode);
        $this->simpleXMLBLocks  = $this->simpleXMLElement->xpath("/$name/block");
        $this->usesDefaultBlock = empty($this->simpleXMLBLocks);
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
        $attributes      = $this->simpleXMLElement->attributes();

        /**
         * @var SimpleXMLElement $value
         */
        foreach ($attributes as $key => $value) {
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
        if ($this->usesDefaultBlock) {
            return $this->getBlockSet('default', $this->inner);
        }

        if (!is_iterable($this->simpleXMLBLocks)) {
            return null;
        }

        $parts = [];
        foreach ($this->simpleXMLBLocks as $xmlBlock) {
            $attributes = $xmlBlock->attributes();
            $name       = $attributes['name'];

            $childParts = [];
            foreach ($xmlBlock->children() as $child) {
                $childParts[] = $child->asXml();
            }

            $parts[] = $this->getBlockSet($name, join(PHP_EOL, $childParts));
        }

        return join(PHP_EOL, $parts);
    }

    private function getBlockSet(string $name, string $contents): string
    {
        $id = uniqid($name.'_');

        $this->blocks[$name] = $id;

        return "{%- set $id -%}$contents{%- endset -%}";
    }

    private function getAllBlockPrints(): ?string
    {
        $parts = [];

        foreach ($this->blocks as $name => $id) {
            $parts[] = "{%- block $name -%}{{- $id -}}{%- endblock -%}";
        }

        return join(PHP_EOL, $parts);
    }
}
