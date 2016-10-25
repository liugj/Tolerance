<?php

namespace Tolerance\Tracer\Zipkin;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Tolerance\Tracer\Span\Annotation;
use Tolerance\Tracer\Span\BinaryAnnotation;
use Tolerance\Tracer\Span\Endpoint;
use Tolerance\Tracer\Span\Span;
use Tolerance\Tracer\Tracer;
use Tolerance\Tracer\TracerException;

class ZipkinHttpTracer implements Tracer
{
    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @param ClientInterface $client
     * @param string $baseUrl
     */
    public function __construct(ClientInterface $client, $baseUrl)
    {
        $this->baseUrl = $baseUrl;
        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     */
    public function trace(array $spans)
    {
        try {
            $this->client->request('POST', $this->baseUrl . '/api/v1/spans', [
                'json' => array_map([$this, 'normalizeSpan'], $spans),
            ]);
        } catch (RequestException $e) {
            throw new TracerException('Unable to publish the traces', $e->getCode(), $e);
        }
    }

    /**
     * @param Span $span
     *
     * @return array
     */
    private function normalizeSpan(Span $span)
    {
        return [
            'id' => (string) $span->getIdentifier(),
            'name' => $span->getName(),
            'traceId' => (string) $span->getTraceIdentifier(),
            'parentId' => null !== $span->getParentIdentifier() ? (string) $span->getParentIdentifier() : null,
            'timestamp' => $span->getTimestamp(),
            'duration' => $span->getDuration(),
            'debug' => $span->getDebug(),
            'annotations' => array_map([$this, 'normalizeAnnotation'], $span->getAnnotations()),
            'binaryAnnotations' => array_map([$this, 'normalizeBinaryAnnotation'], $span->getBinaryAnnotations()),
        ];
    }

    /**
     * @param Annotation $annotation
     *
     * @return array
     */
    private function normalizeAnnotation(Annotation $annotation)
    {
        return [
            'value' => $annotation->getValue(),
            'timestamp' => $annotation->getTimestamp(),
            'endpoint' => null !== $annotation->getHost() ? $this->normalizeEndpoint($annotation->getHost()) : null,
        ];
    }

    /**
     * @param BinaryAnnotation $binaryAnnotation
     *
     * @return array
     */
    private function normalizeBinaryAnnotation(BinaryAnnotation $binaryAnnotation)
    {
        return [
            'key' => $binaryAnnotation->getKey(),
            'value' => $binaryAnnotation->getValue(),
            'endpoint' => null !== $binaryAnnotation->getHost() ? $this->normalizeEndpoint($binaryAnnotation->getHost()) : null,
        ];
    }

    /**
     * @param Endpoint $endpoint
     *
     * @return array
     */
    private function normalizeEndpoint(Endpoint $endpoint)
    {
        $normalizedEndpoint = [
            'serviceName' => $endpoint->getServiceName(),
        ];

        if (null !== ($ipv4 = $endpoint->getIpv4())) {
            $normalizedEndpoint['ipv4'] = $ipv4;
        }

        if (null !== ($ipv6 = $endpoint->getIpv6())) {
            $normalizedEndpoint['ipv6'] = $ipv6;
        }

        if (null !== ($port = $endpoint->getPort())) {
            $normalizedEndpoint['port'] = $port;
        }

        return $normalizedEndpoint;
    }
}
