<?php


namespace CLI\Controller;


class DockerComposeController extends AbstractConsoleActionController
{
    protected $banner = 'DockerCompose Utility';

    protected $help = [
        '__SCRIPT__ docker-compose generate-name ' .
        'TEST-SUITE'                => 'return temporary docker-compose.yml file name',
        '__SCRIPT__ docker-compose generate-content ' .
        'TEST-SUITE SEED-FILE [v2]' => 'return temporary docker-compose.yml file content ' .
                                       'with db and php service inside for phpunit purpose.',
        ['v2', 'use docker-compose version2 formate']
    ];

    public function generateContentAction()
    {
        $testSuite = $this->getRequest()->getParam('test-suite');
        $testSuite = strtolower(str_replace('\\', '-', $testSuite));
        if (strpos($testSuite, '-', strlen($testSuite) - 1)) {
            $testSuite = substr($testSuite, 0, strlen($testSuite) - 1);
        }

        $seedFile = $this->getRequest()->getParam('file-seed');

        $isVersion2 = $this->getRequest()->getParam('version') == 'v2' ? true : false;
        $spaces     = '';

        if ($isVersion2) {
            $spaces = '  ';
        }
        $services = [];
        if ($isVersion2) {
            $services[] = "version: '2'";
            $services[] = 'services:';
        }
        $services[] = $spaces . 'db-' . $testSuite . ':';
        $services[] = $spaces . '  extends:';
        $services[] = $spaces . '    file: ' . $seedFile;
        $services[] = $spaces . '    service: db';
        $services[] = $spaces . 'php-' . $testSuite . ':';
        $services[] = $spaces . '  extends:';
        $services[] = $spaces . '    file: ' . $seedFile;
        $services[] = $spaces . '    service: php';
        $services[] = $spaces . '  links:';
        $services[] = $spaces . '    - "db-' . $testSuite . ':db"';

        echo implode(PHP_EOL, $services);
    }

    public function generateNameAction()
    {
        $testSuite = $this->getRequest()->getParam('test-suite');
        $testSuite = strtolower(str_replace('\\', '-', $testSuite));
        if (strpos($testSuite, '-', strlen($testSuite) - 1)) {
            $testSuite = substr($testSuite, 0, strlen($testSuite) - 1);
        }

        echo $testSuite;
    }
}