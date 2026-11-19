<?php

declare(strict_types=1);

namespace JustinHoltWeb\Dipstick\Scan;

/**
 * Turns a Blade template into PHP that can be parsed, without compiling it.
 *
 * Compiling would run every Blade directive handler that plugins registered,
 * so instead the PHP that Blade passes through is lifted out as it stands:
 * `@php … @endphp` blocks, `@php(…)`, `<?php … ?>` and `{{ }}` / `{!! !!}`
 * echoes. Each piece keeps its line number.
 */
final class BladeExtractor
{
    public static function toPhp(string $blade): string
    {
        // Blade comments can hold anything, including code that isn't there.
        $blade = (string) preg_replace_callback('/\{\{--.*?--\}\}/s', fn (array $m) => str_repeat("\n", substr_count($m[0], "\n")), $blade);

        $pattern = '/@php\s*\((?<inline>(?:[^()]++|\((?&inline)\))*)\)|@php\b(?<block>.*?)@endphp|<\?(?:php|=)(?<raw>.*?)(?:\?>|$)|(?<!@)\{!!(?<unescaped>.*?)!!\}|(?<!@)\{\{(?<echo>.*?)\}\}/s';
        preg_match_all($pattern, $blade, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL);

        $php = '<?php ';
        $line = 1;

        foreach ($matches as $match) {
            foreach (['inline', 'block', 'raw', 'unescaped', 'echo'] as $group) {
                if (! isset($match[$group]) || $match[$group][0] === null) {
                    continue;
                }

                [$code, $offset] = $match[$group];
                $leading = substr_count(substr($code, 0, strlen($code) - strlen(ltrim($code))), "\n");
                $target = substr_count($blade, "\n", 0, max(0, $offset)) + 1 + $leading;
                $php .= str_repeat("\n", max(0, $target - $line));
                $line = max($line, $target);

                $code = trim($code);
                $line += substr_count($code, "\n");

                if (in_array($group, ['block', 'raw'], true)) {
                    // A block can end in a `//` comment, so the next piece starts on a fresh line.
                    $php .= $code.";\n";
                    $line++;
                } else {
                    $php .= '('.$code.'); ';
                }
                break;
            }
        }

        return $php;
    }
}
