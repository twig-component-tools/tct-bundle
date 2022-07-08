<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

use SimpleXMLElement;
use Symfony\Component\VarDumper\VarDumper;

class Component
{
    public int $originalLength;

    public bool $isSelfClosing;

    public int $originalEndPos;

    private array $blocks = [];

    private SimpleXMLElement $element;

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

        VarDumper::dump($this->openingTag);
        VarDumper::dump($this->openingTag[$startTagLength - 2]);

        // TODO Named contexts per file! Pass by reference? {% with %} scope within all blocks in the file. Use simple string replacements.

        $this->isSelfClosing = '/' === $this->openingTag[$startTagLength - 2];
        $codeStartingWithComponent = $this->isSelfClosing ? $openingTag : substr($code, $this->originalStartPos);

        try {
            $this->element = new SimpleXMLElement(
                "<tct-root>$codeStartingWithComponent</tct-root>",
                LIBXML_PARSEHUGE | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );
        } catch (\Throwable $exception) {
            VarDumper::dump($exception);
            VarDumper::dump("<tct-root>$codeStartingWithComponent</tct-root>");
die;
            return;
        }

        $this->element = $this->element->children()[0];

        $this->isSelfClosing = $this->element->hasChildren();
        $this->originalEndPos = $this->originalStartPos + $startTagLength;
        $this->originalLength = strlen($this->element->asXML());

        $this->parseBlocks();
        $this->setDefaultBlock();
    }

    /**
     * @throws \Exception
     */
    private function parseBlocks(): void
    {
        foreach ($this->element as $child) {
            if (!$child instanceof SimpleXMLElement) {
                continue;
            }

            if ($child->getName() !== 'block') {
                continue;
            }

            $nameAttribute = $child['name'];

            if (!$nameAttribute instanceof SimpleXMLElement) {
                continue;
            }

            $name = trim((string)$nameAttribute);
            $inner = $this->innerContents($child);
            $this->blocks[$name] = [uniqid($name), $inner];
        }
    }

    private function innerContents(SimpleXMLElement $node): string
    {
        $content = trim($node->asXML());
        $name = $node->getName();
        $endRootOpening = strpos($content, '>');
        $innerLength = strlen($content) - $endRootOpening - strlen("</$name>");

        return substr($content, $endRootOpening, $innerLength);
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

        foreach ($this->element->attributes as $key => $value) {
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
