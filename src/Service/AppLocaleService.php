<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\MenuRepository;
use App\Repository\PostRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Languages;

/**
 * Langues du site (contenu menus/posts) et libellés admin.
 */
final class AppLocaleService
{
    /** @var array<string, string> code langue ISO 639-1 → code pays ISO 3166-1 alpha-2 */
    private const LOCALE_TO_COUNTRY = [
        'fr' => 'FR',
        'en' => 'GB',
        'de' => 'DE',
        'es' => 'ES',
        'it' => 'IT',
        'pt' => 'PT',
        'nl' => 'NL',
        'pl' => 'PL',
        'ru' => 'RU',
        'ja' => 'JP',
        'zh' => 'CN',
        'ar' => 'SA',
        'cs' => 'CZ',
        'da' => 'DK',
        'fi' => 'FI',
        'el' => 'GR',
        'hu' => 'HU',
        'ko' => 'KR',
        'nb' => 'NO',
        'sv' => 'SE',
        'tr' => 'TR',
        'uk' => 'UA',
        'vi' => 'VN',
        'ro' => 'RO',
        'sk' => 'SK',
        'sl' => 'SI',
        'bg' => 'BG',
        'hr' => 'HR',
        'sr' => 'RS',
        'lt' => 'LT',
        'lv' => 'LV',
        'et' => 'EE',
        'id' => 'ID',
        'ms' => 'MY',
        'th' => 'TH',
        'he' => 'IL',
        'hi' => 'IN',
        'fa' => 'IR',
        'sq' => 'AL',
        'ca' => 'ES',
    ];

    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly PostRepository $postRepository,
        #[Autowire(param: 'app.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    public function getDefaultLocale(): string
    {
        return $this->normalize($this->defaultLocale);
    }

    public function formatLabel(string $locale): string
    {
        $code = strtoupper($this->normalize($locale));

        return sprintf('%s (%s)', $this->resolveCountryOrLanguageName($locale), $code);
    }

    /**
     * Langues ayant déjà du contenu (menus et/ou posts).
     *
     * @return list<string>
     */
    public function getContentLocales(): array
    {
        $locales = array_merge(
            [$this->getDefaultLocale()],
            $this->menuRepository->findDistinctLocales(),
            $this->postRepository->findDistinctLocales(),
        );

        $normalized = [];
        foreach ($locales as $locale) {
            $code = $this->normalize($locale);
            if ($code !== '') {
                $normalized[$code] = true;
            }
        }

        $list = array_keys($normalized);
        usort($list, static fn (string $a, string $b): int => strcmp($a, $b));

        $default = $this->getDefaultLocale();
        if (isset($normalized[$default])) {
            $list = array_values(array_filter($list, static fn (string $l): bool => $l !== $default));
            array_unshift($list, $default);
        }

        return $list;
    }

    /**
     * @return array<string, string> libellé => code
     */
    public function getAdminFilterChoices(): array
    {
        $choices = [];
        foreach ($this->getContentLocales() as $locale) {
            $choices[$this->formatLabel($locale)] = $locale;
        }

        return $choices;
    }

    /**
     * Langues Symfony Intl encore disponibles pour une copie (hors langues déjà utilisées).
     *
     * @return array<string, string> libellé => code
     */
    public function getAvailableTargetLocaleChoices(?string $sourceLocale = null): array
    {
        $used = array_flip($this->getContentLocales());
        $sourceLocale = $sourceLocale !== null ? $this->normalize($sourceLocale) : null;
        $displayLocale = $this->getDefaultLocale();
        $choices = [];

        foreach (Languages::getLanguageCodes() as $code) {
            $code = $this->normalize($code);
            if (isset($used[$code])) {
                continue;
            }
            if ($sourceLocale !== null && $code === $sourceLocale) {
                continue;
            }

            $choices[$this->formatLabel($code)] = $code;
        }

        ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);

        return $choices;
    }

    private function resolveCountryOrLanguageName(string $locale): string
    {
        $locale = $this->normalize($locale);
        $displayLocale = $this->getDefaultLocale();
        $countryCode = $this->resolveCountryCode($locale);

        if ($countryCode !== null) {
            try {
                return Countries::getName($countryCode, $displayLocale);
            } catch (\Throwable) {
            }
        }

        try {
            return Languages::getName($locale, $displayLocale);
        } catch (\Throwable) {
            return strtoupper($locale);
        }
    }

    private function resolveCountryCode(string $locale): ?string
    {
        if (isset(self::LOCALE_TO_COUNTRY[$locale])) {
            return self::LOCALE_TO_COUNTRY[$locale];
        }

        $upper = strtoupper($locale);
        if (\strlen($locale) === 2 && Countries::exists($upper)) {
            return $upper;
        }

        return null;
    }

    public function isValidLanguageCode(string $locale): bool
    {
        $code = $this->normalize($locale);

        return $code !== '' && Languages::exists($code);
    }

    public function resolveAdminFilterLocale(string $requested): string
    {
        $requested = $this->normalize($requested);
        $locales = $this->getContentLocales();

        if ($requested !== '' && \in_array($requested, $locales, true)) {
            return $requested;
        }

        $default = $this->getDefaultLocale();
        if (\in_array($default, $locales, true)) {
            return $default;
        }

        return $locales[0] ?? $default;
    }

    public function resolveAdminFilterLocaleFromRequest(Request $request): string
    {
        return $this->resolveAdminFilterLocale($request->query->getString('menu_locale', ''));
    }

    public function normalize(string $locale): string
    {
        return strtolower(trim($locale));
    }
}
