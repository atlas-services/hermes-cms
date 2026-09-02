<?php

declare(strict_types=1);

namespace App\Config;

final class ConfigDefinitionRegistry
{
    private const COLS = [
        '1/12' => '1',
        '2/12' => '2',
        '3/12' => '3',
        '4/12' => '4',
        '5/12' => '5',
        '6/12' => '6',
        '7/12' => '7',
        '8/12' => '8',
        '9/12' => '9',
        '10/12' => '10',
        '11/12' => '11',
        '12/12' => '12',
    ];

    private const COLS_OFFSET = [
        '0/12' => '0',
        '1/12' => '1',
        '2/12' => '2',
        '3/12' => '3',
        '4/12' => '4',
        '5/12' => '5',
        '6/12' => '6',
        '7/12' => '7',
        '8/12' => '8',
        '9/12' => '9',
        '10/12' => '10',
        '11/12' => '11',
        '12/12' => '12',
    ];

    private const CHEVRON_PERCENT = [
        '1 %' => '1',
        '2 %' => '2',
        '3 %' => '3',
        '4 %' => '4',
        '5 %' => '5',
        '95 %' => '95',
        '96 %' => '96',
        '97 %' => '97',
        '98 %' => '98',
        '99 %' => '99',
        '100 %' => '100',
    ];

    private const DECIMAL = [
        '0.1' => '0.1',
        '0.2' => '0.2',
        '0.3' => '0.3',
        '0.4' => '0.4',
        '0.5' => '0.5',
        '0.6' => '0.6',
        '0.7' => '0.7',
        '0.8' => '0.8',
        '0.9' => '0.9',
        '1.0' => '1.0',
    ];

    private const MARGINS = [
        0 => 0,
        1 => 1,
        2 => 2,
        3 => 3,
        4 => 4,
        5 => 5,
    ];

    private const TEXT_SIZE = [
        'h1' => 'h1',
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
        'h6' => 'h6',
        'display-1' => 'display-1',
        'display-2' => 'display-2',
        'display-3' => 'display-3',
        'display-4' => 'display-4',
        'display-5' => 'display-5',
        'display-6' => 'display-6',
    ];

    private const ROBOTS = [
        'index, follow' => 'index, follow',
        'index, nofollow' => 'index, nofollow',
        'noindex, follow' => 'noindex, follow',
        'noindex, nofollow' => 'noindex, nofollow',
    ];

    private const FONT_FAMILY = [
        'Alfa Slab One' => 'Alfa Slab One',
        '\'Bai Jamjuree\', sans-serif' => '\'Bai Jamjuree\', sans-serif',
        '\'Bubblegum Sans\', cursive' => '\'Bubblegum Sans\', cursive',
        ' Comic Sans MS, Comic Sans, cursive' => ' Comic Sans MS, Comic Sans, cursive',
        'Cherry Bomb One' => 'Cherry Bomb One',
        'Cormorant Garamond' => '\'Cormorant Garamond\', Georgia, serif',
        '\'Fredoka\', sans-serif' => '\'Fredoka\', sans-serif',
        'Impact, fantasy' => 'Impact, fantasy',
        '\'Mali\', cursive' => '\'Mali\', cursive',
        '\'Oswald\',Helvetica,Arial,Lucida,sans-serif' => '\'Oswald\',Helvetica,Arial,Lucida,sans-serif',
        '\'Palatino Linotype\', \'Book Antiqua\', Palatino, serif' => ' \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif',
        '\'Sofia\', sans-serif' => '\'Sofia\', sans-serif',
        '\'Snowburst One\', sans-serif' => '\'Snowburst One\', sans-serif',
        '\'The Antiqua B\', Georgia, Droid-serif, serif' => '\'The Antiqua B\', Georgia, Droid-serif, serif',
        'Verdana' => 'Verdana',
    ];

    private const BTN_OUTLINE = [
        'btn-outline-primary' => 'btn-outline-primary',
        'btn-outline-secondary' => 'btn-outline-secondary',
        'btn-outline-success' => 'btn-outline-success',
        'btn-outline-danger' => 'btn-outline-danger',
        'btn-outline-warning' => 'btn-outline-warning',
        'btn-outline-info' => 'btn-outline-info',
        'btn-outline-light' => 'btn-outline-light',
        'btn-outline-dark' => 'btn-outline-dark',
        'btn-outline-link' => 'btn-outline-link',
        'btn-outline-white' => 'btn-outline-white',
    ];

    private const NAV_AOS = [
        'fade' => 'fade',
        'fade-up' => 'fade-up',
        'fade-down' => 'fade-down',
        'fade-left' => 'fade-left',
        'fade-right' => 'fade-right',
        'fade-up-right' => 'fade-up-right',
        'fade-up-left' => 'fade-up-left',
        'fade-down-right' => 'fade-down-right',
        'fade-down-left' => 'fade-down-left',
        'zoom-in' => 'zoom-in',
        'zoom-in-up' => 'zoom-in-up',
        'zoom-in-down' => 'zoom-in-down',
        'zoom-in-left' => 'zoom-in-left',
        'zoom-in-right' => 'zoom-in-right',
        'zoom-out' => 'zoom-out',
        'zoom-out-up' => 'zoom-out-up',
        'zoom-out-down' => 'zoom-out-down',
        'zoom-out-left' => 'zoom-out-left',
        'zoom-out-right' => 'zoom-out-right',
        'flip-left' => 'flip-left',
        'flip-right' => 'flip-right',
        'flip-up' => 'flip-up',
        'flip-down' => 'flip-down',
        'slide-up' => 'slide-up',
        'slide-down' => 'slide-down',
        'slide-left' => 'slide-left',
        'slide-right' => 'slide-right',
        'fade-zoom-in' => 'fade-zoom-in',
    ];

