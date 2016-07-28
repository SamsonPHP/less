<?php
/**
 * Created by Vitaly Iegorov <egorov@samsonos.com>.
 * on 20.02.16 at 14:39
 */
namespace samsonphp\less\tests;

use samson\core\Core;
use samsonframework\resource\ResourceMap;
use samsonphp\less\Module;
use samsonphp\resource\exception\ResourceNotFound;

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
        $this->module->compiler(__DIR__ . '/test.less', $extension, $content);

        $this->assertEquals($equals, $content);
    }

    public function testException()
    {
        $this->setExpectedException(\Exception::class);

        $this->module->prepare();

        $content = file_get_contents(__DIR__ . '/wrong.less');;
        $extension = 'less';
        $this->module->compiler(__DIR__ . '/wrong.less', $extension, $content);
    }

    public function testImportingException()
    {
        $this->setExpectedException(\Exception::class);

        $this->module->prepare();

        $content = file_get_contents(__DIR__ . '/wrongImport.less');;
        $extension = 'less';
        $this->module->compiler(__DIR__ . '/wrongImport.less', $extension, $content);
    }
}
