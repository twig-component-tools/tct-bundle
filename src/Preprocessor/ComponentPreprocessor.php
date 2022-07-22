<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use Twig\Source;
use TwigComponentTools\TCTBundle\Naming\ComponentNamingInterface;

class ComponentPreprocessor implements PreprocessorInterface
{
    private ComponentNamingInterface $componentNaming;

    private string $componentRegex;

    public function __construct(ComponentNamingInterface $componentNaming)
    {
        $this->componentNaming = $componentNaming;
        $this->componentRegex = $this->componentNaming->getComponentRegex();
    }

    public function processSourceContext(Source $source): Source
    {
        $nextComponent = [];
        $components = [];
        $lastPosition = 0;
        $code = $this->getSanitizedCode($source);
        $embedId = uniqid('context');

        while ($this->getNextComponent($code, $lastPosition, $nextComponent)) {
            [
                'name'          => $name,
                'startPosition' => $startPosition,
                'attributes'    => $attributes,
                'selfClosing'   => $selfClosing,
                'length'        => $openingTagLength,
            ] = $nextComponent;

            if (!in_array($name, $components)) {
                $components[] = $name;
            }

            $code = $this->replaceOpeningTag(
                $code,
                $name,
                $attributes,
                $startPosition,
                $openingTagLength,
                $selfClosing
            );

            $lastPosition = $startPosition + 1;
        }

        if (!empty($components)) {
            $code = $this->replaceClosingTags($code, $components);
            $code = $this->replaceBlocks($code, $embedId);
            $code = $this->replaceDefaultBlocks($code, $embedId);
            $code = $this->insertEmbedId($code, $embedId);

            return new Source($code, $source->getName(), $source->getPath());
        }

        return $source;
    }

    /**
     * @see https://regex101.com/r/eVctpW/1
     * @noinspection PhpUnnecessaryLocalVariableInspection
     */
    private function getSanitizedCode(Source $source): string
    {
        $code = $source->getCode();
        $code = preg_replace("/\{#.*#}/sU", '', $code);

        return $code;
    }

    private function createDOMDocument(string $code): DOMDocument
    {
        libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $parsingCode = mb_convert_encoding("<tct-root>$code</tct-root>", 'HTML-ENTITIES', 'UTF-8');
        $document->loadHTML($parsingCode, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOXMLDECL);
        libxml_use_internal_errors(false);

        return $document;
    }

    public function getNextComponent(string $code, int $lastPosition, array &$component): bool
    {
        $matches = [];

        $found = preg_match(
            $this->componentRegex,
            $code,
            $matches,
            PREG_OFFSET_CAPTURE,
            $lastPosition
        );

        if (0 === $found) {
            return false;
        }

        $fullMatch = $matches[0][0];
        $start = $matches[0][1];
        $name = $matches[1][0];
        $selector = strtolower($name);
        $document = $this->createDOMDocument(substr($code, $start));
        $root = $document->childNodes->item(0);

        if (!$root instanceof DOMElement) {
            return false;
        }

        $componentElement = $root->childNodes->item(0);
        if (!$componentElement instanceof DOMElement || $componentElement->tagName !== $selector) {
            return false;
        }

        $attributes = $this->getTwigParameterMap($componentElement->attributes);
        $numberOfGreaterThan = substr_count($attributes, ">");
        $positionOfEndGreaterThan = $start;
        for ($index = 0; $index < $numberOfGreaterThan + 1; $index++) {
            $positionOfEndGreaterThan = strpos($code, ">", $positionOfEndGreaterThan);
        }

        $length = $positionOfEndGreaterThan - $start + 1;

        $component = [
            'name'          => $name,
            'startPosition' => $start,
            'attributes'    => $attributes,
            'selfClosing'   => '/' === $code[$positionOfEndGreaterThan - 1],
            'length'        => $length,
        ];

        return true;
    }

    private function replaceClosingTags(string $code, array $components): string
    {
        foreach ($components as $component) {
            $code = str_replace("</$component>", "{% endembed %}", $code);
        }

        return $code;
    }

