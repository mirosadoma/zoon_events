<?php

namespace Tests\Unit\BadgePrinting;

use App\Modules\BadgePrinting\Application\Actions\PreviewBadgeTemplateWithTestDataAction;
use App\Modules\BadgePrinting\Infrastructure\Persistence\Models\BadgeTemplate;
use App\Modules\Events\Infrastructure\Persistence\Models\EventBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesPhase1RegistrationFixture;
use Tests\TestCase;

#[Group('badge-printing')]
#[Group('phase-3')]
final class PreviewBadgeTemplateWithTestDataActionTest extends TestCase
{
    use CreatesPhase1RegistrationFixture;
    use RefreshDatabase;

    public function test_resolves_organizer_logo_from_event_branding_when_field_values_omit_it(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required.');
        }

        Storage::fake('public');

        $logo = imagecreatetruecolor(20, 20);
        self::assertNotFalse($logo);
        $red = imagecolorallocate($logo, 220, 20, 60);
        imagefilledrectangle($logo, 0, 0, 19, 19, $red);
        ob_start();
        imagepng($logo);
        $logoBytes = (string) ob_get_clean();
        imagedestroy($logo);

        Storage::disk('public')->put('badges/organizer-preview.png', $logoBytes);

        $fixture = $this->createRegistrationFixture();
        $event = $fixture['event'];
        $tenant = $fixture['tenant'];

        $branding = EventBranding::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_id', $event->id)
            ->firstOrFail();

        $branding->forceFill([
            'theme_config' => [
                'logo_path' => 'badges/organizer-preview.png',
            ],
        ])->save();

        $template = new BadgeTemplate([
            'tenant_id' => $tenant->id,
            'event_id' => $event->id,
            'name' => 'Logo Preview',
            'paper_size' => 'custom',
            'printer_type' => 'thermal',
            'background_color' => '#ffffff',
            'canvas_width' => 100,
            'canvas_height' => 100,
            'layout' => [
                [
                    'field' => 'organizer_logo_ref',
                    'x' => 20,
                    'y' => 20,
                    'width' => 60,
                    'height' => 60,
                ],
            ],
        ]);

        $preview = app(PreviewBadgeTemplateWithTestDataAction::class)->execute($template, [], null);

        self::assertNotNull($preview);
        self::assertSame('image/png', $preview['mime']);
        self::assertNotSame('', $preview['png_base64']);

        $image = imagecreatefromstring((string) base64_decode($preview['png_base64'], true));
        self::assertNotFalse($image);

        $pixel = imagecolorat($image, 50, 50);
        self::assertSame(220, ($pixel >> 16) & 0xFF);
        self::assertSame(20, ($pixel >> 8) & 0xFF);
        self::assertSame(60, $pixel & 0xFF);

        imagedestroy($image);
    }
}
