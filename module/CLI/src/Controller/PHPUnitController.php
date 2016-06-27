<?php


namespace CLI\Controller;


use SebastianBergmann\FinderFacade\FinderFacade;
use TheSeer\fDOM\fDOMDocument;
use TheSeer\fDOM\fDOMException;

class PHPUnitController extends AbstractConsoleActionController
{
    protected $banner = 'PHPUnit Utility';

    protected $help = [
        '__SCRIPT__ phpunit show-testsuites FILE' => 'List test suites in given phpunit.xml file.',
    ];

    public function showTestsuitesAction()
    {
        $file       = $this->getRequest()->getParam('file');
        $Finder     = new FinderFacade([$file]);
        $testSuites = [];
        foreach ($Finder->findFiles() as $file) {
            $XML = new fDOMDocument();
            try {
                $XML->load($file);
            } catch (fDOMException $e) {
                continue;
            }

            foreach ($XML->getElementsByTagName('testsuite') as $Element) {
                /** @var \TheSeer\fDOM\fDOMElement $Element */
                $testSuites[] = str_replace('\\', '\\\\', $Element->getAttribute('name'));
            }
        }
        echo implode(' ', $testSuites) . PHP_EOL;
    }
}