    private function replaceDefaultBlocks(string $code, string $embedId): string
    {
        $code = $this->replaceDefaultBlockStarts($code, $embedId);
        $code = $this->replaceDefaultBlockEnds($code);

        return $code;
    }

    private function replaceDefaultBlockStarts(string $code, string $embedId): string
    {
        $nextEmbed = [];
        $lastPosition = 0;

        while (preg_match("/{% embed .* %}/sU", $code, $nextEmbed, PREG_OFFSET_CAPTURE, $lastPosition)) {
            $match = $nextEmbed[0][0]; // [fullMatch][match]
            $position = mb_strlen($match) + $nextEmbed[0][1]; // [fullMatch][offset]
            $nextCode = trim(substr($code, $position));
            $lastPosition = $position + 1;

            if (0 === strpos($nextCode, '{% block')) {
                continue;
            }

            $code = substr_replace(
                $code,
                "\n{% block default %}\n{% with { props: $embedId } %}",
                $position,
                0
            );
        }

        return $code;
    }

    private function replaceDefaultBlockEnds(string $code): string
    {
        $reversed = strrev($code);
        $expectedString = strrev('{% endblock %}');
        $replaceString = strrev("\n{% endwith %}\n{% endblock %}\n");
        $endEmbed = strrev('{% endembed %}');
        $positionOffset = strlen($endEmbed);
        $lastPosition = 0;

        while (false !== ($position = strpos($reversed, $endEmbed, $lastPosition))) {
            $position += $positionOffset;
            $nextCode = trim(substr($reversed, $position));
            $lastPosition = $position + 1;

            if (0 === strpos($nextCode, $expectedString)) {
                continue;
            }

            $reversed = substr_replace(
                $reversed,
                $replaceString,
                $position,
                0
            );
        }

        return strrev($reversed);
    }

    private function insertEmbedId(string $code, string $embedId): string
    {
        return substr_replace(
            $code,
            "\n{% set $embedId = props|default({}) %}",
            $this->getExtendsEndPosition($code),
            0
        );
    }

    private function getExtendsEndPosition(string $code): int
    {
        $extendMatches = [];
        $matched = preg_match("/\{%\s*extends.*%}/", $code, $extendMatches, PREG_OFFSET_CAPTURE);

        if ($matched === 0) {
            return 0;
        }

        list($fullMatch, $position) = $extendMatches[0];

        return strlen($fullMatch) + $position;
    }

    private function replaceOpeningTag(
        string $code,
        string $name,
        string $attributes,
        int $startPosition,
        int $tagLength,
        bool $selfClosing
    ): string {
        $twigTag = $selfClosing ? 'include' : 'embed';
        $path = $this->componentNaming->pathFromComponentName($name);

        return substr_replace(
            $code,
            "{% $twigTag '$path' with { props: { $attributes } } %}",
            $startPosition,
            $tagLength
        );
    }

    private function getTwigParameterMap(DOMNamedNodeMap $attributes): string
    {
        $attributeObject = [];

        foreach ($attributes as $attribute) {
            if (!$attribute instanceof DOMAttr) {
                continue;
            }

            $stringValue = urldecode($attribute->value);
            $key = $attribute->name;

            $isVar = strpos($stringValue, '{{') === 0 && strpos($stringValue, '}}') === strlen($stringValue) - 2;

            if ($isVar) {
                $stringValue = trim(substr($stringValue, 2, -2));
            }

            $escape = $isVar ? '' : '\'';

            if (!$isVar) {
                $stringValue = str_replace('\'', '\\\'', $stringValue);
            }

            if (empty($stringValue)) {
                $stringValue = 'true';
            }

            if (strpos($key, '-') !== false) {
                $key = ucwords($key, '-');
                $key = str_replace('-', '', $key);
                $key = lcfirst($key);
            }

            $attributeObject[] = "$key: $escape$stringValue$escape";
        }

        return implode(', ', $attributeObject);
    }

    private function replaceBlocks(string $code, string $embedId): string
    {
        $code = preg_replace('/<block name="([a-z]+)">/', "{% block $1 %}\n{% with { props: $embedId } %}", $code);

        return str_replace('</block>', "{% endwith %}\n{% endblock %}", $code);
    }
}
