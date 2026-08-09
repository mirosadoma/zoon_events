<?php

namespace App\Modules\Ai\Domain;

enum AiPurpose: string
{
    case AssistantAnswer = 'assistant_answer';
    case InsightSummary = 'insight_summary';
    case InsightAnswer = 'insight_answer';
    case PlatformChat = 'platform_chat';
}
