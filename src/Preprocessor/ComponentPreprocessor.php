<?php

namespace TwigComponentTools\TCTBundle\Preprocessor;

use DOMDocument;
use DOMElement;
use Symfony\Component\VarDumper\VarDumper;
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
        $code = $source->getCode();
        $components = $this->getComponentList($code);

        if (empty($components)) {
            return $source;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $parsingCode = mb_convert_encoding("<tct-root>$code</tct-root>", 'HTML-ENTITIES', 'UTF-8');
        $dom->loadHTML($parsingCode, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOXMLDECL);
        libxml_use_internal_errors(false);

        foreach ($components as $componentName) {
            $templatePath = $this->componentNaming->pathFromComponentName($componentName);
            $selector = strtolower($componentName);
            $componentNodeList = $dom->getElementsByTagName($selector);
            $numberOfComponents = $componentNodeList->length;

            for ($index = 0; $index < $numberOfComponents; $index++) {
                /**
                 * @var DOMElement $componentNode
                 */
                $componentNode = $componentNodeList->item(
                    0
                ); // $componentNodeList is "live" -> The first item will always be the next non-altered item.

                $component = new Component($componentName, $componentNode, $templatePath);
                $transpiledNodes = $component->getTranspiledNodes();
                $fragment = $dom->createDocumentFragment();

                foreach ($transpiledNodes as $transpiledNode) {
                    $fragment->appendChild($transpiledNode);
                }

                $componentNode->parentNode->replaceChild($fragment, $componentNode);
            }
        }

        $root = $dom->getElementsByTagName('tct-root')[0];

        $transpiledCode = '';
        foreach ($root->childNodes as $node) {
            $transpiledCode .= $dom->saveHTML($node);
        }

        $transpiledCode = preg_replace_callback(
            "/(src|href)=\"(.*)\"/",
            function ($match) {
                $attribute = $match[1];
                $value = urldecode($match[2]);
                return "$attribute=\"$value\"";
            },
            $transpiledCode
        );

        return new Source($transpiledCode, $source->getName(), $source->getPath());
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
