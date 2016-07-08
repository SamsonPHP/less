<?php
namespace samsonphp\less;

use samson\core\ExternalModule;
use samsonphp\event\Event;
use samsonphp\resource\Router;

/**
 * SamsonPHP LESS compiler module.
 *
 * @author Vitaly Iegorov <egorov@samsonos.com>
 * @author Nikita Kotenko <kotenko@samsonos.com>
 */
class Module extends ExternalModule
{
    /** LESS variable declaration pattern */
    const P_VARIABLE_DECLARATION = '/^\s*\@(?<name>[^\s:]+)\s*\:\s*(?<value>[^;]+);/m';
    /** LESS mixin declaration pattern */
    const P_MIXIN_DECLARATION = '/^\s*\.(?<name>[^\s(:]+)\s*(?<params>\([^)]+\))\s*(?<code>\{.+\})/m';

    /** @var \lessc LESS compiler */
    protected $less;

    /** @var array Collection of LESS variables */
    protected $variables = [];

    /** @var array Collection of LESS mixins */
    protected $mixins = [];

    /** SamsonFramework load preparation stage handler */
    public function prepare()
    {
        Event::subscribe(Router::E_RESOURCE_PRELOAD, [$this, 'analyzer']);
        Event::subscribe(Router::E_RESOURCE_COMPILE, [$this, 'compiler']);

        $this->less = new \lessc;

        return true;
    }

    /**
     * LESS resource analyzer.
     *
     * @param string $resource  Resource full path
     * @param string $extension Resource extension
     * @param string $content LESS code
     *
     * @return array Variables and mixins collection
     */
    public function analyzer($resource, $extension, &$content)
    {
        if ($extension === 'less') {
            // Find variable declaration
            if (preg_match_all(self::P_VARIABLE_DECLARATION, $content, $matches)) {
                // Gather variables in collection key => value
                for ($i = 0, $max = count($matches['name']); $i < $max; $i++) {
                    if (!array_key_exists($matches['name'][$i], $this->variables)) {
                        $this->variables[$matches['name'][$i]] = $matches[0][$i];
                        $content = str_replace($matches[0][$i], '', $content);
                    }
                }
            }

            // TODO: Hack that files with mixin should be separated and have "mixin" in their name
            if (strpos($resource, 'mixin') !== false) {
                $this->mixins[$resource] = $content;
                $content = '';
            } elseif (preg_match_all(self::P_MIXIN_DECLARATION, $content, $matches)) {
                // Gather variables in collection key => value
                for ($i = 0, $max = count($matches[0]); $i < $max; $i++) {
                    $this->mixins[$matches['name'][$i]] = $matches[0][$i];
                    $content = str_replace($matches[0][$i], '', $content);
                }
            }

            return [$this->variables, $this->mixins];
        }

        return [];
    }

    /**
     * LESS resource compiler.
     *
     * @param string $resource  Resource full path
     * @param string $extension Resource extension
     * @param string $content   Compiled output resource content
     *
     * @throws \Exception
     */
    public function compiler($resource, &$extension, &$content)
    {
        if ($extension === 'less') {
            try {
                // Read updated CSS resource file and compile it with mixins
                $content = $this->less->compile(
                    implode("\n", $this->variables) . "\n"
                    . implode("\n", $this->mixins) . "\n"
                    . $content
                );

                // Switch extension
                $extension = 'css';
            } catch (\Exception $e) {
                //$errorFile = 'cache/error_resourcer'.microtime(true).'.less';
                //file_put_contents($errorFile, $output);
                throw new \Exception('Failed compiling LESS in "' . $resource . '":' . "\n" . $e->getMessage());
            }
        }
    }
}
