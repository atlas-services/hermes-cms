/**
 * Polices disponibles dans l’admin (ConfigDefinitionRegistry::FONT_FAMILY)
 * et dans CKEditor 5 (fontFamily). Tenir aligné avec src/Config/ConfigDefinitionRegistry.php.
 */
export const HERMES_FONT_FAMILIES = [
    'Alfa Slab One',
    '\'Bai Jamjuree\', sans-serif',
    '\'Bubblegum Sans\', cursive',
    ' Comic Sans MS, Comic Sans, cursive',
    'Cherry Bomb One',
    '\'Cormorant Garamond\', Georgia, serif',
    '\'Fredoka\', sans-serif',
    'Impact, fantasy',
    '\'Mali\', cursive',
    '\'Oswald\',Helvetica,Arial,Lucida,sans-serif',
    ' \'Palatino Linotype\', \'Book Antiqua\', Palatino, serif',
    '\'Sofia\', sans-serif',
    '\'Snowburst One\', sans-serif',
    '\'The Antiqua B\', Georgia, Droid-serif, serif',
    'Verdana',
];

/** @see https://ckeditor.com/docs/ckeditor5/latest/features/font.html */
export const CKEDITOR_FONT_FAMILY_OPTIONS = ['default', ...HERMES_FONT_FAMILIES];
