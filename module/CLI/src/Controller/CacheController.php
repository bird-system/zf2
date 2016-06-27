<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 20/01/2016
 * Time: 16:19
 */

namespace CLI\Controller;


use Zend\Cache\Exception\RuntimeException;
use Zend\Cache\Storage\FlushableInterface;
use Zend\Cache\Storage\IterableInterface;
use Zend\Cache\Storage\StorageInterface;
use Zend\Text\Table;

class CacheController extends AbstractConsoleActionController
{
    protected $banner = 'Cache Control';

    protected $help = [
        '__SCRIPT__ cache help'                         => 'Display this help',
        '__SCRIPT__ cache clear [all|metadata|default]' => 'Clear the cache',
    ];

    public function clearAction()
    {

        $mode = $this->getRequest()->getParam('mode', 'default');

        /** @var StorageInterface[] $caches */
        $caches = [];
        if ('all' == $mode || 'default' == $mode) {
            $caches['cache'] = $this->serviceLocator->get('cache');
        }
        if ('all' == $mode || 'metadata' == $mode) {
            $caches['cache-dbtable-metadata'] = $this->serviceLocator->get('cache-dbtable-metadata');
        }

        foreach ($caches as $key => $cache) {
            try {
                switch (true) {
                    case $cache instanceof FlushableInterface:
                        $cache->flush();
                        break;
                    case $cache instanceof IterableInterface:
                        $cache->removeItems(iterator_to_array($cache->getIterator()));
                        break;
                    default:
                        throw new \Exception(sprintf('%s cannot be cleared!', get_class($cache)));
                }
                $this->console->writeLine("[$key] cleared!");
            } catch (RuntimeException $e) {
                $this->console->writeLine($e->getMessage());
                $this->console->writeLine('Maybe try run this script as sudo ?');
            }
        }

    }
}