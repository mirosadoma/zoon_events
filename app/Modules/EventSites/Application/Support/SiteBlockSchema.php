<?php

namespace App\Modules\EventSites\Application\Support;

use App\Modules\EventSites\Domain\SiteBlockType;

final class SiteBlockSchema
{
    /** Shared background option keys for any block. */
    public const BACKGROUND_OPTIONS = [
        'bg_type', 'bg_color', 'bg_color_end', 'bg_image', 'bg_overlay',
    ];

    /** Common layout options for content blocks. */
    public const LAYOUT_OPTIONS = ['width', 'content_align', 'max_width'];

    /** Extended visual style options (colors, typography, spacing, borders). */
    public const STYLE_OPTIONS = [
        'heading_color', 'text_color', 'accent_color', 'link_color',
        'heading_size', 'body_size', 'heading_weight',
        'padding_y', 'padding_x', 'margin_top', 'margin_bottom',
        'border_radius', 'border_width', 'border_color', 'border_style',
        'shadow', 'opacity', 'background', 'custom_class', 'custom_css',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function allowedOptions(): array
    {
        $bg = self::BACKGROUND_OPTIONS;
        $layout = self::LAYOUT_OPTIONS;
        $style = self::STYLE_OPTIONS;

        return [
            SiteBlockType::Header->value => array_merge([
                'style', 'sticky', 'show_cta', 'cta_href', 'mobile_menu', 'page_id',
                'logo_position', 'logo_size', 'logo_max_height', 'logo_max_height_unit', 'show_logo',
                'layout', 'grid_max_width', 'zone_gap', 'nav_gap', 'cta_placement', 'nav_align',
                'show_brand_text',
            ], $bg, $style),
            SiteBlockType::Hero->value => array_merge(['background_style', 'text_alignment', 'show_date', 'show_location', 'overlay_opacity', 'columns', 'gap', 'page_id'], $bg, $layout, $style),
            SiteBlockType::About->value => array_merge(['layout', 'show_image', 'columns', 'gap', 'page_id'], $bg, $layout, $style),
            SiteBlockType::Agenda->value => array_merge(['show_speakers', 'group_by_date', 'show_venue', 'columns', 'gap', 'page_id'], $bg, $layout, $style),
            SiteBlockType::Speakers->value => array_merge(['layout', 'show_bio', 'columns', 'gap', 'page_id'], $bg, $layout, $style),
            SiteBlockType::Venue->value => array_merge(['show_map', 'show_directions', 'columns', 'gap', 'page_id'], $bg, $layout, $style),
            SiteBlockType::Faq->value => array_merge(['layout', 'collapsible', 'columns', 'gap', 'page_id'], $bg, $layout, $style),
            SiteBlockType::Sponsors->value => array_merge(['layout', 'columns', 'show_names', 'gap', 'page_id'], $bg, $layout, $style),
            SiteBlockType::Gallery->value => array_merge(['layout', 'columns', 'lightbox', 'gap', 'page_id'], $bg, $layout, $style),
            SiteBlockType::Section->value => array_merge(['columns', 'gap', 'background', 'background_preset', 'padding', 'max_width', 'align', 'page_id', 'layout_preset', 'layout_mode', 'freeform_height', 'freeform_height_unit'], $bg, $layout, $style),
            SiteBlockType::MediaText->value => array_merge(['layout', 'image_ratio', 'reverse_mobile', 'page_id'], $bg, $layout, $style),
            SiteBlockType::ImageShowcase->value => array_merge([
                'display', 'columns', 'autoplay', 'autoplay_ms', 'show_arrows', 'show_dots',
                'arrows_style', 'arrows_color', 'dots_style', 'dots_color', 'dots_active_color',
                'pause_on_hover', 'loop', 'drag_to_slide', 'slide_height', 'slide_height_unit', 'image_fit', 'gap', 'page_id',
            ], $bg, $layout, $style),
            SiteBlockType::Form->value => array_merge(['submit_style', 'page_id'], $bg, $layout, $style),
            SiteBlockType::RegisterCta->value => array_merge(['style', 'show_countdown', 'show_availability', 'columns', 'gap', 'page_id'], $bg, $layout, $style),
            SiteBlockType::Footer->value => array_merge([
                'design', 'show_social', 'show_brand', 'show_copyright', 'show_logo',
                'columns', 'gap', 'page_id',
                'grid_cols', 'brand_span', 'brand_order', 'grid_max_width',
                'logo_position', 'logo_size', 'logo_max_height', 'logo_max_height_unit',
                'tagline_color', 'tagline_size',
                'copyright_color', 'copyright_size',
                'column_title_color', 'column_title_size',
                'footer_link_size', 'social_color', 'social_size',
            ], $bg, $style),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function allowedRefs(): array
    {
        return [
            SiteBlockType::Header->value => ['logo', 'logo_url', 'logo_path'],
            SiteBlockType::Hero->value => ['background_image'],
            SiteBlockType::About->value => ['image'],
            SiteBlockType::Agenda->value => [],
            SiteBlockType::Speakers->value => [],
            SiteBlockType::Venue->value => [],
            SiteBlockType::Faq->value => [],
            SiteBlockType::Sponsors->value => ['logos'],
            SiteBlockType::Gallery->value => ['images'],
            SiteBlockType::Section->value => [],
            SiteBlockType::MediaText->value => ['image'],
            SiteBlockType::ImageShowcase->value => [],
            SiteBlockType::Form->value => [],
            SiteBlockType::RegisterCta->value => [],
            SiteBlockType::Footer->value => ['logo', 'logo_url', 'logo_path'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function contentKeys(): array
    {
        return [
            SiteBlockType::Header->value => ['brand', 'links', 'cta_label'],
            SiteBlockType::Hero->value => ['title', 'subtitle'],
            SiteBlockType::About->value => ['title', 'body'],
            SiteBlockType::Agenda->value => ['title'],
            SiteBlockType::Speakers->value => ['title'],
            SiteBlockType::Venue->value => ['title', 'description'],
            SiteBlockType::Faq->value => ['title', 'items'],
            SiteBlockType::Sponsors->value => ['title'],
            SiteBlockType::Gallery->value => ['title'],
            SiteBlockType::Section->value => ['title', 'subtitle', 'elements'],
            SiteBlockType::MediaText->value => ['title', 'body', 'button_label', 'button_href'],
            SiteBlockType::ImageShowcase->value => ['title', 'subtitle', 'items'],
            SiteBlockType::Form->value => ['title', 'description', 'submit_label', 'success_message', 'fields'],
            SiteBlockType::RegisterCta->value => ['title', 'button_text'],
            SiteBlockType::Footer->value => ['tagline', 'columns', 'copyright', 'social_links'],
        ];
    }

    /** @return list<string> */
    public static function sectionElementKinds(): array
    {
        return ['heading', 'text', 'image', 'button', 'card', 'spacer', 'divider', 'quote', 'video', 'list', 'icon', 'html'];
    }

    public static function optionKeysFor(string $type): array
    {
        return self::allowedOptions()[$type] ?? self::BACKGROUND_OPTIONS;
    }

    public static function refKeysFor(string $type): array
    {
        return self::allowedRefs()[$type] ?? [];
    }
}
