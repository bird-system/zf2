<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 20/01/2016
 * Time: 16:58
 */

namespace CLI\Controller;

use Zend\Console\ColorInterface;
use Zend\Console\Console;
use Zend\Console\Request as ConsoleRequest;
use Zend\Mvc\Controller\AbstractConsoleController as Base;
use Zend\Text\Table;

/**
 * Class AbstractConsoleActionController
 *
 * @package CLI\Controller
 * @method ConsoleRequest getRequest()
 */
abstract class AbstractConsoleActionController extends Base
{
    /**
     * @var string
     */
    protected $banner = 'Abstract Control';

    /**
     * @var [] Help messages in ['Command',Description'] format
     */
    protected $help = [];

    /**
     * @var \Zend\Console\Adapter\AdapterInterface
     */
    protected $console;

    public function __construct()
    {
        $this->console = Console::getInstance();
    }

    /**
     * @return string
     */
    public function getBanner()
    {
        return $this->banner;
    }

    /**
     * @return []
     */
    public function getHelp()
    {
        $help = [];
        foreach ($this->help as $helpKey => $content) {
            $helpKey = $this->filterHelpKeywords($helpKey);
            if (is_array($content)) {
                foreach ($content as $key => $value) {
                    $content[$key] = $this->filterHelpKeywords($value);
                }
                $help[] = $content;
            } else {
                $help[$helpKey] = $this->filterHelpKeywords($content);
            }
        }

        return $help;
    }

    protected function filterHelpKeywords($text)
    {
        $request    = $this->getRequest();
        $scriptName = null;

        if ($request instanceof ConsoleRequest) {
            $scriptName = basename($request->getScriptName());
        }

        return str_replace('__SCRIPT__ ', $scriptName ? $scriptName . ' ' : '', $text);
    }

    public function helpAction()
    {
        $this->renderBanner();
        $this->renderHelp();
    }

    public function renderBanner()
    {
        $this->console->writeLine(str_repeat('=', $this->console->getWidth()), ColorInterface::GREEN);
        $this->console->writeLine(str_repeat(' ',
                                             round(($this->console->getWidth() - strlen($this->getBanner())) / 2)) .
                                  $this->banner, ColorInterface::GREEN);
        $this->console->writeLine(str_repeat('=', $this->console->getWidth()), ColorInterface::GREEN);
        $this->console->writeLine('Usage: ');
    }

    public function renderHelp()
    {
        foreach ($this->getHelp() as $key => $content) {
            if (2 == count($content)) {
                $this->console->write("\t" . $content[0], ColorInterface::NORMAL);
                $this->console->write("\t\t" . $content[1], ColorInterface::NORMAL);
                $this->console->write(PHP_EOL);
            } else {
                $this->console->write("\t" . $key, ColorInterface::GREEN);
                $this->console->write("\t\t" . $content, ColorInterface::NORMAL);
                $this->console->write(PHP_EOL);
            }
        }
    }

}