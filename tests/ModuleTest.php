<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 20.02.16 at 14:39
 */
namespace samsonphp\less\tests;

use samson\core\Core;
use samsonframework\resource\ResourceMap;
use samsonphp\less\Module;

// Include framework constants
require('vendor/samsonos/php_core/src/constants.php');
require('vendor/samsonos/php_core/src/Utils2.php');

class ModuleTest extends \PHPUnit_Framework_TestCase
{
    /** @var Module */
    protected $module;

    public function setUp()
    {
        $map = new ResourceMap(__DIR__);
        $core = new Core($map);
        $this->module = new Module(__DIR__, $map, $core);
        $this->module->prepare();
    }

    public function testPrepare()
    {
        $this->module->cachedLESS = __DIR__.'/cache/cache.less';
        $this->module->prepare();
    }

    public function testFinished()
    {
        $this->module->cachedLESS = __DIR__.'/cache/cache.less';
        unlink($this->module->cachedLESS);
        rmdir(dirname($this->module->cachedLESS));
        $this->module->finished();
    }

    public function testAnalyzer()
    {
        $variables = file_get_contents(__DIR__ . '/variables.less');
        $results = $this->module->analyzer(__DIR__ . '/variables.less', 'less', $variables);

        $this->assertArrayHasKey('var', $results[0]);

        $mixins = file_get_contents(__DIR__ . '/mixins.less');
        $results = $this->module->analyzer(__DIR__ . '/mixins.less', 'less', $mixins);

        $this->assertArrayHasKey('var', $results[0]);

        $results = $this->module->analyzer(__DIR__ . '/variables.less', 'css', $variables);

        $this->assertEquals([], $results);
    }

    public function testGenerator()
    {
        $equals = <<<'CSS'
.parentClass {
  color: green;
}
.parentClass.blue {
  color: blue;
}
.parentClass .nestedClass {
  border: 1px solid;
  color: white;
}

CSS;

        $content = file_get_contents(__DIR__ . '/test.less');
        $extension = 'less';
        $variables = file_get_contents(__DIR__ . '/variables.less');
        $mixins = file_get_contents(__DIR__ . '/mixins.less');

        $this->module->analyzer(__DIR__ . '/variables.less', 'less', $variables);
        $this->module->analyzer(__DIR__ . '/mixins.less', 'less', $mixins);
        $this->module->compiler(__DIR__ . '/test.less', $extension, $content);

        $this->assertEquals($equals, $content);
    }

    public function testException()
    {
        $this->setExpectedException('\Exception');

        $this->module->prepare();

        $content = file_get_contents(__DIR__ . '/wrong.less');;
        $extension = 'less';
        $this->module->compiler(__DIR__ . '/wrong.less', $extension, $content);
    }
}