    private const BOOLEAN_CODES = [
        'affiche_admin_post',
        'affiche_search',
        'footer_affiche',
        'topbar_dismiss_once',
        'nav_left_open_on_load',
        'newsletter_active',
        'livredor_active',
    ];

    private const STATEFUL_BOOLEAN_CODES = [
        'nav_left_open_on_load',
        'topbar_dismiss_once',
    ];

    /** @return list<string> */
    public function booleanCodes(): array
    {
        return self::BOOLEAN_CODES;
    }

    /** @return list<string> */
    public function statefulBooleanCodes(): array
    {
        return self::STATEFUL_BOOLEAN_CODES;
    }

    public function definitionFor(string $code): ConfigDefinition
    {
        if (\in_array($code, self::BOOLEAN_CODES, true)) {
            return ConfigDefinition::boolean($code, \in_array($code, self::STATEFUL_BOOLEAN_CODES, true));
        }

        $choices = $this->choiceMap()[$code] ?? null;
        if ($choices !== null) {
            return ConfigDefinition::choice($code, $choices);
        }

        if ($code === 'color' || str_contains($code, 'color') || $code === 'booking_reservation_form') {
            return ConfigDefinition::color($code);
        }

        if ($code === 'width' || str_contains($code, 'width')) {
            return ConfigDefinition::width($code);
        }

        if ($code === 'font_family' || str_contains($code, 'font_family')) {
            return ConfigDefinition::fontFamily($code);
        }

        return ConfigDefinition::text($code);
    }

    /** @return array<string, array<string, mixed>> */
    private function choiceMap(): array
    {
        return [
            'robots' => self::ROBOTS,
            'nav_bar' => ['base' => 'base', 'left' => 'left', 'full' => 'full'],
            'nav_espacement' => self::MARGINS,
            'nav_sub_menu_mx' => self::MARGINS,
            'nav_sub_menu_mt' => self::MARGINS,
            'nav_link_py' => self::MARGINS,
            'nav_link_px' => self::MARGINS,
            'nav_link_rounded' => self::MARGINS,
            'contact_rounded_input' => self::MARGINS,
            'contact_rounded_message' => self::MARGINS,
            'contact_py_input' => self::MARGINS,
            'contact_my_input' => self::MARGINS,
            'booking_rounded_input' => self::MARGINS,
            'booking_py_input' => self::MARGINS,
            'booking_my_input' => self::MARGINS,
            'folio1_padding_x' => self::MARGINS,
            'folio1_padding_y' => self::MARGINS,
            'nav_link_border_bottom' => ['Aucune séparation' => ' ', 'border-bottom' => 'border-bottom'],
            'nav_menu_text_size' => self::TEXT_SIZE,
            'nav_sub_menu_text_size' => self::TEXT_SIZE,
            'chevron' => ['circle' => 'circle-', 'base' => ''],
            'chevron_opacity' => self::DECIMAL,
            'chevron_position' => self::CHEVRON_PERCENT,
            'chevron_right' => self::CHEVRON_PERCENT,
            'logo' => $this->pixelChoices(),
            'nav_height' => $this->pixelChoices(),
            'nav_offset' => self::COLS_OFFSET,
            'contact_bgcolor_btn' => self::BTN_OUTLINE,
            'newsletter_bgcolor_btn' => self::BTN_OUTLINE,
            'livredor_bgcolor_btn' => self::BTN_OUTLINE,
            'nav_offcanvas_position' => ['start' => 'start', 'end' => 'end', 'top' => 'top', 'bottom' => 'bottom'],
            'nav_offcanvas_pct' => $this->percentChoices(),
            'nav_data_aos_active' => ['actif' => '1', 'inactif' => '0'],
            'nav_data_aos' => self::NAV_AOS,
            'nav_data_aos_duration' => [500 => 500, 1000 => 1000, 1500 => 1500, 2000 => 2000, 2500 => 2500, 3000 => 3000, 3500 => 3500],
            'nav_locale_switcher_position' => ['Sous le menu' => 'below', 'À droite du menu' => 'inline'],
            'nav_toggler_side' => ['Gauche' => 'left', 'Droite' => 'right'],
        ];
    }

    /** @return array<string, string> */
    private function pixelChoices(): array
    {
        $choices = [];
        foreach (range(0, 500, 10) as $number) {
            $choices[$number . 'px'] = $number . 'px';
        }

        return $choices;
    }

    /** @return array<int, int> */
    private function percentChoices(): array
    {
        $choices = [];
        foreach (range(0, 100) as $number) {
            $choices[$number] = $number;
        }

        return $choices;
    }

    /** @return array<string, string> */
    public function widthChoices(): array
    {
        return self::COLS;
    }

    /** @return array<string, string> */
    public function fontFamilyChoices(): array
    {
        return self::FONT_FAMILY;
    }
}
