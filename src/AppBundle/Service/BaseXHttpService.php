<?php

namespace AppBundle\Service;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class BaseXHttpService
{
    /**
     * @var string
     */
    private $dbname;

    /**
     * @var BaseXHttpClient
     */
    private $client;

    /**
     * @var HttpFoundationFactory
     */
    private $httpFoundationFactory;

    /**
     * @param string          $dbname
     * @param BaseXHttpClient $client
     */
    public function __construct(
        string $dbname,
        BaseXHttpClient $client
    ) {
        $this->dbname = $dbname;
        $this->client = $client;
        $this->httpFoundationFactory = new HttpFoundationFactory();
    }

    /**
     * @param string $queryName
     * @param array  $parameters
     *
     * @return Response
     */
    public function sendXQuery($queryName, $parameters = [])
    {
        $xQuery = file_get_contents(__DIR__.sprintf('/XQuery/%s.xquery', $queryName));

        return $this->sendXQueryString($xQuery, $parameters);
    }

    /**
     * @param string $xQuery
     * @param array $parameters
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Exception
     */
    public function sendXQueryString($xQuery, $parameters = [])
    {
        if (!array_key_exists('dbname', $parameters)) {
            $parameters['dbname'] = $this->dbname;
        }
        $variables = [];
        foreach ($parameters as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $variables[] = sprintf('<variable name="%s" value="%s" />', $key, $value);
        }

        $postData = sprintf('
            <query xmlns="http://basex.org/rest">
              <text><![CDATA[%s]]></text>
              %s
            </query>', $xQuery, implode("\n", $variables));

        $request = new Request('POST', '/rest', [], $postData);

        try {
            $psrResponse = $this->client->getClient()->send($request);

            return new Response(
                $psrResponse->getBody()->getContents(),
                $psrResponse->getStatusCode(),
                [
                    'Content-Type' => $psrResponse->getHeaderLine('content-type'),
                ]
            );
        } catch (ClientException $e) {
            throw new \Exception(
                sprintf(
                    'Client error in BaseX: %s. Full Response: %s. xQuery: %s',
                    $e->getMessage(),
                    $e->getResponse()->getBody(),
                    $postData
                ),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @param string $path
     * @param string $xml
     *
     * @return Response
     * @throws \Exception
     */
    public function sendDocument($path, $xml)
    {
        $uri = sprintf('/rest/%s/%s', $this->dbname, $path);
        $request = new Request('PUT', $uri, [], $xml);

        try {
            $psrResponse = $this->client->getClient()->send($request);

            return new Response(
                $psrResponse->getBody()->getContents(),
                $psrResponse->getStatusCode(),
                [
                    'Content-Type' => $psrResponse->getHeaderLine('content-type'),
                ]
            );
        } catch (ClientException $e) {
            throw new \Exception(
                sprintf(
                    'Client error in BaseX: %s. Full Response: %s.',
                    $e->getMessage(),
                    $e->getResponse()->getBody()
                ),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * @param string $path
     *
     * @return Response
     * @throws \Exception
     */
    public function removeDocument($path)
    {
        $uri = sprintf('/rest/%s/%s', $this->dbname, $path);
        $request = new Request('DELETE', $uri);

        try {
            $psrResponse = $this->client->getClient()->send($request);

            return new Response(
                $psrResponse->getBody()->getContents(),
                $psrResponse->getStatusCode(),
                [
                    'Content-Type' => $psrResponse->getHeaderLine('content-type'),
                ]
            );
        } catch (ClientException $e) {
            throw new \Exception(
                sprintf(
                    'Client error in BaseX: %s. Full Response: %s.',
                    $e->getMessage(),
                    $e->getResponse()->getBody()
                ),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Check if an ID exists in the database.
     *
     * @param string $xmlId
     *
     * @return Response
     */
    public function idExists(string $xmlId)
    {
        return $this->sendXQuery('IdExists', ['xmlId' => $xmlId]);
    }

    /**
     * Return a book in ICCXML.
     *
     * @param  string  $bookId
     *
     * @return Response
     */
    public function getBook($bookId)
    {
        return $this->sendXQuery('GetBook', ['bookId' => $bookId]);
    }

    /**
     * @param string $shortCode
     * @param string $xml
     *
     * @return Response
     */
    public function addBook($shortCode, $xml)
    {
        return $this->sendDocument(sprintf('%1$s/%1$s.xml', $shortCode), $xml);
    }

    public function deleteBook($shortCode)
    {
        return $this->deleteDocument(sprintf('%1$s/%1$s.xml', $shortCode));
    }

    /**
     * Return a section in ICCXML fragment.
     *
     * @param string $bookId
     * @param string $sectionId
     * @param bool   $includeChildren
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getSection($bookId, $sectionId, $includeChildren = false)
    {
        return $this->sendXQuery(
            'GetSection',
            [
                'bookId' => $bookId,
                'sectionId' => $sectionId,
                'includeChildren' => $includeChildren
            ]
        );
    }

    /**
     * Prepare HTML to be sent to the BaseX Rest API as an xquery variable.
     *
     * The value of variables is sent to BaseX inside an XML attribute, and therefore the value must not
     * contain any "<" characters, as defined in W3C: https://www.w3.org/TR/REC-xml/#dt-attrval
     *
     * When the HTML finally gets to BaseX, any "<" characters that aren't actually HTML markup (less than characters)
     * must be encoded as HTML entities, otherwise it will cause an error assuming it is an HTML tag.
     *
     * Therefore, the HTML coming into this function must not have HTML special chars encoded, but must have any
     * non-HTML less-than symbols encoded as &lt;
     *
     * @param string $html
     *
     * @return string
     */
    private function prepareHtml(string $html) : string
    {
        $html = str_replace('&lt;', '&amp;lt;', $html);

        return htmlspecialchars(
            htmlspecialchars_decode($html, ENT_COMPAT | ENT_HTML5),
            ENT_COMPAT | ENT_HTML5,
            'UTF-8'
        );
    }

    /**
     * @param string $path
     *
     * @return Response
     * @throws \Exception
     */
    private function deleteDocument($path)
    {
        $uri = sprintf('/rest/%s/%s', $this->dbname, $path);
        $request = new Request('DELETE', $uri, []);

        try {
            $psrResponse = $this->client->getClient()->send($request);

            return new Response(
                $psrResponse->getBody()->getContents(),
                $psrResponse->getStatusCode(),
                [
                    'Content-Type' => $psrResponse->getHeaderLine('content-type'),
                ]
            );
        } catch (ClientException $e) {
            throw new \Exception(
                sprintf(
                    'Client error in BaseX: %s. Full Response: %s.',
                    $e->getMessage(),
                    $e->getResponse()->getBody()
                ),
                $e->getCode(),
                $e
            );
        }
    }
}
