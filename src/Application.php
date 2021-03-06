<?php
declare(strict_types = 1);

namespace ApigilityRamlValidator;

use League\CLImate\CLImate;
use Raml\Parser as RamlParser;

class Application
{
    private $spec;
    private $project;
    private $moduleHint;

    private $messages = [];

    public function __construct(string $spec, string $project, $moduleHint = false)
    {
        $this->spec       = $spec;
        $this->project    = $project;
        $this->moduleHint = $moduleHint;
    }

    public function run(CLImate $climate)
    {
        $ramlParser = new RamlParser();

        if ($climate->arguments->get('debug')) {
            $parsedSpec = $ramlParser->parse($this->spec);
        } else {
            $parsedSpec = @$ramlParser->parse($this->spec);
        }

        // Run checks
        $module = $this->moduleHint ? $this->moduleHint : $parsedSpec->getTitle();
        $this->checkAPIModuleExists($module);
        $this->checkCurrentVersionExists($module, $parsedSpec->getVersion());
        $this->checkEndpoints($module, $parsedSpec->getResources());

        if (count($this->messages)) {
            $climate->red("Apigility implementation doesn't match the RAML specification!");

            foreach ($this->messages as $message) {
                $climate->yellow($message);
            }
        } else {
            $climate->green('Congratulations! Your Apigility implementation matches the RAML specification!');
        }
    }

    public function checkApiModuleExists(string $module)
    {
        $modulePath = $this->project . '/module/' . $module;

        if (!is_dir($modulePath)) {
            $this->messages[] = "The $module module does not exist";
        }
    }

    public function checkCurrentVersionExists(string $module, $version)
    {
        $versionPath = $this->project . '/module/' . $module . '/src/' . ucfirst($version);

        if (!is_dir($versionPath)) {
            $this->messages[] = "No service with version $version found for the $module module";
        }
    }

    public function checkEndpoints(string $module, array $resources)
    {
        $moduleConfig = include $this->project . '/module/' . $module . '/config/module.config.php';

        // If documentation has been generated check if, if not skip and notify
        $documentationConfigPath = $this->project . '/module/' . $module . '/config/documentation.config.php';
        if (is_file($documentationConfigPath)) {
            $documentationConfig = include $documentationConfigPath;
        } else {
            $this->messages[] = "Missing documentation for the module $module";
        }

        $routes = $moduleConfig['router']['routes'];

        foreach ($resources as $resource) {

            $found = false;
            // Check if the resource exists
            foreach ($routes as $route) {
                if (preg_match('/^' . preg_quote($resource->getUri(), '/') . '/', $route['options']['route'])) {
                    $found = true;

                    // Check all methods defined for the endpoint are implemented
                    $this->checkEndpointsMethods($route, $moduleConfig, $resource, true);
                    // If available check documentation for the endpoint
                    if (isset($documentationConfig)) {
                        $this->checkDocumentation($route, $documentationConfig, $resource, true);
                    }
                }

                //@TODO nested resources
            }

            if (!$found) {
                $this->messages[] = 'Endpoint ' . $resource->getDisplayName() . ' not found';
            }
        }
    }

    public function checkEndpointsMethods(array $route, array $moduleConfig, $resource, $topResource = true)
    {
        // Extract controller from the route
        $controller = $route['options']['defaults']['controller'];

        // We assume the top resource is a collection method and
        // sub resources will be entity.
        $httpMethodsKey = $topResource
            ? 'collection_http_methods'
            : 'entity_http_methods';

        // Check specification methods match the implementation
        $definedMethods = array_values(
            $moduleConfig['zf-rest'][$controller][$httpMethodsKey]
        );

        $specifiedMethods = array_keys($resource->getMethods());

        if (array_diff($definedMethods, $specifiedMethods)) {
            $this->messages[] = 'Missing methods for ' . $resource->getDisplayName(). ' resource!';
            $this->messages[] = '  Expected ' . json_encode($specifiedMethods);
            $this->messages[] = '  Implemented ' . json_encode($definedMethods);
        }
    }

    public function checkDocumentation(array $route, array $documentationConfig, $resource, $topResource = true)
    {
        // Extract controller from the route
        $controller = $route['options']['defaults']['controller'];

        // We assume the top resource is a collection method and
        // sub resources will be entity.
        $methodsKey = $topResource ? 'collection' : 'entity';

        foreach ($resource->getMethods() as $definedMethod) {
            $methodType = $definedMethod->getType();

            $resourceMethodIdentifier = $resource->getDisplayName() . ' ' . $methodType . ' (' . $methodsKey . ')';

            if (isset($documentationConfig[$controller][$methodsKey][$methodType]['description'])) {
                if (
                    $documentationConfig[$controller][$methodsKey][$methodType]['description'] !==
                    $definedMethod->getDescription()
                ) {
                    $this->messages[] = 'Documentation does not match for ' . $resourceMethodIdentifier;
                    $this->messages[] = '  Expected: ' . $definedMethod->getDescription();
                    $this->messages[] = '     Found: ' . $documentationConfig[$controller][$methodsKey][$methodType]['description'];
                }
            } else {
                $this->messages[] = 'Missing documentation for ' . $resourceMethodIdentifier;
            }
        }
    }
}
