<?php
namespace CLI\Model\CodeGenerator\Test;


use Camel\CaseTransformer;
use Camel\Format\SnakeCase;
use Camel\Format\SpinalCase;
use Camel\Format\StudlyCaps;
use CLI\Model\AbstractCodeGenerator;
use CLI\Model\CodeGenerator\AbstractFileGenerator;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Db\Metadata\Object\TableObject;

class SubModuleSubController extends AbstractFileGenerator
{
    protected $module;

    public function __construct(TableObject $table, $module = null, $parentClassNamespace = null)
    {
        parent::__construct();

        $this->table  = $table;
        $this->module = $module;
        $Transformer  = new CaseTransformer(new SnakeCase(), new StudlyCaps());
        $className    = $Transformer->transform($this->table->getName()) . 'ControllerTest';
        $this->setFilename($className);

        $ClassGenerator = new ClassGenerator($className);

        if ($module) {
            $this->setNamespace($module . '\\' . AbstractCodeGenerator::NAMESPACE_TEST . '\\' .
                                AbstractCodeGenerator::NAMESPACE_CONTROLLER);
        }
        if ($parentClassNamespace) {
            $this->setUse($this->getNamespace() . '\\' . $parentClassNamespace . '\\' . $className, 'BaseClass');
            $ClassGenerator->setExtendedClass('BaseClass');
        }

        $ClassGenerator->addMethod('testAddAction', [], MethodGenerator::FLAG_PUBLIC, $this->testAddAction());
        $ClassGenerator->addMethod('testIndexAction', [], MethodGenerator::FLAG_PUBLIC, $this->testIndexAction());
        $ClassGenerator->addMethod('testGetAction', ['id'], MethodGenerator::FLAG_PUBLIC, $this->testGetAction(), new
        DocBlockGenerator(null, null, [
            new GenericTag('depends', 'testAddAction'),
            new GenericTag('param', '$id')
        ]));
        $ClassGenerator->addMethod('testDeleteAction', ['id'], MethodGenerator::FLAG_PUBLIC, $this->testDeleteAction(),
            new
            DocBlockGenerator(null, null, [
                new GenericTag('depends', 'testAddAction'),
                new GenericTag('param', '$id')
            ]));


        $this->setBody($ClassGenerator->generate());
    }

    public function testAddAction()
    {
        $SpinalTransformer = new CaseTransformer(new SnakeCase(), new SpinalCase());
        $StudlyTransformer = new CaseTransformer(new SnakeCase(), new StudlyCaps());
        $content           =
            '$instance = $this->getTableGatewayTest()->initModelInstance();' . self::LINE_FEED .
            "\$this->dispatch('/" . strtolower($this->module) . '/' .
            $SpinalTransformer->transform($this->table->getName()) .
            "/', 'POST', \$instance->getArrayCopy());" . self::LINE_FEED .
            '' . self::LINE_FEED .
            '$this->assertResponseStatusCode(200);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertModuleName('{$this->module}');" . self::LINE_FEED .
            "\$this->assertControllerName('{$this->module}\\" . AbstractCodeGenerator::NAMESPACE_CONTROLLER .
            '\\' . $StudlyTransformer->transform($this->table->getName()) . "');" . self::LINE_FEED .
            "\$this->assertControllerClass('" . $StudlyTransformer->transform($this->table->getName()) .
            AbstractCodeGenerator::NAMESPACE_CONTROLLER . "');" . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertResponseHeaderContains('Content-type', 'application/json; charset=utf-8');" .
            self::LINE_FEED .
            '' . self::LINE_FEED .
            '$json = json_decode($this->getResponse()->getContent(), true);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            '$this->assertNotFalse($json);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            'if ($instance->getId() === null) {' . self::LINE_FEED .
            "    \$this->assertNotNull(\$json['data']['id']);" . self::LINE_FEED .
            '} else {' . self::LINE_FEED .
            "    \$this->assertEquals(\$instance->getId(), \$json['data']['id']);" . self::LINE_FEED .
            '}' . self::LINE_FEED .
            '' . self::LINE_FEED .
            "return \$json['data']['id'];";

        return $content;
    }


