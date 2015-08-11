<?php

namespace Rezzza\RestApiBehatExtension;

use mageekguy\atoum\asserter;
use Behat\Behat\Context\BehatContext;
use Behat\Gherkin\Node\PyStringNode;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Client as HttpClient;
use Rezzza\RestApiBehatExtension\Json\JsonStorage;
use Rezzza\RestApiBehatExtension\Json\JsonStorageAware;

class RestApiContext extends BehatContext implements JsonStorageAware
{
    private $asserter;

    /** @var HttpClient */
    private $httpClient;

    /** @var array|\Guzzle\Http\Message\RequestInterface */
    private $request;

    /** @var \Guzzle\Http\Message\Response|array */
    private $response;

    /** @var array */
    private $requestHeaders = array();

    /** @var bool */
    private $enableJsonInspection = true;

    /** @var JsonStorage */
    private $jsonStorage;

    public function __construct(HttpClient $httpClient, $asserter, $enableJsonInspection)
    {
        $this->requestHeaders = array();
        $this->httpClient = $httpClient;
        $this->asserter = $asserter;
        $this->enableJsonInspection = (bool) $enableJsonInspection;
    }

    /**
     * {@inheritdoc}
     */
    public function setJsonStorage(JsonStorage $jsonStorage)
    {
        $this->jsonStorage = $jsonStorage;
    }

    /**
     * @param string $method request method
     * @param string $url    relative url
     *
     * @When /^(?:I )?send a ([A-Z]+) request to "([^"]+)"$/
     */
    public function iSendARequest($method, $url)
    {
        $this->sendRequest($method, $url);
    }

    /**
     * Sends HTTP request to specific URL with raw body from PyString.
     *
     * @param string       $method request method
     * @param string       $url relative url
     * @param PyStringNode $body
     *
     * @When /^(?:I )?send a ([A-Z]+) request to "([^"]+)" with body:$/
     * @throws BadResponseException
     * @throws \Exception
     */
    public function iSendARequestWithBody($method, $url, PyStringNode $body)
    {
        $this->sendRequest($method, $url, $body);
    }

    /**
     * @param string $code status code
     *
     * @Then /^(?:the )?response status code should be (\d+)$/
     */
    public function theResponseCodeShouldBe($code)
    {
        $expected = intval($code);
        $actual = intval($this->response->getStatusCode());
        $this->asserter->variable($actual)->isEqualTo($expected);
    }

    /**
     * @Given /^I set "([^"]*)" header equal to "([^"]*)"$/
     */
    public function iSetHeaderEqualTo($headerName, $headerValue)
    {
        $this->setRequestHeader($headerName, $headerValue);
    }

    /**
     * @Given /^I add "([^"]*)" header equal to "([^"]*)"$/
     */
    public function iAddHeaderEqualTo($headerName, $headerValue)
    {
        $this->addRequestHeader($headerName, $headerValue);
    }

    /**
     * Set login / password for next HTTP authentication
     *
     * @When /^I set basic authentication with "(?P<username>[^"]*)" and "(?P<password>[^"]*)"$/
     */
    public function iSetBasicAuthenticationWithAnd($username, $password)
    {
        $this->removeRequestHeader('Authorization');
        $authorization = base64_encode($username . ':' . $password);
        $this->addRequestHeader('Authorization', 'Basic ' . $authorization);
    }

    /**
     * @Then print response
     */
    public function printResponse()
    {
        $request = $this->request;
        $response = $this->response;

        echo sprintf(
            "%s %s :\n%s%s\n",
            $request->getMethod(),
            $request->getUrl(),
            $response->getRawHeaders(),
            $response->getBody()
        );
    }

    /**
     * @return array|\Guzzle\Http\Message\RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return array|\Guzzle\Http\Message\Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return array
     * @deprecated BC Alias, prefer using getRequestHeaders()
     */
    public function getHeaders()
    {
        return $this->getRequestHeaders();
    }

    /**
     * @return array
     */
    public function getRequestHeaders()
    {
        return $this->requestHeaders;
    }

    /**
     * @param string $name
     * @param string $value
     */
    protected function addRequestHeader($name, $value)
    {
        if (isset($this->requestHeaders[$name])) {
            if (!is_array($this->requestHeaders[$name])) {
                $this->requestHeaders[$name] = array($this->requestHeaders[$name]);
            }
            $this->requestHeaders[$name][] = $value;
        } else {
            $this->requestHeaders[$name] = $value;
        }
    }

    /**
     * @param string $headerName
     */
    protected function removeRequestHeader($headerName)
    {
        if (array_key_exists($headerName, $this->requestHeaders)) {
            unset($this->requestHeaders[$headerName]);
        }
    }

    /**
     * @param string $name
     * @param string $value
     */
    protected function setRequestHeader($name, $value)
    {
        $this->removeRequestHeader($name);
        $this->addRequestHeader($name, $value);
    }

    /**
     * @param string $method
     * @param string $url
     * @param PyStringNode $body
     */
    private function sendRequest($method, $url, $body = null)
    {
        $this->createRequest($method, $url, $body);

        try {
            $this->response = $this->httpClient->send($this->request);
        } catch (BadResponseException $e) {
            $this->response = $e->getResponse();

            if (null === $this->response) {
                throw $e;
            }
        }

        if (null !== $this->jsonStorage && $this->enableJsonInspection) {
            $this->jsonStorage->writeRawContent($this->response->getBody(true));
        }
    }

    /**
     * @param string                $method
     * @param string                $uri    With or without host
     * @param string|resource|array $body
     */
    private function createRequest($method, $uri, $body = null)
    {
        if (!$this->hasHost($uri)) {
            $uri = rtrim($this->httpClient->getBaseUrl(), '/') . '/' . ltrim($uri, '/');
        }
        $this->request = $this->httpClient->createRequest($method, $uri, $this->requestHeaders, $body);
        // Reset headers used for the HTTP request
        $this->requestHeaders = array();
    }

    /**
     * @param string $uri
     *
     * @return bool
     */
    private function hasHost($uri)
    {
        return strpos($uri, '://') !== false;
    }
}
