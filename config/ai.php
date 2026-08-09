<?php

return [
    'default' => env('AI_DEFAULT_ADAPTER', 'fake'),
    'embedding_default' => env('AI_EMBEDDING_ADAPTER', 'fake'),
    'allow_network' => (bool) env('AI_ALLOW_NETWORK', false),
    'timeout_ms' => (int) env('AI_TIMEOUT_MS', 15000),
    'max_context_chars' => (int) env('AI_MAX_CONTEXT_CHARS', 6000),
    'max_output_tokens' => (int) env('AI_MAX_OUTPUT_TOKENS', 600),
    'max_request_bytes' => (int) env('AI_MAX_REQUEST_BYTES', 65536),

    'assistant' => [
        'visitor_questions_per_minute' => (int) env('AI_VISITOR_QPM', 6),
        'event_questions_per_day' => (int) env('AI_EVENT_QPD', 500),
        'retrieval_candidates' => (int) env('AI_RETRIEVAL_CANDIDATES', 50),
        'retrieval_top_k' => (int) env('AI_RETRIEVAL_TOP_K', 5),
        'max_question_chars' => (int) env('AI_MAX_QUESTION_CHARS', 1000),
        'transcript_retention_days' => (int) env('AI_TRANSCRIPT_RETENTION_DAYS', 90),
    ],

    'chat' => [
        'history_limit' => (int) env('AI_CHAT_HISTORY_LIMIT', 12),
        'rag_event_limit' => (int) env('AI_CHAT_RAG_EVENT_LIMIT', 10),
        'cache_seconds' => (int) env('AI_CHAT_CACHE_SECONDS', 60),
    ],

    'insights' => [
        'cache_minutes' => (int) env('AI_INSIGHT_CACHE_MINUTES', 15),
        'min_bucket_size' => (int) env('AI_MIN_BUCKET_SIZE', 5),
    ],

    'site' => [
        'max_blocks' => (int) env('AI_SITE_MAX_BLOCKS', 40),
        'max_block_chars' => (int) env('AI_SITE_MAX_BLOCK_CHARS', 20000),
    ],

    'hosted' => [
        'api_url' => env('AI_HOSTED_API_URL'),
        'model' => env('AI_HOSTED_MODEL'),
        'embedding_model' => env('AI_HOSTED_EMBEDDING_MODEL'),
        'secret_reference' => env('AI_HOSTED_SECRET_REFERENCE'),
    ],

    'self_hosted' => [
        'api_url' => env('AI_SELF_HOSTED_API_URL'),
        'model' => env('AI_SELF_HOSTED_MODEL'),
        'embedding_model' => env('AI_SELF_HOSTED_EMBEDDING_MODEL'),
        'secret_reference' => env('AI_SELF_HOSTED_SECRET_REFERENCE'),
    ],
];
