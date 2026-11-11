<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Infrastructure\Text;

use Transliterator;

/**
 * Converts a human name to the URSSAF/DGFiP charset: ASCII letters, apostrophe, hyphen and single spaces.
 *
 * Accents and ligatures are transliterated, first with ext-intl ("Any-Latin; Latin-ASCII") when enabled, otherwise
 * with a built-in map for French and European characters. Any other character is removed, whitespace is collapsed,
 * and leading/trailing characters that are not letters are trimmed.
 */
final readonly class AsciiNameNormalizer
{
    private const INTL_RULE = 'Any-Latin; Latin-ASCII';

    /** @var array<string, string> */
    private const FALLBACK_MAP = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
        'œ' => 'oe', 'ß' => 'ss', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
        'þ' => 'th', 'ł' => 'l', 'đ' => 'd', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ž' => 'z', 'ź' => 'z',
        'ż' => 'z', 'ć' => 'c', 'č' => 'c', 'ń' => 'n', 'ň' => 'n', 'ř' => 'r', 'ě' => 'e', 'ů' => 'u',
        'ő' => 'o', 'ű' => 'u', 'ą' => 'a', 'ę' => 'e', 'ı' => 'i',
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ø' => 'O',
        'Œ' => 'OE', 'ẞ' => 'SS', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Ÿ' => 'Y',
        'Þ' => 'TH', 'Ł' => 'L', 'Đ' => 'D', 'Ś' => 'S', 'Š' => 'S', 'Ş' => 'S', 'Ž' => 'Z', 'Ź' => 'Z',
        'Ż' => 'Z', 'Ć' => 'C', 'Č' => 'C', 'Ń' => 'N', 'Ň' => 'N', 'Ř' => 'R', 'Ě' => 'E', 'Ů' => 'U',
        'Ő' => 'O', 'Ű' => 'U', 'Ą' => 'A', 'Ę' => 'E', 'İ' => 'I',
        '‘' => "'", '’' => "'", 'ʼ' => "'", '´' => "'", '`' => "'",
        '‐' => '-', '‑' => '-', '‒' => '-', '–' => '-', '—' => '-',
    ];

    private ?Transliterator $transliterator;

    /**
     * @param bool $useIntl use ext-intl transliteration (requires the intl extension); false uses the built-in map
     */
    public function __construct(bool $useIntl)
    {
        $this->transliterator = $useIntl ? Transliterator::create(self::INTL_RULE) : null;
    }

    /**
     * @return string letters A-Z, a-z, apostrophes, hyphens and single spaces (empty when nothing is left)
     */
    public function normalize(string $name): string
    {
        $transliterated = $this->transliterate($name);
        $spaced = preg_replace('/\s+/', ' ', $transliterated) ?? '';
        $allowed = preg_replace("/[^A-Za-z '\\-]+/", '', $spaced) ?? '';
        $single = preg_replace('/ {2,}/', ' ', $allowed) ?? '';

        return preg_replace('/^[^A-Za-z]+|[^A-Za-z]+$/', '', $single) ?? '';
    }

    private function transliterate(string $name): string
    {
        if (!$this->transliterator instanceof Transliterator) {
            return strtr($name, self::FALLBACK_MAP);
        }

        $result = $this->transliterator->transliterate($name);

        return $result === false ? strtr($name, self::FALLBACK_MAP) : $result;
    }
}
