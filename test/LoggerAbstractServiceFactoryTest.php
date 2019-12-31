<?php

/**
 * @see       https://github.com/laminas/laminas-log for the canonical source repository
 * @copyright https://github.com/laminas/laminas-log/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-log/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Log;

use Laminas\Log\ProcessorPluginManager;
use Laminas\Log\Writer\Db as DbWriter;
use Laminas\Log\WriterPluginManager;
use Laminas\Mvc\Service\ServiceManagerConfig;
use Laminas\ServiceManager\ServiceManager;

/**
 * @group      Laminas_Log
 */
class LoggerAbstractServiceFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Laminas\ServiceManager\ServiceLocatorInterface
     */
    protected $serviceManager;

    /**
     * Set up LoggerAbstractServiceFactory and loggers configuration.
     *
     * @see PHPUnit_Framework_TestCase::setUp()
     */
    protected function setUp()
    {
        $this->serviceManager = new ServiceManager(new ServiceManagerConfig(array(
            'abstract_factories' => array('Laminas\Log\LoggerAbstractServiceFactory'),
        )));

        $this->serviceManager->setService('Config', array(
            'log' => array(
                'Application\Frontend' => array(),
                'Application\Backend'  => array(),
            ),
        ));
    }

    /**
     * @return array
     */
    public function providerValidLoggerService()
    {
        return array(
            array('Application\Frontend'),
            array('Application\Backend'),
        );
    }

    /**
     * @return array
     */
    public function providerInvalidLoggerService()
    {
        return array(
            array('Logger\Application\Unknown'),
            array('Logger\Application\Frontend'),
            array('Application\Backend\Logger'),
        );
    }

    /**
     * @param string $service
     * @dataProvider providerValidLoggerService
     */
    public function testValidLoggerService($service)
    {
        $actual = $this->serviceManager->get($service);
        $this->assertInstanceOf('Laminas\Log\Logger', $actual);
    }

    /**
     * @param string $service
     * @dataProvider providerInvalidLoggerService
     * @expectedException \Laminas\ServiceManager\Exception\ServiceNotFoundException
     */
    public function testInvalidLoggerService($service)
    {
        $actual = $this->serviceManager->get($service);
    }

    /**
     * @group 5254
     */
    public function testRetrievesDatabaseServiceFromServiceManagerWhenEncounteringDbWriter()
    {
        $db = $this->getMockBuilder('Laminas\Db\Adapter\Adapter')
            ->disableOriginalConstructor()
            ->getMock();
        $serviceManager = new ServiceManager(new ServiceManagerConfig(array(
            'abstract_factories' => array('Laminas\Log\LoggerAbstractServiceFactory'),
        )));
        $serviceManager->setService('Db\Logger', $db);
        $serviceManager->setService('Config', array(
            'log' => array(
                'Application\Log' => array(
                    'writers' => array(
                        array(
                            'name'     => 'db',
                            'priority' => 1,
                            'options'  => array(
                                'separator' => '_',
                                'column'    => array(),
                                'table'     => 'applicationlog',
                                'db'        => 'Db\Logger',
                            ),
                        ),
                    ),
                ),
            ),
        ));
        $logger = $serviceManager->get('Application\Log');
        $this->assertInstanceOf('Laminas\Log\Logger', $logger);
        $writers = $logger->getWriters();
        $found   = false;
        foreach ($writers as $writer) {
            if ($writer instanceof DbWriter) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Did not find expected DB writer');
        $this->assertAttributeSame($db, 'db', $writer);
    }

    /**
     * @group 4455
     */
    public function testWillInjectWriterPluginManagerIfAvailable()
    {
        $writers = new WriterPluginManager();
        $mockWriter = $this->getMock('Laminas\Log\Writer\WriterInterface');
        $writers->setService('CustomWriter', $mockWriter);

        $services = new ServiceManager(new ServiceManagerConfig(array(
            'abstract_factories' => array('Laminas\Log\LoggerAbstractServiceFactory'),
        )));
        $services->setService('LogWriterManager', $writers);
        $services->setService('Config', array(
            'log' => array(
                'Application\Frontend' => array(
                    'writers' => array(array('name' => 'CustomWriter')),
                ),
            ),
        ));

        $log = $services->get('Application\Frontend');
        $logWriters = $log->getWriters();
        $this->assertEquals(1, count($logWriters));
        $writer = $logWriters->current();
        $this->assertSame($mockWriter, $writer);
    }

    /**
     * @group 4455
     */
    public function testWillInjectProcessorPluginManagerIfAvailable()
    {
        $processors = new ProcessorPluginManager();
        $mockProcessor = $this->getMock('Laminas\Log\Processor\ProcessorInterface');
        $processors->setService('CustomProcessor', $mockProcessor);

        $services = new ServiceManager(new ServiceManagerConfig(array(
            'abstract_factories' => array('Laminas\Log\LoggerAbstractServiceFactory'),
        )));
        $services->setService('LogProcessorManager', $processors);
        $services->setService('Config', array(
            'log' => array(
                'Application\Frontend' => array(
                    'writers' => array(array('name' => 'Null')),
                    'processors' => array(array('name' => 'CustomProcessor')),
                ),
            ),
        ));

        $log = $services->get('Application\Frontend');
        $logProcessors = $log->getProcessors();
        $this->assertEquals(1, count($logProcessors));
        $processor = $logProcessors->current();
        $this->assertSame($mockProcessor, $processor);
    }
}
