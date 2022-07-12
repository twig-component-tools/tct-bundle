<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

use DOMDocument;
use DOMElement;
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
        $code = $this->getSanitizedCode($source);
        $components = $this->getComponentList($code);

        if (empty($components)) {
            return $source;
        }

        $embedId = $this->getEmbedId($source);
        $document = $this->createDOMDocument($code);

        foreach ($components as $componentName) {
            $this->processAllComponents($componentName, $document, $embedId);
        }

        return new Source(
            $this->getTranspiledCode($document, $embedId),
            $source->getName(),
            $source->getPath()
        );
    }

    /** @noinspection PhpUnnecessaryLocalVariableInspection */
    private function getTranspiledCode(DOMDocument $document, string $embedId): string
    {
        $code = $this->saveDocument($document);
        $code = $this->fixUrlEncodings($code);
        $code = $this->insertEmbedId($code, $embedId);

        return $code;
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

    private function processAllComponents(string $componentName, DOMDocument $document, string $embedId): void
    {
        $selector = strtolower($componentName);
        $templatePath = $this->componentNaming->pathFromComponentName($componentName);
        $componentNodeList = $document->getElementsByTagName($selector);
        $numberOfComponents = $componentNodeList->length;

        for ($index = 0; $index < $numberOfComponents; $index++) {
            $componentNode = $componentNodeList->item(0);

            if(!$componentNode instanceof DOMElement){
                continue;
            }

            $this->processComponent($componentNode, $componentName, $templatePath, $embedId);
        }
    }

    private function processComponent(DOMElement $element, string $name, string $path, string $embedId): void
    {
        $component = new Component($name, $element, $path, $embedId);
        $transpiledNodes = $component->getTranspiledNodes();
        $fragment = $element->ownerDocument->createDocumentFragment();

        foreach ($transpiledNodes as $transpiledNode) {
            $fragment->appendChild($transpiledNode);
        }

        $element->parentNode->replaceChild($fragment, $element);
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

    private function getEmbedId(Source $source): string
    {
        $fileName = pathinfo($source->getPath(), PATHINFO_FILENAME);

        return uniqid($fileName);
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

    private function fixUrlEncodings(string $code): string
    {
        return preg_replace_callback(
            "/(src|href)=\"(.*)\"/",
            function ($match) {
                $attribute = $match[1];
                $value = urldecode($match[2]);

                return "$attribute=\"$value\"";
            },
            $code
        );
    }

    private function saveDocument(DOMDocument $document): string
    {
        $root = $document->getElementsByTagName('tct-root')[0];

        $transpiledCode = '';
        foreach ($root->childNodes as $node) {
            $transpiledCode .= $document->saveHTML($node);
        }

        return $transpiledCode;
    }

    private function getComponentList(string $code): ?array
    {
        $matches = [];

        preg_match_all(
            $this->componentRegex,
            $code,
            $matches,
            PREG_PATTERN_ORDER
        );

        return array_unique($matches[1 /* Name Matches */]);
    }
}
