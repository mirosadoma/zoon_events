<?php

namespace App\Modules\IdentityVerification\Providers;

use App\Modules\Audit\Application\Listeners\Phase5\IdentityAuditListener;
use App\Modules\IdentityVerification\Contracts\FaceCaptureAdapter;
use App\Modules\IdentityVerification\Contracts\GovernmentIdentityAdapter;
use App\Modules\IdentityVerification\Domain\Events\IdentityArtifactsPurged;
use App\Modules\IdentityVerification\Domain\Events\IdentityConsentCaptured;
use App\Modules\IdentityVerification\Domain\Events\IdentityConsentWithdrawn;
use App\Modules\IdentityVerification\Domain\Events\IdentityFaceCaptureSubmitted;
use App\Modules\IdentityVerification\Domain\Events\IdentityRequirementConfigured;
use App\Modules\IdentityVerification\Domain\Events\IdentityReviewApproved;
use App\Modules\IdentityVerification\Domain\Events\IdentityReviewRejected;
use App\Modules\IdentityVerification\Domain\Events\IdentitySensitiveDataDeleted;
use App\Modules\IdentityVerification\Domain\Events\IdentitySensitiveDataViewed;
use App\Modules\IdentityVerification\Domain\Events\IdentityVerificationResultRecorded;
use App\Modules\IdentityVerification\Domain\Events\IdentityVerificationStarted;
use App\Modules\IdentityVerification\Infrastructure\Adapters\MockFaceCaptureAdapter;
use App\Modules\IdentityVerification\Infrastructure\Adapters\MockGovernmentIdentityAdapter;
use App\Modules\IdentityVerification\Testing\FakeFaceCaptureAdapter;
use App\Modules\IdentityVerification\Testing\FakeGovernmentIdentityAdapter;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class IdentityVerificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FakeGovernmentIdentityAdapter::class);
        $this->app->singleton(FakeFaceCaptureAdapter::class);

        $this->app->bind(GovernmentIdentityAdapter::class, function ($app): GovernmentIdentityAdapter {
            return match (config('identity-verification.default_government_adapter', 'mock')) {
                'fake' => $app->make(FakeGovernmentIdentityAdapter::class),
                default => $app->make(MockGovernmentIdentityAdapter::class),
            };
        });

        $this->app->bind(FaceCaptureAdapter::class, function ($app): FaceCaptureAdapter {
            return match (config('identity-verification.default_face_adapter', 'mock')) {
                'fake' => $app->make(FakeFaceCaptureAdapter::class),
                default => $app->make(MockFaceCaptureAdapter::class),
            };
        });
    }

    public function boot(): void
    {
        $listener = IdentityAuditListener::class;
        Event::listen(IdentityRequirementConfigured::class, [$listener, 'handleRequirementConfigured']);
        Event::listen(IdentityConsentCaptured::class, [$listener, 'handleConsentCaptured']);
        Event::listen(IdentityConsentWithdrawn::class, [$listener, 'handleConsentWithdrawn']);
        Event::listen(IdentityVerificationStarted::class, [$listener, 'handleVerificationStarted']);
        Event::listen(IdentityVerificationResultRecorded::class, [$listener, 'handleVerificationResultRecorded']);
        Event::listen(IdentityFaceCaptureSubmitted::class, [$listener, 'handleFaceCaptureSubmitted']);
        Event::listen(IdentityReviewApproved::class, [$listener, 'handleReviewApproved']);
        Event::listen(IdentityReviewRejected::class, [$listener, 'handleReviewRejected']);
        Event::listen(IdentitySensitiveDataViewed::class, [$listener, 'handleSensitiveDataViewed']);
        Event::listen(IdentitySensitiveDataDeleted::class, [$listener, 'handleSensitiveDataDeleted']);
        Event::listen(IdentityArtifactsPurged::class, [$listener, 'handleArtifactsPurged']);
    }
}