    public function testIndexAction()
    {
        $SpinalTransformer = new CaseTransformer(new SnakeCase(), new SpinalCase());
        $StudlyTransformer = new CaseTransformer(new SnakeCase(), new StudlyCaps());
        $content           =
            "\$this->dispatch('/" . strtolower($this->module) . '/' .
            $SpinalTransformer->transform($this->table->getName()) .
            "');" . self::LINE_FEED .
            '' . self::LINE_FEED .
            '$this->assertResponseStatusCode(200);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertModuleName('{$this->module}');" . self::LINE_FEED .
            "\$this->assertControllerName('{$this->module}\\" . AbstractCodeGenerator::NAMESPACE_CONTROLLER .
            '\\' . $StudlyTransformer->transform($this->table->getName()) . "');" . self::LINE_FEED .
            "\$this->assertControllerClass('" . $StudlyTransformer->transform($this->table->getName()) .
            AbstractCodeGenerator::NAMESPACE_CONTROLLER . "');" . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertResponseHeaderContains('Content-type', 'application/json; charset=utf-8');" .
            self::LINE_FEED .
            '' . self::LINE_FEED .
            '$json = json_decode($this->getResponse()->getContent(), true);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            '$this->assertNotFalse($json);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertArrayHasKey('data', \$json);" . self::LINE_FEED .
            "\$this->assertArrayHasKey('success', \$json);" . self::LINE_FEED .
            "\$this->assertArrayHasKey('total', \$json['data']);" . self::LINE_FEED .
            "\$this->assertArrayHasKey('start', \$json['data']);" . self::LINE_FEED .
            "\$this->assertArrayHasKey('limit', \$json['data']);" . self::LINE_FEED .
            "\$this->assertArrayHasKey('list', \$json['data']);" . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertTrue(is_numeric(\$json['data']['total']));" . self::LINE_FEED .
            "\$this->assertTrue(is_numeric(\$json['data']['start']));" . self::LINE_FEED .
            "\$this->assertTrue(is_numeric(\$json['data']['limit']));" . self::LINE_FEED .
            "\$this->assertTrue(\$json['success']);";

        return $content;
    }

    public function testGetAction()
    {
        $SpinalTransformer = new CaseTransformer(new SnakeCase(), new SpinalCase());
        $StudlyTransformer = new CaseTransformer(new SnakeCase(), new StudlyCaps());
        $content           =
            "\$this->dispatch('/" . strtolower($this->module) . '/' .
            $SpinalTransformer->transform($this->table->getName()) .
            "/' . \$id);" . self::LINE_FEED .
            '' . self::LINE_FEED .
            '$this->assertResponseStatusCode(200);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertModuleName('{$this->module}');" . self::LINE_FEED .
            "\$this->assertControllerName('{$this->module}\\" . AbstractCodeGenerator::NAMESPACE_CONTROLLER .
            '\\' . $StudlyTransformer->transform($this->table->getName()) . "');" . self::LINE_FEED .
            "\$this->assertControllerClass('" . $StudlyTransformer->transform($this->table->getName()) .
            AbstractCodeGenerator::NAMESPACE_CONTROLLER . "');" . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertResponseHeaderContains('Content-type', 'application/json; charset=utf-8');" .
            self::LINE_FEED .
            '' . self::LINE_FEED .
            '$json = json_decode($this->getResponse()->getContent(), true);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            '$this->assertNotFalse($json);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertArrayHasKey('data', \$json);" . self::LINE_FEED .
            "\$this->assertArrayHasKey('success', \$json);" . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertTrue(\$json['success']);";

        return $content;
    }

    public function testDeleteAction()
    {
        $SpinalTransformer = new CaseTransformer(new SnakeCase(), new SpinalCase());
        $StudlyTransformer = new CaseTransformer(new SnakeCase(), new StudlyCaps());
        $content           =
            "\$this->dispatch('/" . strtolower($this->module) . '/' .
            $SpinalTransformer->transform($this->table->getName()) .
            "/' . \$id, 'DELETE');" . self::LINE_FEED .
            '' . self::LINE_FEED .
            '$this->assertResponseStatusCode(200);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertModuleName('{$this->module}');" . self::LINE_FEED .
            "\$this->assertControllerName('{$this->module}\\" . AbstractCodeGenerator::NAMESPACE_CONTROLLER .
            '\\' . $StudlyTransformer->transform($this->table->getName()) . "');" . self::LINE_FEED .
            "\$this->assertControllerClass('" . $StudlyTransformer->transform($this->table->getName()) .
            AbstractCodeGenerator::NAMESPACE_CONTROLLER . "');" . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertResponseHeaderContains('Content-type', 'application/json; charset=utf-8');" .
            self::LINE_FEED .
            '' . self::LINE_FEED .
            '$json = json_decode($this->getResponse()->getContent(), true);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            '$this->assertNotFalse($json);' . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertArrayHasKey('data', \$json);" . self::LINE_FEED .
            "\$this->assertArrayHasKey('success', \$json);" . self::LINE_FEED .
            '' . self::LINE_FEED .
            "\$this->assertTrue(\$json['success']);";

        return $content;
    }
}