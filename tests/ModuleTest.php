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

    public function testAnalyzer()
    {
        $results = $this->module->analyzer(__DIR__ . '/variables.less', 'less');

        $this->assertArrayHasKey('var', $results[0]);

        $results = $this->module->analyzer(__DIR__ . '/mixins.less', 'less');

        $this->assertArrayHasKey('mixin', $results[1]);

        $results = $this->module->analyzer(__DIR__ . '/variables.less', 'css');

        $this->assertEquals(null, $results);
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

        $content = '';
        $extension = 'less';
        $this->module->analyzer(__DIR__ . '/variables.less', 'less');
        $this->module->analyzer(__DIR__ . '/mixins.less', 'less');
        $this->module->compiler(__DIR__ . '/test.less', $extension, $content);

        $this->assertEquals($equals, $content);
    }

    public function testException()
    {
        $this->setExpectedException('\Exception');

        $this->module->prepare();

        $content = '';
        $extension = 'less';
        $this->module->compiler(__DIR__ . '/wrong.less', $extension, $content);
    }
}
