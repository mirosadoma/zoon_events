<?php

namespace App\Modules\EventSites\Application\Support;

use App\Modules\Events\Application\Support\PublicRegistrationUrlBuilder;
use App\Modules\Events\Infrastructure\Persistence\Models\Event;
use App\Modules\EventSites\Domain\SiteBlockType;
use Illuminate\Support\Str;

final readonly class SiteBlockDefaults
{
    public function __construct(
        private PublicRegistrationUrlBuilder $registrationUrls,
    ) {}

    /**
     * Generate default blocks seeded from event data.
     *
     * @return list<array<string, mixed>>
     */
    public function forEvent(Event $event): array
    {
        $blocks = [];
        $eventNameEn = $event->name_en ?: 'Event';
        $eventNameAr = $event->name_ar ?: $eventNameEn;

        $blocks[] = [
            'id' => $this->generateBlockId('header'),
            'type' => SiteBlockType::Header->value,
            'visible' => true,
            'content_en' => [
                'brand' => $eventNameEn,
                'links' => [
                    ['id' => 'l_about', 'label_en' => 'About', 'label_ar' => 'حول', 'href' => '#about'],
                    ['id' => 'l_agenda', 'label_en' => 'Agenda', 'label_ar' => 'الأجندة', 'href' => '#agenda'],
                    ['id' => 'l_venue', 'label_en' => 'Venue', 'label_ar' => 'الموقع', 'href' => '#venue'],
                ],
                'cta_label' => 'Register',
            ],
            'content_ar' => [
                'brand' => $eventNameAr,
                'links' => [
                    ['id' => 'l_about', 'label_en' => 'About', 'label_ar' => 'حول', 'href' => '#about'],
                    ['id' => 'l_agenda', 'label_en' => 'Agenda', 'label_ar' => 'الأجندة', 'href' => '#agenda'],
                    ['id' => 'l_venue', 'label_en' => 'Venue', 'label_ar' => 'الموقع', 'href' => '#venue'],
                ],
                'cta_label' => 'تسجيل',
            ],
            'options' => [
                'style' => 'solid',
                'sticky' => true,
                'show_cta' => true,
                'cta_href' => 'registration',
                'mobile_menu' => true,
            ],
            'refs' => [],
        ];

        $blocks[] = [
            'id' => $this->generateBlockId('hero'),
            'type' => SiteBlockType::Hero->value,
            'visible' => true,
            'content_en' => [
                'title' => $event->name_en ?: '',
                'subtitle' => $this->formatDateRange($event),
            ],
            'content_ar' => [
                'title' => $event->name_ar ?: $event->name_en ?: '',
                'subtitle' => $this->formatDateRange($event),
            ],
            'options' => [
                'background_style' => 'gradient',
                'text_alignment' => 'center',
                'show_date' => true,
                'show_location' => true,
            ],
            'refs' => $event->main_image_path
                ? ['background_image' => $event->main_image_path]
                : [],
        ];

        if ($event->description_en || $event->description_ar) {
            $blocks[] = [
                'id' => $this->generateBlockId('about'),
                'type' => SiteBlockType::About->value,
                'visible' => true,
                'content_en' => [
                    'title' => 'About the Event',
                    'body' => $event->description_en ?: '',
                ],
                'content_ar' => [
                    'title' => 'عن الحدث',
                    'body' => $event->description_ar ?: $event->description_en ?: '',
                ],
                'options' => ['layout' => 'centered'],
                'refs' => [],
            ];
        }

        $agendaCount = $event->agendaItems()->count();
        if ($agendaCount > 0) {
            $blocks[] = [
                'id' => $this->generateBlockId('agenda'),
                'type' => SiteBlockType::Agenda->value,
                'visible' => true,
                'content_en' => ['title' => 'Agenda'],
                'content_ar' => ['title' => 'جدول الأعمال'],
                'options' => [
                    'show_speakers' => true,
                    'group_by_date' => true,
                ],
                'refs' => [],
            ];
        }

        if ($event->location_name_en || $event->location_name_ar) {
            $blocks[] = [
                'id' => $this->generateBlockId('venue'),
                'type' => SiteBlockType::Venue->value,
                'visible' => true,
                'content_en' => [
                    'title' => 'Venue',
                    'description' => $this->buildVenueDescription($event, 'en'),
                ],
                'content_ar' => [
                    'title' => 'الموقع',
                    'description' => $this->buildVenueDescription($event, 'ar'),
                ],
                'options' => ['show_map' => false],
                'refs' => [],
            ];
        }

        $blocks[] = [
            'id' => $this->generateBlockId('section'),
            'type' => SiteBlockType::Section->value,
            'visible' => true,
            'content_en' => [
                'title' => 'Highlights',
                'subtitle' => 'What to expect',
                'elements' => [
                    [
                        'id' => 'e_1',
                        'kind' => 'card',
                        'col_span' => 4,
                        'title' => 'Speakers',
                        'body' => 'Learn from industry leaders.',
                    ],
                    [
                        'id' => 'e_2',
                        'kind' => 'card',
                        'col_span' => 4,
                        'title' => 'Networking',
                        'body' => 'Meet peers and partners.',
                    ],
                    [
                        'id' => 'e_3',
                        'kind' => 'card',
                        'col_span' => 4,
                        'title' => 'Workshops',
                        'body' => 'Hands-on sessions all day.',
                    ],
                ],
            ],
            'content_ar' => [
                'title' => 'أبرز المحاور',
                'subtitle' => 'ماذا تتوقع',
                'elements' => [
                    [
                        'id' => 'e_1',
                        'kind' => 'card',
                        'col_span' => 4,
                        'title' => 'المتحدثون',
                        'body' => 'تعلّم من قادة المجال.',
                    ],
                    [
                        'id' => 'e_2',
                        'kind' => 'card',
                        'col_span' => 4,
                        'title' => 'التواصل',
                        'body' => 'تعرف على الزملاء والشركاء.',
                    ],
                    [
                        'id' => 'e_3',
                        'kind' => 'card',
                        'col_span' => 4,
                        'title' => 'ورش العمل',
                        'body' => 'جلسات عملية طوال اليوم.',
                    ],
                ],
            ],
            'options' => [
                'columns' => 12,
                'gap' => 'md',
                'background' => 'muted',
                'padding' => 'lg',
                'max_width' => '6xl',
                'align' => 'center',
            ],
            'refs' => [],
        ];

        $blocks[] = [
            'id' => $this->generateBlockId('register_cta'),
            'type' => SiteBlockType::RegisterCta->value,
            'visible' => true,
            'content_en' => [
                'title' => 'Register Now',
                'button_text' => 'Register',
            ],
            'content_ar' => [
                'title' => 'سجّل الآن',
                'button_text' => 'تسجيل',
            ],
            'options' => [
                'style' => 'prominent',
                'show_countdown' => false,
            ],
            'refs' => [],
        ];

        $blocks[] = [
            'id' => $this->generateBlockId('footer'),
            'type' => SiteBlockType::Footer->value,
            'visible' => true,
            'content_en' => [
                'tagline' => $eventNameEn,
                'columns' => [
                    [
                        'id' => 'fc_1',
                        'title' => 'Explore',
                        'links' => [
                            ['id' => 'fl_1', 'label' => 'About', 'href' => '#about'],
                            ['id' => 'fl_2', 'label' => 'Agenda', 'href' => '#agenda'],
                        ],
                    ],
                    [
                        'id' => 'fc_2',
                        'title' => 'Attend',
                        'links' => [
                            ['id' => 'fl_3', 'label' => 'Register', 'href' => 'registration'],
                            ['id' => 'fl_4', 'label' => 'Venue', 'href' => '#venue'],
                        ],
                    ],
                ],
                'copyright' => '© '.date('Y').' '.$eventNameEn,
                'social_links' => [],
            ],
            'content_ar' => [
                'tagline' => $eventNameAr,
                'columns' => [
                    [
                        'id' => 'fc_1',
                        'title' => 'استكشف',
                        'links' => [
                            ['id' => 'fl_1', 'label' => 'حول', 'href' => '#about'],
                            ['id' => 'fl_2', 'label' => 'الأجندة', 'href' => '#agenda'],
                        ],
                    ],
                    [
                        'id' => 'fc_2',
                        'title' => 'احضر',
                        'links' => [
                            ['id' => 'fl_3', 'label' => 'تسجيل', 'href' => 'registration'],
                            ['id' => 'fl_4', 'label' => 'الموقع', 'href' => '#venue'],
                        ],
                    ],
                ],
                'copyright' => '© '.date('Y').' '.$eventNameAr,
                'social_links' => [],
            ],
            'options' => [
                'design' => 'columns',
                'show_social' => false,
                'show_brand' => true,
                'show_copyright' => true,
                'show_logo' => true,
                'columns' => 12,
                'gap' => 'md',
            ],
            'refs' => [],
        ];

        return $blocks;
    }

    private function generateBlockId(string $type): string
    {
        return 'b_'.$type.'_'.Str::random(8);
    }

    private function formatDateRange(Event $event): string
    {
        if ($event->start_at === null) {
            return '';
        }

        $start = $event->start_at->format('F j, Y');

        if ($event->end_at !== null && ! $event->start_at->isSameDay($event->end_at)) {
            $end = $event->end_at->format('F j, Y');

            return "{$start} - {$end}";
        }

        return $start;
    }

    private function buildVenueDescription(Event $event, string $locale): string
    {
        $name = $locale === 'ar'
            ? ($event->location_name_ar ?: $event->location_name_en)
            : ($event->location_name_en ?: $event->location_name_ar);

        $address = $locale === 'ar'
            ? ($event->location_address_ar ?: $event->location_address_en)
            : ($event->location_address_en ?: $event->location_address_ar);

        return trim($name.($address ? "\n".$address : ''));
    }
}
