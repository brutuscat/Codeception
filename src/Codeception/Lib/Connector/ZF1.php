<?php
namespace Codeception\Lib\Connector;

use Symfony\Component\BrowserKit\Client;
use Symfony\Component\BrowserKit\Request;
use Symfony\Component\BrowserKit\Response;

class ZF1 extends Client
{
    /**
     * @var \Zend_Controller_Front
     */
    protected $front;

    /**
     * @var \Zend_Application
     */
    protected $bootstrap;

    /**
     * @var  \Zend_Controller_Request_HttpTestCase
     */
    protected $zendRequest;

    public function setBootstrap($bootstrap)
    {
        $this->bootstrap = $bootstrap;

        $this->front = $this->bootstrap
            ->getBootstrap()
            ->getResource('frontcontroller');
        $this->front
            ->throwExceptions(true)
            ->returnResponse(false);
    }

    public function doRequest($request)
    {

        // redirector should not exit
        $redirector = \Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->setExit(false);

        // json helper should not exit
        $json               = \Zend_Controller_Action_HelperBroker::getStaticHelper('json');
        $json->suppressExit = true;

        $zendRequest = new \Zend_Controller_Request_HttpTestCase();
        $zendRequest->setMethod($request->getMethod());
        $zendRequest->setCookies($request->getCookies());
        $zendRequest->setParams($request->getParameters());
        // Sf2's BrowserKit does not distinguish between GET, POST, PUT etc.,
        // so we set all parameters in ZF's request here to not break apps
        // relying on $request->getPost()
        $zendRequest->setPost($request->getParameters());
        $zendRequest->setRawBody($request->getContent());
        $zendRequest->setRequestUri(str_replace('http://localhost','',$request->getUri()));
        $zendRequest->setHeaders(
            $this->getNormalizedHeadersUsing($request->getServer())
        );
        $_FILES = $request->getFiles();
        $_SERVER = array_merge($_SERVER, $request->getServer());

        $zendResponse = new \Zend_Controller_Response_HttpTestCase;
        $this->front->setRequest($zendRequest)->setResponse($zendResponse);

        ob_start();
        $this->bootstrap->run();
        ob_end_clean();

        $this->zendRequest = $zendRequest;

        $response = new Response(
            $zendResponse->getBody(),
            $zendResponse->getHttpResponseCode(),
            $this->getKeyValueHeaders($zendResponse)
        );
        return $response;
    }

    /**
     * @return \Zend_Controller_Request_HttpTestCase
     */
    public function getZendRequest()
    {
        return $this->zendRequest;
    }

    private function getNormalizedHeadersUsing($values)
    {
        $fixedValues = $values;
        foreach($values as $key => $value) {
            if (substr($key, 0, 5) == "HTTP_") {
                $key = str_replace(
                        " ",
                        "-",
                        ucwords(strtolower(
                            str_replace("_", " ", substr($key, 5))
                        ))
                );
                $fixedValues[$key] = $value;
            }
            else {
                $key = str_replace(
                    " ",
                    "-",
                    ucwords(strtolower(
                        str_replace("_", " ", $key)
                    ))
                );
                $fixedValues[$key] = $value;
            }
        }
        return $fixedValues;
    }

    private function getKeyValueHeaders($zendResponse)
    {
        $headers = array();
        foreach ($zendResponse->getRawHeaders() as $header) {
            $headers[] = $header;
        }
        foreach ($zendResponse->getHeaders() as $header) {
            $name = $header['name'];
            $key  = strtolower($name);
            if (array_key_exists($name, $headers)) {
                if ($header['replace']) {
                    $headers[$key] = $header['value'];
                }
            } else {
                $headers[$key] = $header['value'];
            }
        }
        return $headers;
    }
}
