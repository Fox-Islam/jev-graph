<?php

declare(strict_types=1);

namespace Tests\Fakes;

use GuzzleHttp\Psr7\Response;
use Phox\ComposableJev\Graph;
use Phox\ComposableJev\Oracle\Jev;
use Phox\TypeSafe\Client;
use Phox\TypeSafe\Contracts\Transport;
use Phox\TypeSafe\Retry\RetryPolicy;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * TypeSafe's System One, as the SDK's transport, answering each noul 0.99 or 0.01 by the rule of
 * the node it came from, and keeping every request.
 */
final class FakeTypeSafe implements Transport
{
    /** @var list<array<string, mixed>> */
    public array $requests = [];

    /**
     * @param  array<string, \Phox\ComposableJev\Node>  $nodes  by instructions
     */
    private function __construct(private array $nodes) {}

    public static function byRule(Graph $graph): self
    {
        $nodes = [];
        foreach ($graph->nodes() as $node) {
            $nodes[$node->instructions] = $node;
        }

        return new self($nodes);
    }

    public function jev(): Jev
    {
        return new Jev(Client::make('ts-key')->transport($this)->retry(RetryPolicy::none()));
    }

    public function send(RequestInterface $request, float $timeout): ResponseInterface
    {
        $body = json_decode((string) $request->getBody(), true);
        $this->requests[] = $body;
        $answers = [];
        foreach ($body['questions'] as $key => $question) {
            $node = $this->nodes[$question['instructions']];
            $yes = $node->expected(array_intersect_key($body['state'], array_flip($node->reads)));
            $answers[$key] = ['type' => 'noul', 'noul' => $yes ? 0.99 : 0.01];
        }

        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'model' => 'jev-test', 'answers' => $answers, 'usage' => ['input_tokens' => 10, 'output_tokens' => 1],
        ]));
    }
}
