<?php
namespace Tests\Functional\BehatContext;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\PyStringNode;
use DemoApp\AbstractKernel;
use DemoApp\DefaultKernel;
use DemoApp\KernelWithMethodDocCreatedListener;
use DemoApp\KernelWithServerDocCreatedListener;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Yoanm\JsonRpcServer\Domain\JsonRpcMethodInterface;

/**
 * Defines application features from the specific context.
 */
class DemoAppContext implements Context
{
    /** @var Response|null */
    private $lastResponse;

    /** @var bool */
    private $useKernelWithMethodDocCreatedListener = false;
    /** @var bool */
    private $useKernelWithServerDocCreatedListener = false;

    /**
     * @Given I will use kernel with MethodDocCreated listener
     */
    public function givenIWillUseMethodDocCreatedListener()
    {
        $this->useKernelWithMethodDocCreatedListener = true;
    }

    /**
     * @Given I will use kernel with ServerDocCreated listener
     */
    public function givenIWillUseServerDocCreatedListener()
    {
        $this->useKernelWithServerDocCreatedListener = true;
    }

    /**
     * @When I send a :httpMethod request on :uri demoApp kernel endpoint
     * @When I send following :httpMethod input on :uri demoApp kernel endpoint:
     */
    public function whenISendFollowingPayloadToDemoApp($httpMethod, $uri, PyStringNode $payload = null)
    {
        $this->lastResponse = null;

        $kernel = $this->getDemoAppKernel();
        $kernel->boot();
        $request = Request::create($uri, $httpMethod, [], [], [], [], $payload ? $payload->getRaw() : null);
        $this->lastResponse = $kernel->handle($request);
        $kernel->terminate($request, $this->lastResponse);
        $kernel->shutdown();
    }

    /**
     * @Then I should have a :httpCode response from demoApp with following content:
     */
    public function thenIShouldHaveAResponseFromDemoAppWithFollowingContent($httpCode, PyStringNode $payload)
    {
        Assert::assertInstanceOf(Response::class, $this->lastResponse);
        // Decode payload to get ride of indentation, spacing, etc
        Assert::assertEquals(
            json_decode($payload->getRaw(), true),
            json_decode($this->lastResponse->getContent(), true)
        );
        Assert::assertSame((int) $httpCode, $this->lastResponse->getStatusCode());
    }

    /**
     * @Then Collector should have :methodClass JSON-RPC method with name :methodName
     */
    public function thenCollectorShouldHaveAMethodWithName($methodClass, $methodName)
    {
        $kernel = $this->getDemoAppKernel();
        $kernel->boot();
        $mappingList = $kernel->getContainer()
            ->get('mapping_aware_service')
            ->getMappingList()
        ;
        $kernel->shutdown();

        if (!isset($mappingList[$methodName])) {
            throw new \Exception(sprintf('No mapping defined to method name "%s"', $methodName));
        }
        $method = $mappingList[$methodName];

        Assert::assertInstanceOf(
            JsonRpcMethodInterface::class,
            $method,
            'Method must be a JsonRpcMethodInterface instance'
        );
        Assert::assertInstanceOf(
            $methodClass,
            $method,
            sprintf('Method "%s" is not an instance of "%s"', $methodName, $methodClass)
        );
    }

    /**
     * @return AbstractKernel
     */
    protected function getDemoAppKernel()
    {
        $env = 'prod';
        $debug = true;

        if (true === $this->useKernelWithMethodDocCreatedListener) {
            $kernelClass = KernelWithMethodDocCreatedListener::class;
        } elseif (true === $this->useKernelWithServerDocCreatedListener) {
            $kernelClass = KernelWithServerDocCreatedListener::class;
        } else {
            $kernelClass = DefaultKernel::class;
        }

        return new $kernelClass($env, $debug);
    }
}
