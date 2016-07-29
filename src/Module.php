<?php
namespace samsonphp\less;

use samson\core\ExternalModule;
use samsonphp\event\Event;
use samsonphp\resource\exception\ResourceNotFound;
use samsonphp\resource\Router;

/**
 * SamsonPHP LESS compiler module.
 * TODO: Change to file operations to FileManagerInterface
 *
 * @author Vitaly Iegorov <egorov@samsonos.com>
 */
class Module extends ExternalModule
{
    /** LESS mixin declaration pattern */
    const P_IMPORT_DECLARATION = '/@import\s+(\'|\")(?<path>[^\'\"]+)(\'|\");/';

    /** LESS resource importing dependencies file name */
    /** LESS resource importing dependencies file name */
    const DEPENDENCY_CACHE = 'dependencies';

    /** @var array LESS resources dependencies */
    public $dependencies = [];

    /** @var string Path to LESS resources dependencies cache file */
    protected $dependencyCache;

    /** @var \lessc LESS compiler */
    protected $less;

    /** SamsonFramework load preparation stage handler */
    public function prepare(array $params = [])
    {
        $moduleCachePath = array_key_exists('cachePath', $params) ? $params['cachePath'] : $this->cache_path;
        $this->dependencyCache = $moduleCachePath.self::DEPENDENCY_CACHE;

        // Read previous cache file
        if (file_exists($this->dependencyCache)) {
            $this->dependencies = unserialize(file_get_contents($this->dependencyCache));
        }

        $this->less = new \lessc;

        Event::subscribe(Router::E_RESOURCE_COMPILE, [$this, 'compiler']);

        return parent::prepare();
    }

    /**
     * Cache LESS resources importing dependency trees.
     */
    public function cacheDependencies()
    {
        // Create folder
        if (!file_exists(dirname($this->dependencyCache))) {
            @mkdir(dirname($this->dependencyCache), 0777, true);
        }

        file_put_contents($this->dependencyCache, serialize($this->dependencies));
    }

    /**
     * Recursively replace @import in content of the LESS file
     *
     * @param string $resource Resource full path
     * @param string $content  less file content
     *
     * @return string Content of LESS file with included @imported resources
     * @throws ResourceNotFound If importing resource could not be found
     */
    protected function readImport($resource, $content)
    {
        // Rewrite imports
        $matches = [];
        if (preg_match_all(self::P_IMPORT_DECLARATION, $content, $matches)) {
            for ($i=0, $size = count($matches[0]); $i < $size; $i++) {
                // Build absolute path to imported resource
                $path = dirname($resource).DIRECTORY_SEPARATOR.$matches['path'][$i];

                // Append .less extension according to standard
                if (false === ($path = realpath(is_file($path)?$path:$path.'.less'))) {
                    throw new ResourceNotFound('Cannot import file: '.$matches['path'][$i]);
                }

                // Add parent to child dependency
                $this->dependencies[$path][$resource] = [];

                // Replace path in LESS @import command with recursive call to this function
                $content = str_replace($matches[0][$i], $this->readImport($path, file_get_contents($path)), $content);
            }
        }

        return $content;
    }

    /**
     * LESS resource compiler.
     *
     * @param string $resource  Resource full path
     * @param string $extension Resource extension
     * @param string $content   Compiled output resource content
     * @param array $dependencies Collection of compiled resource dependent modules
     *
     * @throws \Exception
     */
    public function compiler($resource, &$extension, &$content, &$dependencies)
    {
        if ($extension === 'less') {
            try {
                // Rewrite imports
                $content = $this->readImport($resource, $content);

                // Compile LESS content to CSS
                $content = $this->less->compile($content);

                // Switch extension
                $extension = 'css';

                // Return dependencies for this resource
                $dependencies = array_key_exists($resource, $this->dependencies)
                    ? $this->dependencies[$resource]
                    : [];
            } catch (\Exception $e) {
                //$errorFile = 'cache/error_resourcer'.microtime(true).'.less';
                //file_put_contents($errorFile, $output);
                throw new \Exception('Failed compiling LESS in "' . $resource . '":' . "\n" . $e->getMessage());
            }
        }
    }
}
