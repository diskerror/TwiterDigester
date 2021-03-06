<?php

namespace Service\Application;


use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Url;
use Phalcon\Mvc\View\Simple;
use Service\View;

/**
 * Class Http
 *
 * @package Service\Application
 */
class Http extends DiAbstract
{
	public function init(): self
	{
//		$this->_loader->registerDirs([$this->_basePath . '/app/controllers/'])
//					  ->register();

		$di = new FactoryDefault();

		parent::_commonDi($di);

		$self = $this;

//		$di->setShared('view', function() use ($self) {
//			static $view;
//			if (!isset($view)) {
//				$view = new View();
//				$view->setViewsDir($self->_basePath . '/app/views/');
//				$view->setLayoutsDir($self->_basePath . '/app/views/layouts/');
//				$view->setTemplateAfter('default');
//				$view->start();
//			}
//			return $view;
//		});

		$di->setShared('view', function() use ($self) {
			static $view;
			if (!isset($view)) {
				$view = new Simple();
				$view->setViewsDir($self->_basePath . '/app/views/');
			}
			return $view;
		});

//		$di->setShared('url', function() {
//			static $url;
//			if (!isset($url)) {
//				$url = new Url();
//				$url->setBaseUri('/');
////                $url->setBasePath($this->_basePath);
//			}
//			return $url;
//		});

//		$di->setShared('dispatcher', function() use ($di) {
//			static $dispatcher;
//
//			if (!isset($dispatcher)) {
//				$events = $di->getShared('eventsManager');
//
//				$events->attach(
//					'dispatch:beforeException',
//					function($event, Dispatcher $dispatcher, \Exception $exception) {
//						switch ($exception->getCode()) {
//							case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
//								$dispatcher->forward([
//									'controller' => 'error',
//									'action'     => 'show404',
//								]);
//								return false;
//
//							default:
//								//  Check for controller not found exceptions.
//								if ($exception instanceof Dispatcher\Exception) {
//									$dispatcher->forward([
//										'controller' => 'error',
//										'action'     => 'show404',
//									]);
//									return false;
//								}
//
//								$dispatcher->forward([
//									'controller' => 'error',
//									'action'     => 'index',
//									'params'     => [$exception],
//								]);
//								return false;
//						}
//					}
//				);
//
//				$dispatcher = new Dispatcher();
//				$dispatcher->setEventsManager($events);
//			}
//
//			return $dispatcher;
//		});

		$this->_application = new Application($di);
		$this->_application->useImplicitView(false);

		return $this;
	}

	/**
	 * Run application.
	 */
	public function run(array $argv): string
	{
		$ri = $this->_application->handle($argv['REQUEST_URI']);
		return $ri->getContent();
	}
}
