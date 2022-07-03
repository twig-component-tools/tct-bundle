<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

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

    /**
     * @throws \Exception
     */
    public function processSourceContext(Source $source): Source
    {
        /**
         * @var ?\TwigComponentTools\TCTBundle\Preprocessor\Component $component
         */
        $component = null;
        $code = $source->getCode();
        $hasComponents = false;

        while ($this->getNextComponent($code, $component)) {
            $hasComponents = true;

            $code = substr_replace(
                $code,
                $component->render(),
                $component->originalStartPos,
                $component->originalLength
            );
        }

        if ($hasComponents) {
//            VarDumper::dump($code);

            return new Source($code, $source->getName(), $source->getPath());
        }

        return $source;
    }

    /**
     * @param string     $code      Code to search components in
     * @param ?Component $component The component information will be saved in this array
     *
     * @return bool True if it found a component
     *
     * @throws \Exception
     */
    public function getNextComponent(string $code, ?Component &$component): bool
    {
        $matches = [];

        $found = 1 === preg_match(
                $this->componentRegex,
                $code,
                $matches,
                PREG_OFFSET_CAPTURE
            );

        if (!$found) {
            return false;
        }

        list($fullMatch, $nameMatch) = $matches;
        list($openingTag, $start) = $fullMatch;
        list($name) = $nameMatch;

        $path = $this->componentNaming->pathFromComponentName($name);

        $component = new Component(
            $openingTag,
            $name,
            $path,
            $start,
            $code
        );

        return true;
    }
}
