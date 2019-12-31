<?php

/**
 * @see       https://github.com/laminas/laminas-mvc for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Controller\Plugin;

use Laminas\Form\Element\Collection;
use Laminas\Form\Form;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\InputFilter\InputFilter;
use Laminas\Mvc\ModuleRouteListener;
use Laminas\Mvc\MvcEvent;
use Laminas\Mvc\Router\Http\Literal as LiteralRoute;
use Laminas\Mvc\Router\Http\Segment as SegmentRoute;
use Laminas\Mvc\Router\RouteMatch;
use Laminas\Mvc\Router\SimpleRouteStack;
use Laminas\Stdlib\Parameters;
use Laminas\Validator\NotEmpty;
use LaminasTest\Mvc\Controller\TestAsset\SampleController;
use LaminasTest\Session\TestAsset\TestManager as SessionManager;
use PHPUnit_Framework_TestCase as TestCase;

class FilePostRedirectGetTest extends TestCase
{
    public $form;
    public $controller;
    public $event;
    public $request;
    public $response;
    public $collection;

    public function setUp()
    {
        $this->form = new Form();

        $this->collection = new Collection('links', array(
                'count' => 1,
                'allow_add' => true,
                'target_element' => array(
                    'type' => 'LaminasTest\Mvc\Controller\Plugin\TestAsset\LinksFieldset',
                ),
        ));

        $router = new SimpleRouteStack;
        $router->addRoute('home', LiteralRoute::factory(array(
            'route'    => '/',
            'defaults' => array(
                'controller' => 'LaminasTest\Mvc\Controller\TestAsset\SampleController',
            )
        )));

        $router->addRoute('sub', SegmentRoute::factory(array(
            'route' => '/foo/:param',
            'defaults' => array(
                'param' => 1
            )
        )));

        $router->addRoute('ctl', SegmentRoute::factory(array(
            'route' => '/ctl/:controller',
            'defaults' => array(
                '__NAMESPACE__' => 'LaminasTest\Mvc\Controller\TestAsset',
            )
        )));

        $this->controller = new SampleController();
        $this->request    = new Request();
        $this->event      = new MvcEvent();
        $this->routeMatch = new RouteMatch(array('controller' => 'controller-sample', 'action' => 'postPage'));

        $this->event->setRequest($this->request);
        $this->event->setRouteMatch($this->routeMatch);
        $this->event->setRouter($router);

        $this->sessionManager = new SessionManager();
        $this->sessionManager->destroy();

        $this->controller->setEvent($this->event);
        $this->controller->flashMessenger()->setSessionManager($this->sessionManager);
    }

    public function testReturnsFalseOnIntialGet()
    {
        $result    = $this->controller->dispatch($this->request, $this->response);
        $prgResult = $this->controller->fileprg($this->form, 'home');

        $this->assertFalse($prgResult);
    }

    public function testRedirectsToUrlOnPost()
    {
        $this->request->setMethod('POST');
        $this->request->setPost(new Parameters(array(
            'postval1' => 'value'
        )));

        $this->controller->dispatch($this->request, $this->response);
        $prgResultUrl = $this->controller->fileprg($this->form, '/test/getPage', true);

        $this->assertInstanceOf('Laminas\Http\Response', $prgResultUrl);
        $this->assertTrue($prgResultUrl->getHeaders()->has('Location'));
        $this->assertEquals('/test/getPage', $prgResultUrl->getHeaders()->get('Location')->getUri());
        $this->assertEquals(303, $prgResultUrl->getStatusCode());
    }

    public function testRedirectsToRouteOnPost()
    {
        $this->request->setMethod('POST');
        $this->request->setPost(new Parameters(array(
            'postval1' => 'value1'
        )));

        $this->controller->dispatch($this->request, $this->response);
        $prgResultRoute = $this->controller->fileprg($this->form, 'home');

        $this->assertInstanceOf('Laminas\Http\Response', $prgResultRoute);
        $this->assertTrue($prgResultRoute->getHeaders()->has('Location'));
        $this->assertEquals('/', $prgResultRoute->getHeaders()->get('Location')->getUri());
        $this->assertEquals(303, $prgResultRoute->getStatusCode());
    }

    /**
     * @expectedException Laminas\Mvc\Exception\RuntimeException
     */
    public function testThrowsExceptionOnRouteWithoutRouter()
    {
        $controller = $this->controller;
        $controller = $controller->getEvent()->setRouter(new SimpleRouteStack);

        $this->request->setMethod('POST');
        $this->request->setPost(new Parameters(array(
            'postval1' => 'value'
        )));

        $this->controller->dispatch($this->request, $this->response);
        $this->controller->fileprg($this->form, 'some/route');
    }

    public function testNullRouteUsesMatchedRouteName()
    {
        $this->controller->getEvent()->getRouteMatch()->setMatchedRouteName('home');

        $this->request->setMethod('POST');
        $this->request->setPost(new Parameters(array(
            'postval1' => 'value1'
        )));

        $this->controller->dispatch($this->request, $this->response);
        $prgResultRoute = $this->controller->fileprg($this->form);

        $this->assertInstanceOf('Laminas\Http\Response', $prgResultRoute);
        $this->assertTrue($prgResultRoute->getHeaders()->has('Location'));
        $this->assertEquals('/', $prgResultRoute->getHeaders()->get('Location')->getUri());
        $this->assertEquals(303, $prgResultRoute->getStatusCode());
    }

    public function testReuseMatchedParameters()
    {
        $this->controller->getEvent()->getRouteMatch()->setMatchedRouteName('sub');

        $this->request->setMethod('POST');
        $this->request->setPost(new Parameters(array(
            'postval1' => 'value1'
        )));

        $this->controller->dispatch($this->request, $this->response);
        $prgResultRoute = $this->controller->fileprg($this->form);

        $this->assertInstanceOf('Laminas\Http\Response', $prgResultRoute);
        $this->assertTrue($prgResultRoute->getHeaders()->has('Location'));
        $this->assertEquals('/foo/1', $prgResultRoute->getHeaders()->get('Location')->getUri());
        $this->assertEquals(303, $prgResultRoute->getStatusCode());
    }

    public function testReturnsPostOnRedirectGet()
    {
        // Do POST
        $params = array(
            'postval1' => 'value'
        );
        $this->request->setMethod('POST');
        $this->request->setPost(new Parameters($params));

        $this->form->add(array(
            'name' => 'postval1'
        ));

        $this->controller->dispatch($this->request, $this->response);
        $prgResultUrl = $this->controller->fileprg($this->form, '/test/getPage', true);

        $this->assertInstanceOf('Laminas\Http\Response', $prgResultUrl);
        $this->assertTrue($prgResultUrl->getHeaders()->has('Location'));
        $this->assertEquals('/test/getPage', $prgResultUrl->getHeaders()->get('Location')->getUri());
        $this->assertEquals(303, $prgResultUrl->getStatusCode());

        // Do GET
        $this->request = new Request();
        $this->controller->dispatch($this->request, $this->response);
        $prgResult = $this->controller->fileprg($this->form, '/test/getPage', true);

        $this->assertEquals($params, $prgResult);
        $this->assertEquals($params['postval1'], $this->form->get('postval1')->getValue());

        // Do GET again to make sure data is empty
        $this->request = new Request();
        $this->controller->dispatch($this->request, $this->response);
        $prgResult = $this->controller->fileprg($this->form, '/test/getPage', true);

        $this->assertFalse($prgResult);
    }

    public function testAppliesFormErrorsOnPostRedirectGet()
    {
        // Do POST
        $params = array();
        $this->request->setMethod('POST');
        $this->request->setPost(new Parameters($params));

        $this->form->add(array(
            'name' => 'postval1'
        ));
        $inputFilter = new InputFilter();
        $inputFilter->add(array(
            'name'     => 'postval1',
            'required' => true,
        ));
        $this->form->setInputFilter($inputFilter);

        $this->controller->dispatch($this->request, $this->response);
        $prgResultUrl = $this->controller->fileprg($this->form, '/test/getPage', true);
        $this->assertInstanceOf('Laminas\Http\Response', $prgResultUrl);
        $this->assertTrue($prgResultUrl->getHeaders()->has('Location'));
        $this->assertEquals('/test/getPage', $prgResultUrl->getHeaders()->get('Location')->getUri());
        $this->assertEquals(303, $prgResultUrl->getStatusCode());

        // Do GET
        $this->request = new Request();
        $this->controller->dispatch($this->request, $this->response);
        $prgResult = $this->controller->fileprg($this->form, '/test/getPage', true);
        $messages  = $this->form->getMessages();

        $this->assertEquals($params, $prgResult);
        $this->assertNotEmpty($messages['postval1']['isEmpty']);
    }

    public function testReuseMatchedParametersWithSegmentController()
    {
        $expects = '/ctl/sample';
        $this->request->setMethod('POST');
        $this->request->setUri($expects);
        $this->request->setPost(new Parameters(array(
            'postval1' => 'value1'
        )));

        $routeMatch = $this->event->getRouter()->match($this->request);
        $this->event->setRouteMatch($routeMatch);

        $moduleRouteListener = new ModuleRouteListener;
        $moduleRouteListener->onRoute($this->event);

        $this->controller->dispatch($this->request, $this->response);
        $prgResultRoute = $this->controller->fileprg($this->form);

        $this->assertInstanceOf('Laminas\Http\Response', $prgResultRoute);
        $this->assertTrue($prgResultRoute->getHeaders()->has('Location'));
        $this->assertEquals($expects, $prgResultRoute->getHeaders()->get('Location')->getUri(), 'redirect to the same url');
        $this->assertEquals(303, $prgResultRoute->getStatusCode());
    }

    public function testCollectionInputFilterIsInitializedBeforePluginRetrievesIt()
    {
        $fieldset = new TestAsset\InputFilterProviderFieldset();
        $collectionSpec = array(
            'name' => 'test_collection',
            'type' => 'collection',
            'options' => array(
                'target_element' => $fieldset
            ),
        );

        $form = new Form();
        $form->add($collectionSpec);

        $postData = array(
            'test_collection' => array(
                array(
                    'test_field' => 'foo'
                ),
                array(
                    'test_field' => 'bar'
                )
            )
        );

        // test POST
        $request = new Request();
        $request->setMethod('POST');
        $request->setPost(new Parameters($postData));
        $this->controller->dispatch($request, $this->response);

        $this->controller->fileprg($form, '/someurl', true);

        $data = $form->getData();

        $this->assertArrayHasKey(0, $data['test_collection']);
        $this->assertArrayHasKey(1, $data['test_collection']);

        $this->assertSame('FOO', $data['test_collection'][0]['test_field']);
        $this->assertSame('BAR', $data['test_collection'][1]['test_field']);

        // now test GET with a brand new form instance
        $form = new Form();
        $form->add($collectionSpec);

        $request = new Request();
        $this->controller->dispatch($request, $this->response);

        $this->controller->fileprg($form, '/someurl', true);

        $data = $form->getData();

        $this->assertArrayHasKey(0, $data['test_collection']);
        $this->assertArrayHasKey(1, $data['test_collection']);

        $this->assertSame('FOO', $data['test_collection'][0]['test_field']);
        $this->assertSame('BAR', $data['test_collection'][1]['test_field']);
    }

    public function testCorrectInputDataMerging()
    {
        require_once __DIR__ . '/TestAsset/DisablePhpUploadChecks.php';
        require_once __DIR__ . '/TestAsset/DisablePhpMoveUploadedFileChecks.php';

        $form = new Form();
        $form->add(array(
            'name' => 'collection',
            'type' => 'collection',
            'options' => array(
                'target_element' => new TestAsset\TestFieldset('target'),
                'count' => 2,
            )
        ));

        copy(__DIR__ . '/TestAsset/nullfile', __DIR__ . '/TestAsset/nullfile_copy');

        $request = $this->request;
        $request->setMethod('POST');
        $request->setPost(new Parameters(array(
            'collection' => array(
                0 => array(
                    'text' => 'testvalue1',
                ),
                1 => array(
                    'text' => '',
                )
            )
        )));
        $request->setFiles(new Parameters(array(
            'collection' => array(
                0 => array(
                    'file' => array(
                        'name' => 'test.jpg',
                        'type' => 'image/jpeg',
                        'size' => 20480,
                        'tmp_name' => __DIR__ . '/TestAsset/nullfile_copy',
                        'error' => UPLOAD_ERR_OK
                    ),
                ),
            )
        )));

        $this->controller->dispatch($this->request, $this->response);
        $this->controller->fileprg($form, '/test/getPage', true);

        $this->assertFalse($form->isValid());
        $data = $form->getData();

        $this->assertEquals(array(
            'collection' => array(
                0 => array(
                    'text' => 'testvalue1',
                    'file' => array(
                        'name' => 'test.jpg',
                        'type' => 'image/jpeg',
                        'size' => 20480,
                        'tmp_name' => __DIR__ . DIRECTORY_SEPARATOR . 'TestAsset' . DIRECTORY_SEPARATOR . 'testfile.jpg',
                        'error' => 0
                    ),
                ),
                1 => array(
                    'text' => null,
                    'file' => null,
                )
            )
        ), $data);

        $this->assertFileExists($data['collection'][0]['file']['tmp_name']);

        unlink($data['collection'][0]['file']['tmp_name']);

        $messages = $form->getMessages();
        $this->assertTrue(isset($messages['collection'][1]['text'][NotEmpty::IS_EMPTY]));
        $this->assertTrue(isset($messages['collection'][1]['file'][NotEmpty::IS_EMPTY]));
    }
}
