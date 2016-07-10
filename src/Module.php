<?php
namespace samsonphp\less;

use samson\core\ExternalModule;
use samsonphp\event\Event;
use samsonphp\resource\Router;

/**
 * SamsonPHP LESS compiler module.
 *
 * TODO: Nested mixin parsing to remove file name mixin hack
 * TODO: Switch to independent generic file system manager
 *
 * @author Vitaly Iegorov <egorov@samsonos.com>
 */
class Module extends ExternalModule
{
    /** LESS variable declaration pattern */
    const P_VARIABLE_DECLARATION = '/^\s*\@(?<name>[^\s:]+)\s*\:\s*(?<value>[^;]+);/m';
    /** LESS mixin declaration pattern */
    const P_MIXIN_DECLARATION = '/^\s*\.(?<name>[^\s(:]+)\s*(?<params>\([^)]+\))\s*(?<code>\{[^}]+\})/m';

    /** @var string Path to cached mixins & variables */
    public $cachedLESS;

    /** @var \lessc LESS compiler */
    protected $less;

    /** @var array Collection of LESS variables */
    protected $variables = [];

    /** @var array Collection of LESS mixins */
    protected $mixins = [];

    /** @var string Cached LESS code */
    protected $lessCode;

    /** SamsonFramework load preparation stage handler */
    public function prepare()
    {
        Event::subscribe(Router::E_RESOURCE_PRELOAD, [$this, 'analyzer']);
        Event::subscribe(Router::E_RESOURCE_COMPILE, [$this, 'compiler']);
        Event::subscribe(Router::E_FINISHED, [$this, 'finished']);

        $this->less = new \lessc;

        // Create path to LESS
        $this->cachedLESS = strlen($this->cachedLESS) ? $this->cachedLESS : $this->cache_path.'mixins.less';

        // Read cached less mixins and variables
        if (file_exists($this->cachedLESS)) {
            $this->lessCode = file_get_contents($this->cachedLESS);
        }

        return true;
    }

    /**
     * Create LESS variables and mixins cache file.
     */
    public function finished()
    {
        $this->lessCode .= implode("\n", $this->variables) . "\n"
            . implode("\n", $this->mixins) . "\n";

        // Create cache path
        $path = dirname($this->cachedLESS);
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        file_put_contents($this->cachedLESS, $this->lessCode);
    }

    /**
     * Parse LESS variable definition and remove them from code.
     *
     * @param string $content Source code for parsing LESS variables
     *
     * @return string Parsed code without LESS variables
     */
    protected function parseVariables($content)
    {
        // Find variable declaration
        if (preg_match_all(self::P_VARIABLE_DECLARATION, $content, $matches)) {
            // Gather variables in collection key => value
            for ($i = 0, $max = count($matches['name']); $i < $max; $i++) {
                // Check for duplicates
                if (!array_key_exists($matches['name'][$i], $this->variables)) {
                    // Store variable by name => definition
                    $this->variables[$matches['name'][$i]] = $matches[0][$i];
                    // Remove variable declaration from content
                    $content = str_replace($matches[0][$i], '', $content);
                }
            }
        }

        return $content;
    }

    /**
     * Parse LESS mixins definition and remove them from code.
     *
     * @param string $resource LESS resource path
     * @param string $content Source code for parsing LESS mixins
     *
     * @return string Parsed code without LESS mixins
     */
    protected function parseMixins($resource, $content)
    {
        // TODO: Hack that files with mixin should be separated and have "mixin" in their name
        if (strpos($resource, 'mixin') !== false) {
            $this->mixins[$resource] = $content;
            // As we consider whole file has only mixins - clear content
            $content = '';
        } elseif (preg_match_all(self::P_MIXIN_DECLARATION, $content, $matches)) {
            // Gather variables in collection key => value
            for ($i = 0, $max = count($matches[0]); $i < $max; $i++) {
                $this->mixins[$matches['name'][$i]] = $matches[0][$i];
                $content = str_replace($matches[0][$i], '', $content);
            }
        }

        return $content;
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
            $content = $this->parseVariables($content);
            $content = $this->parseMixins($resource, $content);

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
                    . $this->lessCode
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
