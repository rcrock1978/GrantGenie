<?php

declare(strict_types=1);

namespace App\Infrastructure\External;

use App\Http\Middleware\CorrelationIdMiddleware;
use App\Observability\Tracer;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * T029: AIServiceClient.
 *
 * Thin HTTP/JSON adapter for the internal AI service contract
 * (see contracts/ai-service-http.yaml). The Laravel backend is the sole
 * consumer; the AI service is never exposed publicly.
 *
 * Auth: shared-secret header in dev (config: `ai_service.internal_token`).
 * In production, traffic flows over the AKS service mesh which terminates
 * mTLS before the request reaches this client.
 *
 * Maps AI service errors to RFC 7807 problem+json responses via ProblemDetails.
 */
final class AIServiceClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly Tracer $tracer,
    ) {}

    public function retrieveRag(string $accountId, string $query, int $topK = 5): array
    {
        return $this->tracer->span('ai.rag.retrieve', function () use ($accountId, $query, $topK): array {
            $response = $this->request('POST', '/internal/v1/rag/retrieve', [
                'account_id' => $accountId,
                'query' => $query,
                'top_k' => $topK,
                'rerank' => true,
            ]);
            return $response->json();
        });
    }

    public function generateProposal(string $accountId, array $proposal): array
    {
        return $this->tracer->span('ai.generate.proposal', function () use ($accountId, $proposal): array {
            $response = $this->request('POST', '/internal/v1/generate/proposal', $proposal);
            return $response->json();
        });
    }

    public function generateBudgetNarrative(string $accountId, string $proposalId, array $items, array $funderCategories): array
    {
        return $this->tracer->span('ai.generate.budget_narrative', function () use ($accountId, $proposalId, $items, $funderCategories): array {
            $response = $this->request('POST', '/internal/v1/generate/budget-narrative', [
                'account_id' => $accountId,
                'proposal_id' => $proposalId,
                'items' => $items,
                'funder_budget_categories' => $funderCategories,
            ]);
            return $response->json();
        });
    }

    public function score(string $accountId, string $proposalId, array $sections): array
    {
        return $this->tracer->span('ai.eval.score', function () use ($accountId, $proposalId, $sections): array {
            $response = $this->request('POST', '/internal/v1/eval/score', [
                'account_id' => $accountId,
                'proposal_id' => $proposalId,
                'sections' => $sections,
            ]);
            return $response->json();
        });
    }

    public function checkSafety(string $inputText, ?string $outputText = null, bool $redactPii = true): array
    {
        return $this->tracer->span('ai.safety.check', function () use ($inputText, $outputText, $redactPii): array {
            $response = $this->request('POST', '/internal/v1/safety/check', [
                'input_text' => $inputText,
                'output_text' => $outputText,
                'redact_pii' => $redactPii,
            ]);
            return $response->json();
        });
    }

    /**
     * @return Response
     */
    private function request(string $method, string $path, array $body): Response
    {
        $baseUrl = rtrim((string) config('ai_service.base_url', env('AI_SERVICE_URL', 'http://ai-service:8001')), '/');
        $token = (string) config('ai_service.internal_token', env('AI_SERVICE_INTERNAL_TOKEN', ''));
        $correlationId = request()?->attributes->get(CorrelationIdMiddleware::ATTRIBUTE);

        try {
            $response = $this->http
                ->withHeaders(array_filter([
                    'Authorization' => $token !== '' ? "Bearer {$token}" : null,
                    'X-Correlation-Id' => is_string($correlationId) ? $correlationId : null,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]))
                ->timeout(30)
                ->connectTimeout(5)
                ->{$this->httpMethod($method)}($baseUrl . $path, $body);

            if ($response->serverError() || $response->clientError()) {
                throw new RuntimeException(sprintf(
                    'ai_service_error status=%d body=%s',
                    $response->status(),
                    $response->body()
                ));
            }
            return $response;
        } catch (RequestException $e) {
            throw new RuntimeException('ai_service_unreachable: ' . $e->getMessage(), 0, $e);
        } catch (GuzzleException $e) {
            throw new RuntimeException('ai_service_transport_error: ' . $e->getMessage(), 0, $e);
        }
    }

    private function httpMethod(string $verb): string
    {
        return match (strtoupper($verb)) {
            'GET' => 'get',
            'POST' => 'post',
            'PUT' => 'put',
            'PATCH' => 'patch',
            'DELETE' => 'delete',
            default => throw new \InvalidArgumentException("Unsupported verb: {$verb}"),
        };
    }
}
