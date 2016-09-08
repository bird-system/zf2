<?php


namespace CLI\Controller;


class DockerComposeController extends AbstractConsoleActionController
{
    protected $banner = 'DockerCompose Utility';

    protected $help = [
        '__SCRIPT__ docker-compose generate ' .
        'TEST-SUITE SEED-FILE OUTPUT-FILE' => 'Take create temporary docker-compose.yml file' .
                                              'with db and php service inside for phpunit '
                                              . 'purpose.',
    ];

    public function generateAction()
    {
        $testSuite = $this->getRequest()->getParam('test-suite');
        $testSuite = strtolower(str_replace('\\', '-', $testSuite));
        if (strpos($testSuite, '-', strlen($testSuite) - 1)) {
            $testSuite = substr($testSuite, 0, strlen($testSuite) - 1);
        }

        $seedFile   = $this->getRequest()->getParam('file-seed');
        $outputFile = $this->getRequest()->getParam('file-output');

        $services   = [];
        $services[] = 'db-' . $testSuite . ':';
        $services[] = '  extends:';
        $services[] = '    file: ' . $seedFile;
        $services[] = '    service: db';
        $services[] = 'php-' . $testSuite . ':';
        $services[] = '  extends:';
        $services[] = '    file: ' . $seedFile;
        $services[] = '    service: php';
        $services[] = '  links:';
        $services[] = '    - db-' . $testSuite . ':db';
        file_put_contents($outputFile, implode(PHP_EOL, $services));
        echo $testSuite;
    }
}