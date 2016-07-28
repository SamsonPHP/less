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
    /** LESS mixin declaration pattern */
    const P_IMPORT_DECLARATION = '/@import\s+(\'|\")(?<path>[^\'\"]+)(\'|\");/';


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
        Event::subscribe(Router::E_RESOURCE_COMPILE, [$this, 'compiler']);

        $this->less = new \lessc;

        return true;
    }

    /**
     * Replace @import in content of the file
     *
     * @param string $resource Resource full path
     * @param string $content less file content
     * @return string
     */
    protected function readImport($resource, $content)
    {
        // Rewrite imports
        $matches = [];
        if (preg_match_all(self::P_IMPORT_DECLARATION, $content, $matches)) {
            for ($i=0, $size = count($matches[0]); $i < $size; $i++) {
                $path = dirname($resource).DIRECTORY_SEPARATOR.$matches['path'][$i];
                $path = (is_file($path))?$path:$path.'.less';
                $path = realpath($path);

                $newContent = file_get_contents($path);

                // Replace path in LESS @import command
                $content = str_replace($matches[0][$i], $this->readImport($path, $newContent), $content);
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
     *
     * @throws \Exception
     */
    public function compiler($resource, &$extension, &$content)
    {
        if ($extension === 'less') {
            try {

                // Rewrite imports
                $content = $this->readImport($resource, $content);

                // Compile LESS content to CSS
                $content = $this->less->compile($content);

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
