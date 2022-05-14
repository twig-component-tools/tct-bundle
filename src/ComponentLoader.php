<?php

namespace TwigComponentTools\Loader;

use SimpleXMLElement;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;
use Twig\Source;

class ComponentLoader implements LoaderInterface
{
    private const TYPES = [
        'q' => 'Quark',
        'a' => 'Atom',
        'm' => 'Molecule',
        'o' => 'Organism',
    ];

    private FilesystemLoader $filesystemLoader;

    private array $loadedComponents = [];

    public function __construct(FilesystemLoader $filesystemLoader)
    {
        $this->filesystemLoader = $filesystemLoader;
    }

    public function getSourceContext(string $name): Source
    {
        $source = $this->filesystemLoader->getSourceContext($name);

        $component     = [];
        $code          = $source->getCode();
        $lastPosition  = 0;
        $hasComponents = false;

        while ($this->getNextComponent($code, $lastPosition, $component)) {
            [
                'name'          => $name,
                'startPosition' => $startPosition,
                'tag'           => $openingTag,
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

        /**
         * @see https://regex101.com/r/3ujquL/2
         */
        $found = preg_match(
            '/<([QAMO][A-Z][a-zA-Z]+\b)\s*([a-zA-Z]+=.+\")?\s*\/?>/msU',
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

        $defaultBlock = false === strpos($inner, '<block');
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
        $path    = $this->getTemplatePath($name);

        [
            'params' => $params,
            'only'   => $only,
        ] = $this->createParameterMap($attributes);

        $onlyTag = $only ? 'only' : '';

        return substr_replace(
            $code,
            "{% $twigTag '$path' with {{$params}} {$onlyTag} %}",
            $startPosition,
            $tagLength
        );
    }

    private function getTemplatePath(string $namePascal): string
    {
        return implode(DIRECTORY_SEPARATOR, [
            '@components',
            self::TYPES[strtolower($namePascal[0])],
            $namePascal,
            "{$namePascal}.twig",
        ]);
    }

    private function createParameterMap(string $attributesString): array
    {
        if (empty($attributesString)) {
            return [
                'params' => '',
                'only'   => true,
            ];
        }

        $attributesString = htmlspecialchars($attributesString, ENT_NOQUOTES);

        $attributeObject = [];
        try {
            $attributes = new SimpleXMLElement("<element $attributesString />");
        } catch (\Exception $exception) {
            return [
                'params' => '',
                'only'   => true,
            ];
        }

        $only = true;
        /**
         * @var SimpleXMLElement $value
         */
        foreach ($attributes->attributes() as $key => $value) {
            if ($key === "only" && (string)$value === "false") {
                $only = false;
                continue;
            }

            $isVar = strpos($value, '{{') === 0 && strpos($value, '}}') === strlen($value) - 2;

            if ($isVar) {
                $value = trim(substr($value, 2, -2));
            }

            $escape = $isVar ? '' : '\'';

            $attributeObject[] = "$key: $escape$value$escape";
        }

        return [
            'params' => implode(', ', $attributeObject),
            'only'   => $only,
        ];
    }

    private function replaceBlocks(string $code): string
    {
        $code = preg_replace('/<block #([a-z]+)>/', '{% block $1 %}', $code);

        return str_replace('</block>', '{% endblock %}', $code);
    }

    public function getCacheKey(string $name): string
    {
        if (strpos($name, '@components') === 0) {
            $parts         = explode('/', $name);
            $componentName = str_replace('.twig', '', $parts[count($parts) - 1]);

            if (!in_array($componentName, $this->loadedComponents)) {
                $this->loadedComponents[] = $componentName;
            }
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
}
