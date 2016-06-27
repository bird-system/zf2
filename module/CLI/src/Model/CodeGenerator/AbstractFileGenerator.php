<?php
namespace CLI\Model\CodeGenerator;

use Zend\Code\Exception\RuntimeException;
use Zend\Code\Generator\FileGenerator as BaseClass;
use Zend\Db\Metadata\Object\TableObject;

abstract class AbstractFileGenerator extends BaseClass
{
    const OVERWRITE_MODE_ON = 'ON';
    const OVERWRITE_MODE_OFF = 'OFF';

    protected $overwriteMode = self::OVERWRITE_MODE_OFF;

    protected $basePath;

    protected $codeDirectoryName;

    /**
     * @return mixed
     */
    public function getCodeDirectoryName()
    {
        return $this->codeDirectoryName;
    }

    /**
     * @param mixed $codeDirectoryName
     */
    public function setCodeDirectoryName($codeDirectoryName)
    {
        $this->codeDirectoryName = $codeDirectoryName;
    }

    /**
     * @var TableObject
     */
    protected $table;

    /**
     * @return string
     */
    public function getOverwriteMode()
    {
        return $this->overwriteMode;
    }

    /**
     * @param string $overwriteMode
     */
    public function setOverwriteMode($overwriteMode)
    {
        $this->overwriteMode = $overwriteMode;
    }

    /**
     * @return mixed
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * @param mixed $basePath
     */
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    public function getFilePath()
    {
        $namespacePathInfo =
            explode(DIRECTORY_SEPARATOR, str_replace('\\', DIRECTORY_SEPARATOR, $this->getNamespace()));
        $moduleName        = array_shift($namespacePathInfo);
        if ('Tests' == $namespacePathInfo[0]) {
            array_shift($namespacePathInfo);
        }
        $namespacePath = implode(DIRECTORY_SEPARATOR, $namespacePathInfo);

        return $this->getBasePath() . DIRECTORY_SEPARATOR .
               $moduleName . DIRECTORY_SEPARATOR .
               $this->getCodeDirectoryName() . DIRECTORY_SEPARATOR .
               $namespacePath . DIRECTORY_SEPARATOR .
               $this->getFilename() . '.php';
    }

    /**
     * @return AbstractFileGenerator
     */
    public function write($outputOnly = false, $forceOverwrite = false)
    {
        if ($outputOnly) {
            $filepath = 'php://stdout';
            file_put_contents($filepath, $this->generate());
        } else {
            $filepath = $this->getFilePath();
            if (!file_exists(dirname($filepath))) {
                mkdir(dirname($filepath), 0777, true);
            }
            if ($filepath == '' || !is_writable(dirname($this->filename))) {
                throw new RuntimeException('This code generator object is not writable.');
            }
            if (self::OVERWRITE_MODE_ON == $this->getOverwriteMode() || !file_exists($filepath) || $forceOverwrite) {
                file_put_contents($filepath, $this->generate());
            }
        }

        return $this;
    }
}