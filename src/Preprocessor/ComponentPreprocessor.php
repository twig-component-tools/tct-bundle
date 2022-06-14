<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

use Exception;
use SimpleXMLElement;
use Twig\Source;
use TwigComponentTools\TCTBundle\Naming\ComponentNamingInterface;

class ComponentPreprocessor implements PreprocessorInterface
{
    private ComponentNamingInterface $componentNaming;

    private string $componentRegex;

    public function __construct(ComponentNamingInterface $componentNaming)
    {
        $this->componentNaming = $componentNaming;
        $this->componentRegex  = $this->componentNaming->getComponentRegex();
    }

    public function processSourceContext(Source $source): Source
    {
        $component     = [];
        $code          = $source->getCode();
        $lastPosition  = 0;
        $hasComponents = false;

        while ($this->getNextComponent($code, $lastPosition, $component)) {
            [
                'name'          => $name,
                'startPosition' => $startPosition,
                // 'tag'           => $openingTag,
                'endPosition'   => $endOfOpeningTag,
                'attributes'    => $attributes,
                'selfClosing'   => $selfClosing,
                'length'        => $openingTagLength,
            ] = $component;

            if (!$selfClosing) {
                $closingTag        = "</$name>";
                $startOfClosingTag = strpos($code, $closingTag, $endOfOpeningTag);

                $code = $this->replaceClosingTag($code, $startOfClosingTag, $closingTag);
                $code = $this->replaceDefaultBlock($code, $name, $endOfOpeningTag, $startOfClosingTag);
            }

            $code = $this->replaceOpeningTag(
                $code,
                $name,
                $attributes,
                $startPosition,
                $openingTagLength,
                $selfClosing
            );

            $lastPosition  = $startPosition + 1;
            $hasComponents = true;
        }

        if ($hasComponents) {
            $code = $this->replaceBlocks($code);

            return new Source($code, $source->getName(), $source->getPath());
        }

        return $source;
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

        $tag    = $matches[0][0];
        $length = strlen($tag);
        $start  = $matches[0][1];

        $component = [
            'tag'           => $tag,
            'name'          => $matches[1][0],
            'startPosition' => $start,
            'endPosition'   => $start + $length,
            'attributes'    => $matches[2][0] ?? '',
            'selfClosing'   => '/' === $tag[$length - 2],
            'length'        => $length,
        ];

        return true;
    }

    private function replaceClosingTag(string $code, int $closingPosition, string $closingTag): string
    {
        return substr_replace($code, '{% endembed %}', $closingPosition, strlen($closingTag));
    }

    private function replaceDefaultBlock(
        string $code,
        string $name,
        int $endOfOpeningTag,
        int $startOfClosingTag
    ): string {
        $inner = substr(
            $code,
            $endOfOpeningTag,
            $startOfClosingTag - $endOfOpeningTag
        );

        $defaultBlock = !str_contains($inner, '<block');
        if ($defaultBlock) {
            $code = substr_replace(
                $code,
                "\n{% block ${name}_default %}$inner{% endblock %}\n",
                $endOfOpeningTag,
                strlen($inner)
            );
        }

        return $code;
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
        $path    = $this->componentNaming->pathFromComponentName($name);
        $params  = $this->getTwigParameterMap($attributes);

        return substr_replace(
            $code,
            "{% $twigTag '$path' with { props: { $params } } %}",
            $startPosition,
            $tagLength
        );
    }

    private function getTwigParameterMap(string $attributesString): string
    {
        $attributeObject  = [];
        $attributesString = htmlspecialchars($attributesString, ENT_NOQUOTES);

        try {
            $element    = new SimpleXMLElement("<element $attributesString />");
            $attributes = $element->attributes();
        } catch (Exception $exception) {
            $attributes = [];
        }

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

    private function replaceBlocks(string $code): string
    {
        $code = preg_replace('/<block #([a-z]+)>/', '{% block $1 %}', $code);

        return str_replace('</block>', '{% endblock %}', $code);
    }
}
