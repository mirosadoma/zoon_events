<?php

namespace App\Modules\Ai\Providers;

use App\Modules\Ai\Application\Actions\RebuildEventIndex;
use App\Modules\Ai\Application\AiProviderRegistry;
use App\Modules\Ai\Application\Commands\PurgeAssistantTranscripts;
use App\Modules\Ai\Application\Retrieval\CosineReranker;
use App\Modules\Ai\Application\Retrieval\DatabaseKnowledgeRetriever;
use App\Modules\Ai\Application\Retrieval\KnowledgeChunker;
use App\Modules\Ai\Application\Retrieval\LexicalCandidateFinder;
use App\Modules\Ai\Application\Retrieval\Sources\AgendaSpeakersSource;
use App\Modules\Ai\Application\Retrieval\Sources\EventCoreSource;
use App\Modules\Ai\Application\Retrieval\Sources\OrganizerFaqSource;
use App\Modules\Ai\Application\Retrieval\Sources\PublishedSiteSource;
use App\Modules\Ai\Application\Retrieval\Sources\RegistrationFactsSource;
use App\Modules\Ai\Application\Retrieval\Sources\VenueZonesSource;
use App\Modules\Ai\Application\Support\AiProviderStatus;
use App\Modules\Ai\Application\Support\FunctionCallingChatService;
use App\Modules\Ai\Application\Support\OrganizerFaqMatcher;
use App\Modules\Ai\Contracts\AiSecretLoader;
use App\Modules\Ai\Contracts\EmbeddingProvider;
use App\Modules\Ai\Contracts\KnowledgeRetriever;
use App\Modules\Ai\Contracts\LlmProvider;
use App\Modules\Ai\Infrastructure\Adapters\Hosted\HostedEmbeddingProvider;
use App\Modules\Ai\Infrastructure\Adapters\Hosted\HostedLlmProvider;
use App\Modules\Ai\Infrastructure\Adapters\SelfHosted\SelfHostedEmbeddingProvider;
use App\Modules\Ai\Infrastructure\Adapters\SelfHosted\SelfHostedLlmProvider;
use App\Modules\Ai\Infrastructure\Secrets\EnvironmentAiSecretLoader;
use App\Modules\Ai\Testing\FakeEmbeddingProvider;
use App\Modules\Ai\Testing\FakeLlmProvider;
use Illuminate\Support\ServiceProvider;

final class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiSecretLoader::class, EnvironmentAiSecretLoader::class);

        $this->app->singleton(FakeLlmProvider::class);
        $this->app->singleton(FakeEmbeddingProvider::class);
        $this->app->singleton(HostedLlmProvider::class);
        $this->app->singleton(HostedEmbeddingProvider::class);
        $this->app->singleton(SelfHostedLlmProvider::class);
        $this->app->singleton(SelfHostedEmbeddingProvider::class);

        $this->app->singleton(AiProviderRegistry::class, fn ($app) => new AiProviderRegistry(
            llmProviders: [
                'fake' => $app->make(FakeLlmProvider::class),
                'hosted' => $app->make(HostedLlmProvider::class),
                'self_hosted' => $app->make(SelfHostedLlmProvider::class),
            ],
            embeddingProviders: [
                'fake' => $app->make(FakeEmbeddingProvider::class),
                'hosted' => $app->make(HostedEmbeddingProvider::class),
                'self_hosted' => $app->make(SelfHostedEmbeddingProvider::class),
            ],
        ));

        $this->app->bind(
            LlmProvider::class,
            fn ($app) => $app->make(AiProviderRegistry::class)->getLlm((string) config('ai.default', 'fake')),
        );

        $this->app->bind(
            EmbeddingProvider::class,
            fn ($app) => $app->make(AiProviderRegistry::class)->getEmbedding((string) config('ai.embedding_default', 'fake')),
        );

        $this->app->singleton(KnowledgeChunker::class);
        $this->app->singleton(LexicalCandidateFinder::class);
        $this->app->singleton(CosineReranker::class);
        $this->app->singleton(OrganizerFaqMatcher::class);
        $this->app->singleton(AiProviderStatus::class);
        $this->app->bind(KnowledgeRetriever::class, DatabaseKnowledgeRetriever::class);

        $this->app->singleton(FunctionCallingChatService::class, function ($app) {
            $llm = $app->make(LlmProvider::class);
            $client = $llm instanceof HostedLlmProvider ? $llm : (
                $app->make(AiProviderRegistry::class)->getLlm('hosted') instanceof HostedLlmProvider
                    ? $app->make(AiProviderRegistry::class)->getLlm('hosted')
                    : null
            );

            return new FunctionCallingChatService(
                llmProvider: $llm,
                networkClient: $client,
                secretLoader: $app->make(AiSecretLoader::class),
            );
        });

        $this->app->singleton(RebuildEventIndex::class, function ($app) {
            $action = new RebuildEventIndex($app->make(EmbeddingProvider::class));

            $action->addSource($app->make(PublishedSiteSource::class));
            $action->addSource($app->make(EventCoreSource::class));
            $action->addSource($app->make(AgendaSpeakersSource::class));
            $action->addSource($app->make(VenueZonesSource::class));
            $action->addSource($app->make(RegistrationFactsSource::class));
            $action->addSource($app->make(OrganizerFaqSource::class));

            return $action;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PurgeAssistantTranscripts::class,
            ]);
        }
    }
}
