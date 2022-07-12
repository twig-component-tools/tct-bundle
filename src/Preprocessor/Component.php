<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

use DOMAttr;
use DOMElement;
use DOMText;

class Component
{
    public bool $isSelfClosing;
    public string $name;
    public DOMElement $element;
    public string $filePath;


    /**
     * @throws \Exception
     */
    public function __construct(
        string $name,
        DOMElement $element,
        string $filePath
    ) {
        $this->filePath = $filePath;
        $this->element = $element;
        $this->name = $name;
        $this->isSelfClosing = !$this->element->hasChildNodes();
    }

    private function createTextNode(string $data): DOMText
    {
        return $this->element->ownerDocument->createTextNode($data);
    }

    private function replaceBlocks(): void
    {
        $blocks = $this->element->getElementsByTagName('block');
        $numberOfBlocks = $blocks->length;

        if (0 === $numberOfBlocks) {
            $start = $this->createTextNode("{% block default %}\n{% with embedContext %}");
            $end = $this->createTextNode("{% endwith %}\n{% endblock default %}");
            /**
             * @var \DOMNodeList $children
             */
            $children = $this->element->childNodes;

            $this->element->insertBefore($start, $children->item(0)); // start before first node
            $this->element->appendChild($end); // end after all children

            return;
        }

        for ($index = 0; $index < $numberOfBlocks; $index++) {
            /**
             * @var DOMElement $block
             */
            $block = $blocks->item(0); // first item is the next un-altered item

            $name = $block->attributes['name']->value;
            $start = $this->createTextNode("{% block $name %}\n{% with embedContext %}");
            $end = $this->createTextNode("{% endwith %}\n{% endblock $name %}");

            $end = $this->element->insertBefore($end, $block); // end before block
            $start = $this->element->insertBefore($start, $end); // start before end

            /**
             * @var \DOMNodeList $children
             */
            $children = $this->element->childNodes;
            for($index = $children->length - 1; $index >= 0; $index--) {
                $this->element->insertBefore($children->item($index), $end);
            }

            $block->remove();
        }
    }

    public function getTranspiledNodes(): array
    {
        if ($this->isSelfClosing) {
            $includeNode = $this->transpileInclude();

            return [$includeNode];
        }

        return $this->transpileEmbed();
    }

    private function getTwigParameterMap(): string
    {
        $attributeObject = [];

        foreach ($this->element->attributes as $attribute) {
            if (!$attribute instanceof DOMAttr) {
                continue;
            }

            $stringValue = urldecode($attribute->value);
            $key = $attribute->name;

            $isVar = strpos($stringValue, '{{') === 0 && strpos($stringValue, '}}') === strlen($stringValue) - 1;

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

            if (strpos($key, '-') !== FALSE) {
                $key = ucwords($key, '-');
                $key = str_replace('-', '', $key);
                $key = lcfirst($key);
            }

            $attributeObject[] = "$key: $escape$stringValue$escape";
        }

        return implode(', ', $attributeObject);
    }

    private function transpileInclude(): DOMText
    {
        $parameterMap = $this->getTwigParameterMap();

        return $this->createTextNode("{% include '$this->filePath' with { props: { $parameterMap } } %}");
    }

    private function transpileEmbed(): array
    {
        $this->replaceBlocks();
        $parameterMap = $this->getTwigParameterMap();

        $start = $this->createTextNode(
            "{% embed '$this->filePath' with { props: { $parameterMap }, embedContext: _context } %}"
        );
        $end = $this->createTextNode("{% endembed %}");


        /**
         * @var \DOMNodeList $children
         */
        $children = $this->element->childNodes;

        $this->element->insertBefore($start, $children->item(0));
        $this->element->appendChild($end); // end after all children

        return iterator_to_array($children);
    }
}
