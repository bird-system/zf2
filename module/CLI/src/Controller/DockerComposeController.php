<?php


namespace CLI\Controller;


class DockerComposeController extends AbstractConsoleActionController
{
    protected $banner = 'DockerCompose Utility';

    protected $help = [
        '__SCRIPT__ docker-compose generate-name ' .
        'TEST-SUITE' => 'return temporary docker-compose.yml file name',
        '__SCRIPT__ docker-compose generate-content ' .
        'TEST-SUITE SEED-FILE' => 'return temporary docker-compose.yml file content ' .
                                  'with db and php service inside for phpunit purpose.',
    ];

    public function generateContentAction()
    {
        $testSuite = $this->getRequest()->getParam('test-suite');
        $testSuite = strtolower(str_replace('\\', '-', $testSuite));
        if (strpos($testSuite, '-', strlen($testSuite) - 1)) {
            $testSuite = substr($testSuite, 0, strlen($testSuite) - 1);
        }

        $seedFile = $this->getRequest()->getParam('file-seed');

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