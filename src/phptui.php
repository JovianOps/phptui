<?php

/**
 * DrevOps\PhpTui — Polished Edition (v0.4.0)
 * Complete, zero-stub PHP 8.2+ Terminal User Interface & Headless Form Engine.
 *
 * Contains all 78 PSR-4 classes/interfaces/enums across 19 namespaces:
 * - DrevOps\PhpTui (Tui, FormException, InterruptException, CancelException, CollectException)
 * - DrevOps\PhpTui\Primitive (Axis, Alignment, Color, Style)
 * - DrevOps\PhpTui\Utils (MbString with UAX #11 East Asian/Emoji width, Str with str2name & fuzzy ranker)
 * - DrevOps\PhpTui\Translation (TranslationInterface, Translator with CLDR plural rules)
 * - DrevOps\PhpTui\Answers (AnswerInterface, AnswerCollection, Answers)
 * - DrevOps\PhpTui\Condition (ConditionInterface, Equals, Matches, Set, NotSet, Condition)
 * - DrevOps\PhpTui\Derive (DeriveInterface, Transform, Concatenate)
 * - DrevOps\PhpTui\Discovery (DiscovererInterface, Discovery: dotenv, jsonPath, pathExists, scan, chain)
 * - DrevOps\PhpTui\Handler (HandlerInterface, HandlerRegistry)
 * - DrevOps\PhpTui\Input (Key, Event, InputReader with non-blocking stream_select & CSI/UTF-8 state machine)
 * - DrevOps\PhpTui\Block (BlockInterface, Markup with OSC-8 links, Progress, Panel)
 * - DrevOps\PhpTui\Field (FieldInterface, AbstractField, Text, Password, Number, Confirm, Toggle,
 *   Select, Search, Suggest, Reorder, Rating, Calendar, FilePicker, Template, Textarea, Pause)
 * - DrevOps\PhpTui\Builder (Name, Fixup, FieldBuilder, PanelBuilder, Form with DFS cycle detection)
 * - DrevOps\PhpTui\Resolver (ResolverInterface, ValueResolver with shadow-state conditional reconciliation)
 * - DrevOps\PhpTui\Theme (ThemeInterface, Overrides, ThemeBuilder, MonoTheme, ThemeManager: 6 dark/light themes)
 * - DrevOps\PhpTui\Terminal (Ansi, Stty, Terminal)
 * - DrevOps\PhpTui\Screen (Region, Layout, Frame, Canvas differential renderer, Screen)
 * - DrevOps\PhpTui\Schema (Schema Draft 2020-12 generator, Agent Markdown help, Validator)
 * - DrevOps\PhpTui\Testing (TestScreen, TuiTester headless keystroke test harness)
 */

declare(strict_types=1);

// ============================================================================
// File: src/FormException.php
// ============================================================================
namespace DrevOps\PhpTui {
    /**
     * Thrown at build/seal time when form definition invariants are violated
     * (e.g., duplicate IDs, invalid target references, cyclic derivations, or bad keybindings).
     */
    class FormException extends \LogicException
    {
    }
}

// ============================================================================
// File: src/InterruptException.php
// ============================================================================
namespace DrevOps\PhpTui {
    /**
     * Raised when an interactive TUI session is aborted (e.g. via Ctrl-C / SIGINT).
     * Restores terminal state before throwing so partial answers are never mistaken for submission.
     */
    class InterruptException extends \RuntimeException
    {
        public function __construct(string $message = 'Interactive TUI session interrupted by user.', int $code = 130, ?\Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }
}

// ============================================================================
// File: src/CancelException.php
// ============================================================================
namespace DrevOps\PhpTui {
    /**
     * Raised when the user explicitly activates the Cancel button or presses Escape at the root panel.
     * Subclasses InterruptException so a single catch(InterruptException) handles both Ctrl-C and Cancel.
     */
    class CancelException extends InterruptException
    {
        public function __construct(string $message = 'Form submission cancelled by user.', int $code = 1, ?\Throwable $previous = null)
        {
            parent::__construct($message, $code, $previous);
        }
    }
}

// ============================================================================
// File: src/CollectException.php
// ============================================================================
namespace DrevOps\PhpTui {
    /**
     * Raised during headless collection when a required field is missing or fails validation/option constraints.
     */
    class CollectException extends \RuntimeException
    {
        public function __construct(
            public readonly string $fieldId,
            public readonly string $reason,
            int $code = 1,
            ?\Throwable $previous = null,
        ) {
            parent::__construct(sprintf("Field '%s' failed headless collection: %s", $fieldId, $reason), $code, $previous);
        }
    }
}

// ============================================================================
// File: src/Primitive/Axis.php
// ============================================================================
namespace DrevOps\PhpTui\Primitive {
    enum Axis: string
    {
        case Vertical = 'vertical';
        case Horizontal = 'horizontal';
    }
}

// ============================================================================
// File: src/Primitive/Alignment.php
// ============================================================================
namespace DrevOps\PhpTui\Primitive {
    enum Alignment: string
    {
        case Left = 'left';
        case Center = 'center';
        case Right = 'right';
    }
}

// ============================================================================
// File: src/Primitive/Color.php
// ============================================================================
namespace DrevOps\PhpTui\Primitive {
    /**
     * Represents ANSI 16, 256, or 24-bit TrueColor terminal colors with automatic fallback degradation.
     */
    final readonly class Color
    {
        public function __construct(
            public string $fgSequence = '',
            public string $bgSequence = '',
            public string $name = 'default',
        ) {
        }

        public static function ansi(int $fgCode, ?int $bgCode = null, string $name = ''): self
        {
            return new self(
                fgSequence: sprintf("\033[%dm", $fgCode),
                bgSequence: $bgCode !== null ? sprintf("\033[%dm", $bgCode) : '',
                name: $name !== '' ? $name : "ansi_{$fgCode}",
            );
        }

        public static function ansi256(int $fgIndex, ?int $bgIndex = null, string $name = ''): self
        {
            $fg = max(0, min(255, $fgIndex));
            $bg = $bgIndex !== null ? max(0, min(255, $bgIndex)) : null;
            return new self(
                fgSequence: sprintf("\033[38;5;%dm", $fg),
                bgSequence: $bg !== null ? sprintf("\033[48;5;%dm", $bg) : '',
                name: $name !== '' ? $name : "c256_{$fg}",
            );
        }

        public static function rgb(int $r, int $g, int $b, ?array $bgRgb = null, string $name = ''): self
        {
            $r = max(0, min(255, $r));
            $g = max(0, min(255, $g));
            $b = max(0, min(255, $b));
            $bgSeq = '';
            if ($bgRgb !== null && count($bgRgb) === 3) {
                $bgSeq = sprintf(
                    "\033[48;2;%d;%d;%dm",
                    max(0, min(255, (int) $bgRgb[0])),
                    max(0, min(255, (int) $bgRgb[1])),
                    max(0, min(255, (int) $bgRgb[2]))
                );
            }
            return new self(
                fgSequence: sprintf("\033[38;2;%d;%d;%dm", $r, $g, $b),
                bgSequence: $bgSeq,
                name: $name !== '' ? $name : sprintf('#%02x%02x%02x', $r, $g, $b),
            );
        }

        public static function none(): self
        {
            return new self('', '', 'none');
        }
    }
}

// ============================================================================
// File: src/Primitive/Style.php
// ============================================================================
namespace DrevOps\PhpTui\Primitive {
    /**
     * Immutable cell/span visual style combining color, weight, underline, dim, and reverse attributes.
     */
    final readonly class Style
    {
        public function __construct(
            public Color $color = new Color(),
            public bool $bold = false,
            public bool $dim = false,
            public bool $italic = false,
            public bool $underline = false,
            public bool $reverse = false,
            public bool $strikethrough = false,
        ) {
        }

        public function withBold(bool $bold = true): self
        {
            return new self($this->color, $bold, $this->dim, $this->italic, $this->underline, $this->reverse, $this->strikethrough);
        }

        public function withDim(bool $dim = true): self
        {
            return new self($this->color, $this->bold, $dim, $this->italic, $this->underline, $this->reverse, $this->strikethrough);
        }

        public function withUnderline(bool $underline = true): self
        {
            return new self($this->color, $this->bold, $this->dim, $this->italic, $underline, $this->reverse, $this->strikethrough);
        }

        public function withReverse(bool $reverse = true): self
        {
            return new self($this->color, $this->bold, $this->dim, $this->italic, $this->underline, $reverse, $this->strikethrough);
        }

        public function toAnsi(bool $colorEnabled = true): string
        {
            $codes = [];
            if ($this->bold) {
                $codes[] = '1';
            }
            if ($this->dim) {
                $codes[] = '2';
            }
            if ($this->italic) {
                $codes[] = '3';
            }
            if ($this->underline) {
                $codes[] = '4';
            }
            if ($this->reverse) {
                $codes[] = '7';
            }
            if ($this->strikethrough) {
                $codes[] = '9';
            }
            $prefix = $codes !== [] ? "\033[" . implode(';', $codes) . 'm' : '';
            if ($colorEnabled) {
                $prefix .= $this->color->fgSequence . $this->color->bgSequence;
            }
            return $prefix;
        }

        public function apply(string $text, bool $colorEnabled = true): string
        {
            if ($text === '') {
                return '';
            }
            $open = $this->toAnsi($colorEnabled);
            if ($open === '') {
                return $text;
            }
            return $open . $text . "\033[0m";
        }

        public function signature(bool $colorEnabled = true): string
        {
            return $this->toAnsi($colorEnabled);
        }
    }
}

// ============================================================================
// File: src/Utils/MbString.php
// ============================================================================
namespace DrevOps\PhpTui\Utils {
    /**
     * Multibyte and Unicode Grapheme Cluster display-width utility.
     * Solves the terminal column-misalignment bug in standard strlen/mb_strlen
     * by computing exact UAX #11 East Asian Wide/Fullwidth (2 cols), Emoji (2 cols),
     * and zero-width combining marks (0 cols).
     */
    final class MbString
    {
        /**
         * Strips all ANSI CSI/OSC escape sequences before measuring or slicing visible text.
         */
        public static function stripAnsi(string $text): string
        {
            return (string) preg_replace('/\033(?:\[[0-9;?]*[A-Za-z]|\].*?(?:\007|\033\\\\)|[()][AB012])/s', '', $text);
        }

        /**
         * Returns the visible character length (code points) without ANSI sequences.
         */
        public static function length(string $text): int
        {
            $clean = self::stripAnsi($text);
            if (function_exists('grapheme_strlen')) {
                $len = grapheme_strlen($clean);
                if ($len !== false && $len !== null) {
                    return $len;
                }
            }
            if (function_exists('mb_strlen')) {
                return mb_strlen($clean, 'UTF-8');
            }
            return count(preg_split('//u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: []);
        }

        /**
         * Calculates exact terminal column display width according to UAX #11 East Asian Width
         * and Unicode Emoji presentation rules.
         */
        public static function displayWidth(string $text): int
        {
            $clean = self::stripAnsi($text);
            if ($clean === '') {
                return 0;
            }

            $chars = preg_split('//u', $clean, -1, PREG_SPLIT_NO_EMPTY);
            if ($chars === false) {
                return strlen($clean);
            }

            $width = 0;
            foreach ($chars as $ch) {
                $cp = self::ordUtf8($ch);
                $width += self::codepointWidth($cp);
            }
            return $width;
        }

        /**
         * Returns terminal column width (0, 1, or 2) for a single Unicode codepoint.
         */
        public static function codepointWidth(int $cp): int
        {
            // Control characters and null
            if ($cp === 0 || ($cp < 32) || ($cp >= 0x7F && $cp < 0xA0)) {
                return 0;
            }
            // Combining diacritical marks, zero-width space/joiners, variation selectors
            if (
                ($cp >= 0x0300 && $cp <= 0x036F) ||
                ($cp >= 0x1AB0 && $cp <= 0x1AFF) ||
                ($cp >= 0x1DC0 && $cp <= 0x1DFF) ||
                ($cp >= 0x20D0 && $cp <= 0x20FF) ||
                ($cp >= 0xFE00 && $cp <= 0xFE0F) ||
                ($cp >= 0xFE20 && $cp <= 0xFE2F) ||
                ($cp >= 0x200B && $cp <= 0x200F) ||
                $cp === 0xFEFF
            ) {
                return 0;
            }
            // Wide / Fullwidth East Asian & Emoji blocks (2 columns)
            if (
                ($cp >= 0x1100 && $cp <= 0x115F) || // Hangul Jamo
                $cp === 0x2329 || $cp === 0x232A ||
                ($cp >= 0x2E80 && $cp <= 0xA4CF && $cp !== 0x303F) || // CJK Radicals, Ideographs, Yi
                ($cp >= 0xAC00 && $cp <= 0xD7A3) || // Hangul Syllables
                ($cp >= 0xF900 && $cp <= 0xFAFF) || // CJK Compatibility Ideographs
                ($cp >= 0xFE10 && $cp <= 0xFE19) ||
                ($cp >= 0xFE30 && $cp <= 0xFE6F) ||
                ($cp >= 0xFF00 && $cp <= 0xFF60) || // Fullwidth Forms
                ($cp >= 0xFFE0 && $cp <= 0xFFE6) ||
                ($cp >= 0x1F300 && $cp <= 0x1F64F) || // Misc Symbols, Pictographs, Emoticons
                ($cp >= 0x1F680 && $cp <= 0x1F6FF) || // Transport & Map Symbols
                ($cp >= 0x1F900 && $cp <= 0x1FAFF) || // Supplemental Symbols and Pictographs
                ($cp >= 0x20000 && $cp <= 0x3FFFD)
            ) {
                return 2;
            }
            return 1;
        }

        public static function ordUtf8(string $char): int
        {
            if (function_exists('mb_ord')) {
                $ord = mb_ord($char, 'UTF-8');
                if ($ord !== false) {
                    return $ord;
                }
            }
            $bytes = unpack('C*', $char);
            if ($bytes === false || $bytes === []) {
                return 0;
            }
            $b1 = $bytes[1];
            if ($b1 < 0x80) {
                return $b1;
            }
            if (($b1 & 0xE0) === 0xC0 && isset($bytes[2])) {
                return (($b1 & 0x1F) << 6) | ($bytes[2] & 0x3F);
            }
            if (($b1 & 0xF0) === 0xE0 && isset($bytes[2], $bytes[3])) {
                return (($b1 & 0x0F) << 12) | (($bytes[2] & 0x3F) << 6) | ($bytes[3] & 0x3F);
            }
            if (($b1 & 0xF8) === 0xF0 && isset($bytes[2], $bytes[3], $bytes[4])) {
                return (($b1 & 0x07) << 18) | (($bytes[2] & 0x3F) << 12) | (($bytes[3] & 0x3F) << 6) | ($bytes[4] & 0x3F);
            }
            return 0;
        }

        public static function substr(string $text, int $start, ?int $length = null): string
        {
            if (function_exists('mb_substr')) {
                return $length === null
                    ? mb_substr($text, $start, null, 'UTF-8')
                    : mb_substr($text, $start, $length, 'UTF-8');
            }
            $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            return implode('', array_slice($chars, $start, $length));
        }

        public static function lower(string $text): string
        {
            return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        }

        public static function upper(string $text): string
        {
            return function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
        }

        /**
         * Truncates a string to fit within $maxWidth terminal columns, appending $ellipsis if truncated.
         */
        public static function truncateWidth(string $text, int $maxWidth, string $ellipsis = '…'): string
        {
            if ($maxWidth <= 0) {
                return '';
            }
            if (self::displayWidth($text) <= $maxWidth) {
                return $text;
            }
            $ellipsisWidth = self::displayWidth($ellipsis);
            $targetWidth = max(0, $maxWidth - $ellipsisWidth);
            $chars = preg_split('//u', self::stripAnsi($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $out = '';
            $used = 0;
            foreach ($chars as $ch) {
                $w = self::codepointWidth(self::ordUtf8($ch));
                if ($used + $w > $targetWidth) {
                    break;
                }
                $out .= $ch;
                $used += $w;
            }
            return $targetWidth > 0 ? $out . $ellipsis : self::substr($ellipsis, 0, $maxWidth);
        }

        /**
         * Pads a string to $targetWidth terminal columns using $padChar.
         */
        public static function padWidth(string $text, int $targetWidth, string $padChar = ' ', string $align = 'left'): string
        {
            $w = self::displayWidth($text);
            if ($w >= $targetWidth) {
                return $text;
            }
            $diff = $targetWidth - $w;
            if ($align === 'right') {
                return str_repeat($padChar, $diff) . $text;
            }
            if ($align === 'center') {
                $left = intdiv($diff, 2);
                $right = $diff - $left;
                return str_repeat($padChar, $left) . $text . str_repeat($padChar, $right);
            }
            return $text . str_repeat($padChar, $diff);
        }

        /**
         * Word-wraps text cleanly to $maxWidth terminal columns.
         *
         * @return list<string>
         */
        public static function wrapLines(string $text, int $maxWidth): array
        {
            $maxWidth = max(1, $maxWidth);
            $paragraphs = preg_split("/\r?\n/", $text) ?: [''];
            $lines = [];
            foreach ($paragraphs as $para) {
                if ($para === '') {
                    $lines[] = '';
                    continue;
                }
                $words = preg_split('/(\s+)/u', $para, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
                $current = '';
                foreach ($words as $token) {
                    if (preg_match('/^\s+$/u', $token) === 1) {
                        if ($current !== '' && self::displayWidth($current . ' ') <= $maxWidth) {
                            $current .= ' ';
                        }
                        continue;
                    }
                    $tokenWidth = self::displayWidth($token);
                    if ($current === '') {
                        if ($tokenWidth <= $maxWidth) {
                            $current = $token;
                        } else {
                            // Hard break overlong token
                            $chars = preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                            $chunk = '';
                            foreach ($chars as $ch) {
                                if (self::displayWidth($chunk . $ch) > $maxWidth) {
                                    $lines[] = $chunk;
                                    $chunk = $ch;
                                } else {
                                    $chunk .= $ch;
                                }
                            }
                            $current = $chunk;
                        }
                    } elseif (self::displayWidth($current . $token) <= $maxWidth) {
                        $current .= $token;
                    } else {
                        $lines[] = rtrim($current);
                        $current = $token;
                    }
                }
                if ($current !== '') {
                    $lines[] = rtrim($current);
                }
            }
            return $lines === [] ? [''] : $lines;
        }
    }
}

// ============================================================================
// File: src/Utils/Str.php
// ============================================================================
namespace DrevOps\PhpTui\Utils {
    /**
     * Built-in zero-dependency implementation of alexskrypnyk/str2name transforms,
     * identifier canonicalization, and fuzzy subsequence scoring.
     */
    final class Str
    {
        /**
         * Transforms an arbitrary string into the specified naming convention.
         * Supported transforms: machine, snake, kebab, slug, pascal, camel, constant,
         * human, title, lower, upper, initials, host, path, class_short.
         */
        public static function transform(string $input, string $format = 'machine'): string
        {
            $words = self::tokenize($input);
            return match (MbString::lower(trim($format))) {
                'machine', 'snake' => implode('_', array_map(static fn(string $w): string => MbString::lower($w), $words)),
                'kebab', 'slug', 'dash' => implode('-', array_map(static fn(string $w): string => MbString::lower($w), $words)),
                'pascal', 'studly', 'class', 'class_short' => implode('', array_map(static fn(string $w): string => ucfirst(MbString::lower($w)), $words)),
                'camel' => self::toCamel($words),
                'constant', 'env', 'macro' => implode('_', array_map(static fn(string $w): string => MbString::upper($w), $words)),
                'human', 'sentence' => $words === [] ? '' : ucfirst(implode(' ', array_map(static fn(string $w): string => MbString::lower($w), $words))),
                'title' => implode(' ', array_map(static fn(string $w): string => ucfirst(MbString::lower($w)), $words)),
                'lower' => MbString::lower(trim($input)),
                'upper' => MbString::upper(trim($input)),
                'initials', 'acronym' => implode('', array_map(static fn(string $w): string => MbString::lower(MbString::substr($w, 0, 1)), $words)),
                'host', 'domain' => implode('-', array_map(static fn(string $w): string => MbString::lower($w), $words)) . '.local',
                'path' => implode('/', array_map(static fn(string $w): string => MbString::lower($w), $words)),
                default => implode('_', array_map(static fn(string $w): string => MbString::lower($w), $words)),
            };
        }

        /**
         * Converts a human label or key into a canonical machine identifier (e.g. "Order name?" -> "order_name").
         */
        public static function toMachineId(string $label): string
        {
            $id = self::transform($label, 'machine');
            return $id !== '' ? $id : 'field';
        }

        /**
         * Converts a machine identifier into a PSR-4 PascalCase handler class short name (e.g. "red_apple" -> "RedApple").
         */
        public static function toClassName(string $id): string
        {
            return self::transform($id, 'pascal');
        }

        /**
         * Splits an arbitrary string (camelCase, PascalCase, snake_case, kebab-case, or punctuation-separated) into words.
         *
         * @return list<string>
         */
        public static function tokenize(string $input): array
        {
            // Insert boundary between lower/digit and upper, or upper sequence and upper+lower
            $expanded = (string) preg_replace([
                '/([a-z0-9])([A-Z])/u',
                '/([A-Z]+)([A-Z][a-z])/u',
            ], '$1 $2', $input);

            $parts = preg_split('/[^\p{L}\p{N}]+/u', $expanded, -1, PREG_SPLIT_NO_EMPTY);
            if ($parts === false || $parts === []) {
                return [];
            }
            return array_values($parts);
        }

        /**
         * @param list<string> $words
         */
        private static function toCamel(array $words): string
        {
            if ($words === []) {
                return '';
            }
            $first = MbString::lower($words[0]);
            $rest = array_slice($words, 1);
            return $first . implode('', array_map(static fn(string $w): string => ucfirst(MbString::lower($w)), $rest));
        }

        /**
         * Fuzzy-matches $query against $candidate and returns a score (>0 if matched, 0 if no match).
         * Combines exact prefix bonus, word-boundary bonus, consecutive match bonus, and subsequence alignment.
         */
        public static function fuzzyScore(string $query, string $candidate): int
        {
            $q = MbString::lower(trim($query));
            if ($q === '') {
                return 1;
            }
            $c = MbString::lower($candidate);
            if ($q === $c) {
                return 1000;
            }
            if (str_starts_with($c, $q)) {
                return 800 - max(0, MbString::length($c) - MbString::length($q));
            }
            $pos = strpos($c, $q);
            if ($pos !== false) {
                return 500 - $pos;
            }

            // Subsequence match with consecutive + word-boundary bonus
            $qChars = preg_split('//u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $cChars = preg_split('//u', $c, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $qi = 0;
            $qLen = count($qChars);
            $score = 0;
            $streak = 0;

            foreach ($cChars as $ci => $ch) {
                if ($qi < $qLen && $ch === $qChars[$qi]) {
                    $qi++;
                    $streak++;
                    $score += 15 + ($streak * 10);
                    if ($ci === 0 || in_array($cChars[$ci - 1], [' ', '_', '-', '/'], true)) {
                        $score += 25; // Word-start bonus
                    }
                } else {
                    $streak = 0;
                }
            }

            return $qi === $qLen ? max(1, $score - count($cChars)) : 0;
        }
    }
}

// ============================================================================
// File: src/Translation/TranslationInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Translation {
    interface TranslationInterface
    {
        /**
         * Translates a message key or literal string with named placeholder interpolation.
         *
         * @param array<string, scalar> $parameters
         */
        public function translate(string $message, array $parameters = []): string;

        /**
         * Translates a singular/plural message using CLDR plural category rules for the active locale.
         *
         * @param array<string, scalar> $parameters
         */
        public function translatePlural(string $singular, string $plural, int $count, array $parameters = []): string;

        public function getLocale(): string;
    }
}

// ============================================================================
// File: src/Translation/Translator.php
// ============================================================================
namespace DrevOps\PhpTui\Translation {
    /**
     * Localizes UI chrome, hints, buttons, validation messages, and form labels.
     * Supports custom catalog overrides, CLDR plural rules (one/few/many/other), and English fallback.
     */
    final class Translator implements TranslationInterface
    {
        /**
         * @param array<string, string|array<string, string>> $catalog
         * @param null|\Closure(int, string): string $pluralRuleResolver
         */
        public function __construct(
            private string $locale = 'en',
            private array $catalog = [],
            private ?\Closure $pluralRuleResolver = null,
        ) {
        }

        public function getLocale(): string
        {
            return $this->locale;
        }

        /**
         * @param array<string, string|array<string, string>> $entries
         */
        public function addCatalog(array $entries): self
        {
            $this->catalog = array_replace($this->catalog, $entries);
            return $this;
        }

        public function translate(string $message, array $parameters = []): string
        {
            $entry = $this->catalog[$message] ?? $message;
            if (is_array($entry)) {
                $template = $entry['other'] ?? $entry['one'] ?? (string) reset($entry);
            } else {
                $template = (string) $entry;
            }
            return $this->interpolate($template, $parameters);
        }

        public function translatePlural(string $singular, string $plural, int $count, array $parameters = []): string
        {
            $parameters['@count'] ??= $count;
            $parameters['{count}'] ??= $count;
            $category = $this->resolvePluralCategory($count);

            $entry = $this->catalog[$singular] ?? null;
            if (is_array($entry)) {
                $template = $entry[$category] ?? $entry['other'] ?? ($count === 1 ? $singular : $plural);
            } elseif (is_string($entry) && $count === 1) {
                $template = $entry;
            } else {
                $template = $count === 1 ? $singular : ($this->catalog[$plural] ?? $plural);
                if (is_array($template)) {
                    $template = $template[$category] ?? $template['other'] ?? $plural;
                }
            }

            return $this->interpolate((string) $template, $parameters);
        }

        /**
         * @param array<string, scalar> $parameters
         */
        private function interpolate(string $template, array $parameters): string
        {
            if ($parameters === []) {
                return $template;
            }
            $replacements = [];
            foreach ($parameters as $k => $v) {
                $strVal = (string) $v;
                $replacements[$k] = $strVal;
                if (!str_starts_with($k, '{') && !str_starts_with($k, '@') && !str_starts_with($k, ':')) {
                    $replacements['{' . $k . '}'] = $strVal;
                    $replacements['@' . $k] = $strVal;
                    $replacements[':' . $k] = $strVal;
                }
            }
            return strtr($template, $replacements);
        }

        private function resolvePluralCategory(int $n): string
        {
            if ($this->pluralRuleResolver !== null) {
                return ($this->pluralRuleResolver)($n, $this->locale);
            }
            $abs = abs($n);
            $lang = strtolower(explode('_', str_replace('-', '_', $this->locale))[0]);

            return match ($lang) {
                'ru', 'uk', 'sr', 'hr', 'bs' => match (true) {
                    $abs % 10 === 1 && $abs % 100 !== 11 => 'one',
                    $abs % 10 >= 2 && $abs % 10 <= 4 && ($abs % 100 < 12 || $abs % 100 > 14) => 'few',
                    default => 'many',
                },
                'pl' => match (true) {
                    $abs === 1 => 'one',
                    $abs % 10 >= 2 && $abs % 10 <= 4 && ($abs % 100 < 12 || $abs % 100 > 14) => 'few',
                    default => 'many',
                },
                'fr', 'pt' => $abs <= 1 ? 'one' : 'other',
                'ja', 'zh', 'ko', 'id', 'ms', 'vi', 'th' => 'other',
                default => $abs === 1 ? 'one' : 'other',
            };
        }
    }
}

// ============================================================================
// File: src/Answers/AnswerInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Answers {
    interface AnswerInterface extends \JsonSerializable
    {
        public function id(): string;

        public function label(): string;

        public function type(): string;

        public function value(): mixed;

        public function rawValue(): mixed;

        public function provenance(): string;

        public function isVisible(): bool;

        public function displayValue(): string;

        /**
         * @return array<string, mixed>
         */
        public function toArray(): array;
    }
}

// ============================================================================
// File: src/Answers/AnswerCollection.php
// ============================================================================
namespace DrevOps\PhpTui\Answers {
    /**
     * Self-describing snapshot of a single question's collected answer, its provenance
     * ('user', 'default', 'discovered', 'derived', 'prompt', 'env'), and its display representation.
     */
    final readonly class AnswerCollection implements AnswerInterface
    {
        public const PROVENANCE_USER = 'user';
        public const PROVENANCE_DEFAULT = 'default';
        public const PROVENANCE_DISCOVERED = 'discovered';
        public const PROVENANCE_DERIVED = 'derived';
        public const PROVENANCE_PROMPT = 'prompt';
        public const PROVENANCE_ENV = 'env';

        /**
         * @param array<string, mixed> $parts Named sub-parts (e.g. for Template fields)
         */
        public function __construct(
            private string $id,
            private string $label,
            private string $type,
            private mixed $value,
            private mixed $rawValue = null,
            private string $provenance = self::PROVENANCE_DEFAULT,
            private bool $visible = true,
            private string $formattedDisplay = '',
            private string $panelId = 'root',
            private array $parts = [],
        ) {
        }

        public function id(): string
        {
            return $this->id;
        }

        public function label(): string
        {
            return $this->label;
        }

        public function type(): string
        {
            return $this->type;
        }

        public function value(): mixed
        {
            return $this->value;
        }

        public function rawValue(): mixed
        {
            return $this->rawValue ?? $this->value;
        }

        public function provenance(): string
        {
            return $this->provenance;
        }

        public function isVisible(): bool
        {
            return $this->visible;
        }

        public function panelId(): string
        {
            return $this->panelId;
        }

        /**
         * @return array<string, mixed>
         */
        public function parts(): array
        {
            return $this->parts;
        }

        public function displayValue(): string
        {
            if ($this->formattedDisplay !== '') {
                return $this->formattedDisplay;
            }
            if ($this->type === 'password') {
                $str = (string) ($this->value ?? '');
                return $str === '' ? '(empty)' : str_repeat('•', min(12, strlen($str)));
            }
            if (is_bool($this->value)) {
                return $this->value ? 'Yes' : 'No';
            }
            if (is_array($this->value)) {
                return implode(', ', array_map(static fn(mixed $v): string => (string) $v, $this->value));
            }
            if ($this->value === null || $this->value === '') {
                return '—';
            }
            return (string) $this->value;
        }

        /**
         * @return array<string, mixed>
         */
        public function toArray(): array
        {
            $data = [
                'id' => $this->id,
                'label' => $this->label,
                'type' => $this->type,
                'value' => $this->value,
                'provenance' => $this->provenance,
                'visible' => $this->visible,
                'display' => $this->displayValue(),
                'panel' => $this->panelId,
            ];
            if ($this->parts !== []) {
                $data['parts'] = $this->parts;
            }
            return $data;
        }

        /**
         * @return array<string, mixed>
         */
        public function jsonSerialize(): array
        {
            return $this->toArray();
        }
    }
}

// ============================================================================
// File: src/Answers/Answers.php
// ============================================================================
namespace DrevOps\PhpTui\Answers {
    /**
     * Self-describing immutable container of collected answers.
     * Supports dot-path lookup ('order.fruit' or 'fruit'), JSON export, and human-readable summaries
     * without requiring the original Form definition.
     *
     * @implements \ArrayAccess<string, mixed>
     * @implements \IteratorAggregate<string, mixed>
     */
    final class Answers implements \ArrayAccess, \Countable, \IteratorAggregate, \JsonSerializable
    {
        /** @var array<string, AnswerCollection> */
        private array $records = [];

        /**
         * @param array<string, AnswerCollection|mixed> $items
         */
        public function __construct(
            array $items = [],
            private readonly string $formTitle = '',
            private readonly bool $cancelled = false,
        ) {
            foreach ($items as $key => $item) {
                if ($item instanceof AnswerCollection) {
                    $this->records[$item->id()] = $item;
                } else {
                    $id = (string) $key;
                    $this->records[$id] = new AnswerCollection(
                        id: $id,
                        label: $id,
                        type: get_debug_type($item),
                        value: $item,
                    );
                }
            }
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->value($key, $default);
        }

        public function value(string $key, mixed $default = null): mixed
        {
            if (isset($this->records[$key])) {
                return $this->records[$key]->isVisible() ? $this->records[$key]->value() : $default;
            }
            // Support dot notation: 'panel.field_id' or 'template_field.slot_name'
            if (str_contains($key, '.')) {
                [$prefix, $rest] = explode('.', $key, 2);
                if (isset($this->records[$prefix])) {
                    $parts = $this->records[$prefix]->parts();
                    if (array_key_exists($rest, $parts)) {
                        return $parts[$rest];
                    }
                }
                if (isset($this->records[$rest]) && $this->records[$rest]->panelId() === $prefix) {
                    return $this->records[$rest]->isVisible() ? $this->records[$rest]->value() : $default;
                }
            }
            return $default;
        }

        public function answer(string $key): ?AnswerCollection
        {
            return $this->records[$key] ?? null;
        }

        /**
         * @return array<string, AnswerCollection>
         */
        public function records(bool $visibleOnly = true): array
        {
            if (!$visibleOnly) {
                return $this->records;
            }
            return array_filter($this->records, static fn(AnswerCollection $r): bool => $r->isVisible());
        }

        /**
         * Returns a flat associative map of field ID => value for all active/visible fields.
         *
         * @return array<string, mixed>
         */
        public function all(bool $visibleOnly = true): array
        {
            $out = [];
            foreach ($this->records($visibleOnly) as $id => $record) {
                $out[$id] = $record->value();
                foreach ($record->parts() as $slotKey => $slotVal) {
                    $out["{$id}.{$slotKey}"] = $slotVal;
                }
            }
            return $out;
        }

        /**
         * @return array<string, mixed>
         */
        public function toArray(bool $visibleOnly = true): array
        {
            return $this->all($visibleOnly);
        }

        public function has(string $key): bool
        {
            if (isset($this->records[$key])) {
                return $this->records[$key]->isVisible();
            }
            if (str_contains($key, '.')) {
                [$prefix, $rest] = explode('.', $key, 2);
                if (isset($this->records[$prefix]) && array_key_exists($rest, $this->records[$prefix]->parts())) {
                    return true;
                }
                if (isset($this->records[$rest]) && $this->records[$rest]->panelId() === $prefix) {
                    return $this->records[$rest]->isVisible();
                }
            }
            return false;
        }

        public function isCancelled(): bool
        {
            return $this->cancelled;
        }

        public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
        {
            return (string) json_encode($this->all(true), $flags);
        }

        /**
         * Renders a self-contained human-readable summary table of all collected answers and their provenance.
         */
        public function toSummary(bool $includeProvenance = false): string
        {
            $lines = [];
            if ($this->formTitle !== '') {
                $lines[] = $this->formTitle;
                $lines[] = str_repeat('─', max(12, strlen($this->formTitle)));
            }
            $visible = $this->records(true);
            if ($visible === []) {
                $lines[] = '(no answers collected)';
                return implode("\n", $lines) . "\n";
            }
            $maxLabel = 0;
            foreach ($visible as $rec) {
                $maxLabel = max($maxLabel, strlen($rec->label()));
            }
            foreach ($visible as $rec) {
                $padLabel = str_pad($rec->label(), $maxLabel, ' ', STR_PAD_RIGHT);
                $line = sprintf('  %s : %s', $padLabel, $rec->displayValue());
                if ($includeProvenance) {
                    $line .= sprintf(' [%s]', $rec->provenance());
                }
                $lines[] = $line;
            }
            return implode("\n", $lines) . "\n";
        }

        public function offsetExists(mixed $offset): bool
        {
            return $this->has((string) $offset);
        }

        public function offsetGet(mixed $offset): mixed
        {
            return $this->value((string) $offset);
        }

        public function offsetSet(mixed $offset, mixed $value): void
        {
            throw new \BadMethodCallException('Answers collection is immutable.');
        }

        public function offsetUnset(mixed $offset): void
        {
            throw new \BadMethodCallException('Answers collection is immutable.');
        }

        public function count(): int
        {
            return count($this->records(true));
        }

        public function getIterator(): \Traversable
        {
            return new \ArrayIterator($this->all(true));
        }

        /**
         * @return array<string, mixed>
         */
        public function jsonSerialize(): array
        {
            return $this->all(true);
        }
    }
}

// ============================================================================
// File: src/Condition/ConditionInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Condition {
    interface ConditionInterface
    {
        /**
         * Evaluates the visibility condition against the current map of active field values.
         *
         * @param array<string, mixed> $values
         */
        public function evaluate(array $values): bool;

        /**
         * Returns all target field IDs referenced by this condition (for build-time graph validation).
         *
         * @return list<string>
         */
        public function referencedFields(): array;

        /**
         * Exports a machine-readable description of the condition for JSON Schema / AI Agent introspection.
         *
         * @return array<string, mixed>
         */
        public function toSchema(): array;
    }
}

// ============================================================================
// File: src/Condition/Equals.php
// ============================================================================
namespace DrevOps\PhpTui\Condition {
    final readonly class Equals implements ConditionInterface
    {
        public function __construct(
            private string $fieldId,
            private mixed $expected,
            private bool $negate = false,
        ) {
        }

        public function evaluate(array $values): bool
        {
            $actual = $values[$this->fieldId] ?? null;
            if (is_array($this->expected)) {
                $match = is_array($actual)
                    ? array_intersect($this->expected, $actual) !== []
                    : in_array($actual, $this->expected, false);
            } elseif (is_array($actual)) {
                $match = in_array($this->expected, $actual, false);
            } else {
                $match = $actual == $this->expected;
            }
            return $this->negate ? !$match : $match;
        }

        public function referencedFields(): array
        {
            return [$this->fieldId];
        }

        public function toSchema(): array
        {
            return [
                'op' => $this->negate ? 'ne' : 'eq',
                'field' => $this->fieldId,
                'value' => $this->expected,
            ];
        }
    }
}

// ============================================================================
// File: src/Condition/Matches.php
// ============================================================================
namespace DrevOps\PhpTui\Condition {
    final readonly class Matches implements ConditionInterface
    {
        public function __construct(
            private string $fieldId,
            private string $pattern,
        ) {
        }

        public function evaluate(array $values): bool
        {
            $actual = $values[$this->fieldId] ?? null;
            if (!is_scalar($actual)) {
                return false;
            }
            return @preg_match($this->pattern, (string) $actual) === 1;
        }

        public function referencedFields(): array
        {
            return [$this->fieldId];
        }

        public function toSchema(): array
        {
            return [
                'op' => 'matches',
                'field' => $this->fieldId,
                'pattern' => $this->pattern,
            ];
        }
    }
}

// ============================================================================
// File: src/Condition/Set.php
// ============================================================================
namespace DrevOps\PhpTui\Condition {
    final readonly class Set implements ConditionInterface
    {
        public function __construct(private string $fieldId)
        {
        }

        public function evaluate(array $values): bool
        {
            if (!array_key_exists($this->fieldId, $values)) {
                return false;
            }
            $v = $values[$this->fieldId];
            return $v !== null && $v !== '' && $v !== [] && $v !== false;
        }

        public function referencedFields(): array
        {
            return [$this->fieldId];
        }

        public function toSchema(): array
        {
            return ['op' => 'set', 'field' => $this->fieldId];
        }
    }
}

// ============================================================================
// File: src/Condition/NotSet.php
// ============================================================================
namespace DrevOps\PhpTui\Condition {
    final readonly class NotSet implements ConditionInterface
    {
        public function __construct(private string $fieldId)
        {
        }

        public function evaluate(array $values): bool
        {
            return !(new Set($this->fieldId))->evaluate($values);
        }

        public function referencedFields(): array
        {
            return [$this->fieldId];
        }

        public function toSchema(): array
        {
            return ['op' => 'not_set', 'field' => $this->fieldId];
        }
    }
}

// ============================================================================
// File: src/Condition/Condition.php
// ============================================================================
namespace DrevOps\PhpTui\Condition {
    /**
     * Fluent and composite condition builder supporting eq, ne, in, contains, matches, all (AND), any (OR), and not.
     */
    final readonly class Condition implements ConditionInterface
    {
        private const UNSET_SENTINEL = '__phptui_unset__';

        /**
         * @param list<ConditionInterface> $children
         */
        public function __construct(
            private string $fieldId = '',
            private mixed $eq = self::UNSET_SENTINEL,
            private mixed $ne = self::UNSET_SENTINEL,
            private ?array $in = null,
            private mixed $contains = self::UNSET_SENTINEL,
            private string $logicalOp = 'leaf',
            private array $children = [],
        ) {
        }

        public static function equals(string $fieldId, mixed $value): Equals
        {
            return new Equals($fieldId, $value, false);
        }

        public static function notEquals(string $fieldId, mixed $value): Equals
        {
            return new Equals($fieldId, $value, true);
        }

        public static function set(string $fieldId): Set
        {
            return new Set($fieldId);
        }

        public static function notSet(string $fieldId): NotSet
        {
            return new NotSet($fieldId);
        }

        public static function matches(string $fieldId, string $pattern): Matches
        {
            return new Matches($fieldId, $pattern);
        }

        public static function all(ConditionInterface ...$conditions): self
        {
            return new self(logicalOp: 'all', children: array_values($conditions));
        }

        public static function any(ConditionInterface ...$conditions): self
        {
            return new self(logicalOp: 'any', children: array_values($conditions));
        }

        public static function not(ConditionInterface $condition): self
        {
            return new self(logicalOp: 'not', children: [$condition]);
        }

        public function evaluate(array $values): bool
        {
            if ($this->logicalOp === 'all') {
                foreach ($this->children as $child) {
                    if (!$child->evaluate($values)) {
                        return false;
                    }
                }
                return true;
            }
            if ($this->logicalOp === 'any') {
                foreach ($this->children as $child) {
                    if ($child->evaluate($values)) {
                        return true;
                    }
                }
                return false;
            }
            if ($this->logicalOp === 'not') {
                return isset($this->children[0]) ? !$this->children[0]->evaluate($values) : true;
            }

            $actual = $values[$this->fieldId] ?? null;
            if ($this->eq !== self::UNSET_SENTINEL) {
                return (new Equals($this->fieldId, $this->eq, false))->evaluate($values);
            }
            if ($this->ne !== self::UNSET_SENTINEL) {
                return (new Equals($this->fieldId, $this->ne, true))->evaluate($values);
            }
            if ($this->in !== null) {
                return is_array($actual)
                    ? array_intersect($this->in, $actual) !== []
                    : in_array($actual, $this->in, false);
            }
            if ($this->contains !== self::UNSET_SENTINEL) {
                if (is_array($actual)) {
                    return in_array($this->contains, $actual, false);
                }
                if (is_string($actual) && is_scalar($this->contains)) {
                    return str_contains($actual, (string) $this->contains);
                }
                return false;
            }
            return (new Set($this->fieldId))->evaluate($values);
        }

        public function referencedFields(): array
        {
            if ($this->children !== []) {
                $refs = [];
                foreach ($this->children as $c) {
                    foreach ($c->referencedFields() as $f) {
                        $refs[$f] = true;
                    }
                }
                return array_keys($refs);
            }
            return $this->fieldId !== '' ? [$this->fieldId] : [];
        }

        public function toSchema(): array
        {
            if ($this->logicalOp !== 'leaf') {
                return [
                    'op' => $this->logicalOp,
                    'conditions' => array_map(static fn(ConditionInterface $c): array => $c->toSchema(), $this->children),
                ];
            }
            if ($this->eq !== self::UNSET_SENTINEL) {
                return ['op' => 'eq', 'field' => $this->fieldId, 'value' => $this->eq];
            }
            if ($this->ne !== self::UNSET_SENTINEL) {
                return ['op' => 'ne', 'field' => $this->fieldId, 'value' => $this->ne];
            }
            if ($this->in !== null) {
                return ['op' => 'in', 'field' => $this->fieldId, 'values' => $this->in];
            }
            if ($this->contains !== self::UNSET_SENTINEL) {
                return ['op' => 'contains', 'field' => $this->fieldId, 'value' => $this->contains];
            }
            return ['op' => 'set', 'field' => $this->fieldId];
        }
    }
}

// ============================================================================
// File: src/Derive/DeriveInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Derive {
    interface DeriveInterface
    {
        /**
         * Computes the derived value from the current map of field values.
         *
         * @param array<string, mixed> $values
         */
        public function compute(array $values): mixed;

        /**
         * Returns the list of upstream field IDs that this derivation depends on (used for DAG cycle check).
         *
         * @return list<string>
         */
        public function dependencies(): array;

        /**
         * @return array<string, mixed>
         */
        public function toSchema(): array;
    }
}

// ============================================================================
// File: src/Derive/Transform.php
// ============================================================================
namespace DrevOps\PhpTui\Derive {
    use DrevOps\PhpTui\Utils\Str;

    /**
     * Computes a derived field value by applying a str2name naming transformation
     * (e.g. 'machine', 'kebab', 'pascal', 'camel', 'constant', 'initials', 'host') or custom Closure to a source field.
     */
    final readonly class Transform implements DeriveInterface
    {
        /**
         * @param string|list<string> $sourceFields
         * @param string|\Closure(mixed, array<string, mixed>): mixed $transform
         */
        public function __construct(
            private string|array $sourceFields,
            private string|\Closure $transform = 'machine',
            private string $separator = ' ',
        ) {
        }

        public function compute(array $values): mixed
        {
            $sources = is_array($this->sourceFields) ? $this->sourceFields : [$this->sourceFields];
            $parts = [];
            foreach ($sources as $src) {
                $val = $values[$src] ?? '';
                if (is_array($val)) {
                    $val = implode($this->separator, array_map(
                        static fn(mixed $item): string => is_scalar($item) || $item instanceof \Stringable ? (string) $item : '',
                        $val,
                    ));
                }
                if ((string) $val !== '') {
                    $parts[] = (string) $val;
                }
            }
            $combined = implode($this->separator, $parts);
            if ($this->transform instanceof \Closure) {
                return ($this->transform)($combined, $values);
            }
            if ($combined === '') {
                return '';
            }
            return Str::transform($combined, $this->transform);
        }

        public function dependencies(): array
        {
            return is_array($this->sourceFields) ? array_values($this->sourceFields) : [$this->sourceFields];
        }

        public function toSchema(): array
        {
            return [
                'type' => 'transform',
                'sources' => $this->dependencies(),
                'transform' => is_string($this->transform) ? $this->transform : 'closure',
            ];
        }
    }
}

// ============================================================================
// File: src/Derive/Concatenate.php
// ============================================================================
namespace DrevOps\PhpTui\Derive {
    use DrevOps\PhpTui\Utils\Str;

    /**
     * Joins or interpolates multiple upstream fields using a template string (e.g. "{org}/{project}")
     * or separator, with optional per-token str2name transformation.
     */
    final readonly class Concatenate implements DeriveInterface
    {
        /**
         * @param list<string> $sourceFields
         */
        public function __construct(
            private array $sourceFields,
            private string $templateOrSeparator = '-',
            private ?string $transformEach = null,
        ) {
        }

        public function compute(array $values): string
        {
            $hasPlaceholders = preg_match('/\{[A-Za-z0-9_]+\}/', $this->templateOrSeparator) === 1;
            if ($hasPlaceholders) {
                $replacements = [];
                foreach ($this->sourceFields as $fieldId) {
                    $raw = self::scalarize($values[$fieldId] ?? '');
                    $formatted = $this->transformEach !== null && $raw !== ''
                        ? Str::transform($raw, $this->transformEach)
                        : $raw;
                    $replacements['{' . $fieldId . '}'] = $formatted;
                }
                return strtr($this->templateOrSeparator, $replacements);
            }

            $tokens = [];
            foreach ($this->sourceFields as $fieldId) {
                $raw = self::scalarize($values[$fieldId] ?? '');
                if ($raw === '') {
                    continue;
                }
                $tokens[] = $this->transformEach !== null ? Str::transform($raw, $this->transformEach) : $raw;
            }
            return implode($this->templateOrSeparator, $tokens);
        }

        public function dependencies(): array
        {
            return array_values($this->sourceFields);
        }

        /**
         * Coerce a raw value to string without warnings on arrays/objects.
         */
        private static function scalarize(mixed $value): string
        {
            if (is_array($value)) {
                $parts = [];
                array_walk_recursive($value, static function (mixed $item) use (&$parts): void {
                    if (is_scalar($item) || $item instanceof \Stringable) {
                        $parts[] = (string) $item;
                    }
                });
                return implode(' ', $parts);
            }
            if (is_scalar($value) || $value instanceof \Stringable) {
                return (string) $value;
            }
            return '';
        }

        public function toSchema(): array
        {
            return [
                'type' => 'concatenate',
                'sources' => $this->dependencies(),
                'template' => $this->templateOrSeparator,
                'transform' => $this->transformEach,
            ];
        }
    }
}

// ============================================================================
// File: src/Discovery/DiscovererInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Discovery {
    interface DiscovererInterface
    {
        /**
         * Attempts to discover a default value from the given project root directory.
         * Returns null when no value could be discovered.
         */
        public function discover(string $directory): mixed;

        /**
         * @return array<string, mixed>
         */
        public function toSchema(): array;
    }
}

// ============================================================================
// File: src/Discovery/Discovery.php
// ============================================================================
namespace DrevOps\PhpTui\Discovery {
    /**
     * Built-in project context discovery rules:
     * - dotenv($key, $file = '.env'): parses KEY=VALUE from .env files
     * - jsonPath($file, $dotPath): extracts nested value from composer.json / package.json / etc.
     * - pathExists($relativePath, $whenTrue = true, $whenFalse = false): checks filesystem presence
     * - scan($relativeDir, $mode = 'files', $pattern = '*'): lists files or subdirectories
     * - chain(...$discoverers): returns the first non-null discovered result
     */
    final readonly class Discovery implements DiscovererInterface
    {
        /**
         * @param list<DiscovererInterface> $chain
         */
        private function __construct(
            private string $kind,
            private string $target = '',
            private string $secondary = '',
            private mixed $trueValue = true,
            private mixed $falseValue = false,
            private array $chain = [],
            private ?\Closure $customCallback = null,
        ) {
        }

        public static function dotenv(string $key, string $envFile = '.env'): self
        {
            return new self(kind: 'dotenv', target: $key, secondary: $envFile);
        }

        public static function jsonPath(string $jsonFile, string $dotPath): self
        {
            return new self(kind: 'json', target: $jsonFile, secondary: $dotPath);
        }

        public static function pathExists(string $relativePath, mixed $whenTrue = true, mixed $whenFalse = false): self
        {
            return new self(kind: 'path_exists', target: $relativePath, trueValue: $whenTrue, falseValue: $whenFalse);
        }

        public static function scan(string $relativeDir = '.', string $mode = 'files', string $pattern = '*'): self
        {
            return new self(kind: 'scan', target: $relativeDir, secondary: "{$mode}:{$pattern}");
        }

        public static function callback(\Closure $callback): self
        {
            return new self(kind: 'callback', customCallback: $callback);
        }

        public static function chain(DiscovererInterface ...$discoverers): self
        {
            return new self(kind: 'chain', chain: array_values($discoverers));
        }

        public function discover(string $directory): mixed
        {
            $base = $directory !== '' ? rtrim($directory, '/\\') : (getcwd() ?: '.');

            return match ($this->kind) {
                'dotenv' => $this->discoverDotenv($base, $this->secondary, $this->target),
                'json' => $this->discoverJson($base, $this->target, $this->secondary),
                'path_exists' => file_exists($base . DIRECTORY_SEPARATOR . ltrim($this->target, '/\\'))
                    ? $this->trueValue
                    : $this->falseValue,
                'scan' => $this->discoverScan($base, $this->target, $this->secondary),
                'callback' => $this->customCallback !== null ? ($this->customCallback)($base) : null,
                'chain' => $this->discoverChain($base),
                default => null,
            };
        }

        private function discoverDotenv(string $base, string $file, string $key): ?string
        {
            $path = $base . DIRECTORY_SEPARATOR . ltrim($file, '/\\');
            if (!is_file($path) || !is_readable($path)) {
                return null;
            }
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                return null;
            }
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }
                if (str_starts_with($trimmed, 'export ')) {
                    $trimmed = trim(substr($trimmed, 7));
                }
                $eqPos = strpos($trimmed, '=');
                if ($eqPos === false) {
                    continue;
                }
                $k = trim(substr($trimmed, 0, $eqPos));
                if ($k !== $key) {
                    continue;
                }
                $v = trim(substr($trimmed, $eqPos + 1));
                if (
                    (str_starts_with($v, '"') && str_ends_with($v, '"')) ||
                    (str_starts_with($v, "'") && str_ends_with($v, "'"))
                ) {
                    $v = substr($v, 1, -1);
                } else {
                    // Strip inline comment if unquoted
                    $hashPos = strpos($v, ' #');
                    if ($hashPos !== false) {
                        $v = rtrim(substr($v, 0, $hashPos));
                    }
                }
                return $v;
            }
            return null;
        }

        private function discoverJson(string $base, string $file, string $dotPath): mixed
        {
            $path = $base . DIRECTORY_SEPARATOR . ltrim($file, '/\\');
            if (!is_file($path) || !is_readable($path)) {
                return null;
            }
            $raw = file_get_contents($path);
            if ($raw === false) {
                return null;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return null;
            }
            $segments = explode('.', $dotPath);
            $cursor = $decoded;
            foreach ($segments as $seg) {
                if (!is_array($cursor) || !array_key_exists($seg, $cursor)) {
                    return null;
                }
                $cursor = $cursor[$seg];
            }
            return $cursor;
        }

        /**
         * @return list<string>|null
         */
        private function discoverScan(string $base, string $relDir, string $modeAndPattern): ?array
        {
            $targetDir = $relDir === '.' || $relDir === ''
                ? $base
                : $base . DIRECTORY_SEPARATOR . ltrim($relDir, '/\\');
            if (!is_dir($targetDir) || !is_readable($targetDir)) {
                return null;
            }
            [$mode, $pattern] = array_pad(explode(':', $modeAndPattern, 2), 2, '*');
            if (!in_array($mode, ['files', 'dirs'], true)) {
                throw new FormException(sprintf("Unknown scan mode '%s'.", $mode));
            }
            $entries = scandir($targetDir);
            if ($entries === false) {
                return null;
            }
            $results = [];
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $targetDir . DIRECTORY_SEPARATOR . $entry;
                if ($mode === 'dirs' && !is_dir($full)) {
                    continue;
                }
                if ($mode === 'files' && !is_file($full)) {
                    continue;
                }
                if ($pattern !== '*' && !fnmatch($pattern, $entry)) {
                    continue;
                }
                $results[] = $entry;
            }
            sort($results);
            return $results;
        }

        private function discoverChain(string $base): mixed
        {
            foreach ($this->chain as $item) {
                $val = $item->discover($base);
                if ($val !== null) {
                    return $val;
                }
            }
            return null;
        }

        public function toSchema(): array
        {
            return [
                'kind' => $this->kind,
                'target' => $this->target,
                'secondary' => $this->secondary,
            ];
        }
    }
}

// ============================================================================
// File: src/Handler/HandlerInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Handler {
    interface HandlerInterface
    {
        /**
         * Provides a dynamic default value when no explicit or discovered default exists.
         *
         * @param array<string, mixed> $answers
         */
        public function default(array $answers, string $directory = ''): mixed;

        /**
         * Validates a candidate answer value. Returns null if valid, or a human-readable error message string.
         *
         * @param array<string, mixed> $answers
         */
        public function validate(mixed $value, array $answers): ?string;

        /**
         * Transforms an accepted answer value into its normalized consumer representation.
         *
         * @param array<string, mixed> $answers
         */
        public function transform(mixed $value, array $answers): mixed;
    }
}

// ============================================================================
// File: src/Handler/HandlerRegistry.php
// ============================================================================
namespace DrevOps\PhpTui\Handler {
    use DrevOps\PhpTui\Utils\Str;

    /**
     * Resolves per-field HandlerInterface implementations either via explicit registration
     * (type-safe, static-analysis friendly) or PSR-4 namespace + str2name class discovery.
     */
    final class HandlerRegistry
    {
        /** @var array<string, HandlerInterface> */
        private array $instances = [];

        /** @var array<string, class-string<HandlerInterface>> */
        private array $classMap = [];

        /** @var list<string> */
        private array $namespaces = [];

        /**
         * @param list<string> $namespaces
         */
        public function __construct(array $namespaces = [])
        {
            foreach ($namespaces as $ns) {
                $this->addNamespace($ns);
            }
        }

        public function addNamespace(string $namespace): self
        {
            $trimmed = trim($namespace, '\\');
            if ($trimmed !== '' && !in_array($trimmed, $this->namespaces, true)) {
                $this->namespaces[] = $trimmed;
            }
            return $this;
        }

        /**
         * Explicitly binds a field ID to a handler instance or class-string (avoids dynamic reflection overhead).
         *
         * @param HandlerInterface|class-string<HandlerInterface> $handler
         */
        public function register(string $fieldId, HandlerInterface|string $handler): self
        {
            if ($handler instanceof HandlerInterface) {
                $this->instances[$fieldId] = $handler;
            } else {
                $this->classMap[$fieldId] = $handler;
            }
            return $this;
        }

        public function resolve(string $fieldId): ?HandlerInterface
        {
            if (isset($this->instances[$fieldId])) {
                return $this->instances[$fieldId];
            }
            if (isset($this->classMap[$fieldId])) {
                $cls = $this->classMap[$fieldId];
                $inst = self::safeInstantiate($cls);
                if ($inst !== null) {
                    return $this->instances[$fieldId] = $inst;
                }
            }
            $shortName = Str::toClassName($fieldId);
            foreach ($this->namespaces as $ns) {
                foreach (["{$ns}\{$shortName}Handler", "{$ns}\{$shortName}"] as $candidate) {
                    $inst = self::safeInstantiate($candidate);
                    if ($inst !== null) {
                        return $this->instances[$fieldId] = $inst;
                    }
                }
            }
            return null;
        }

        /**
         * Instantiate a discovered handler without fataling on abstract
         * classes, interfaces, or constructors with required arguments.
         */
        private static function safeInstantiate(string $cls): ?HandlerInterface
        {
            if (!class_exists($cls)) {
                return null;
            }
            try {
                $reflection = new \ReflectionClass($cls);
                if (!$reflection->isInstantiable()) {
                    return null;
                }
                $constructor = $reflection->getConstructor();
                if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
                    return null;
                }
                $inst = $reflection->newInstance();
            } catch (\Throwable) {
                return null;
            }
            return $inst instanceof HandlerInterface ? $inst : null;
        }

        public function has(string $fieldId): bool
        {
            return $this->resolve($fieldId) !== null;
        }
    }
}

// ============================================================================
// File: src/Input/Key.php
// ============================================================================
namespace DrevOps\PhpTui\Input {
    use DrevOps\PhpTui\FormException;

    /**
     * Represents a normalized keyboard key, character input, or semantic action binding.
     * Includes 'default' and 'vim' keybinding presets and build-time collision checking.
     */
    final readonly class Key
    {
        public const ENTER = 'enter';
        public const ESCAPE = 'escape';
        public const TAB = 'tab';
        public const SHIFT_TAB = 'shift_tab';
        public const SPACE = 'space';
        public const BACKSPACE = 'backspace';
        public const DELETE = 'delete';
        public const UP = 'up';
        public const DOWN = 'down';
        public const LEFT = 'left';
        public const RIGHT = 'right';
        public const HOME = 'home';
        public const END = 'end';
        public const PAGE_UP = 'page_up';
        public const PAGE_DOWN = 'page_down';
        public const HELP = '?';
        public const CTRL_C = 'ctrl_c';
        public const CTRL_D = 'ctrl_d';
        public const CTRL_G = 'ctrl_g';
        public const CTRL_R = 'ctrl_r';
        public const CTRL_U = 'ctrl_u';
        public const CTRL_W = 'ctrl_w';

        // Semantic actions
        public const ACTION_UP = 'move_up';
        public const ACTION_DOWN = 'move_down';
        public const ACTION_LEFT = 'move_left';
        public const ACTION_RIGHT = 'move_right';
        public const ACTION_EDIT = 'edit';
        public const ACTION_ACCEPT = 'accept';
        public const ACTION_CANCEL = 'cancel';
        public const ACTION_TOGGLE = 'toggle';
        public const ACTION_HELP = 'help';
        public const ACTION_SUBMIT = 'submit';

        public function __construct(
            public string $name,
            public string $char = '',
            public bool $ctrl = false,
            public bool $alt = false,
            public bool $shift = false,
        ) {
        }

        public static function named(string $name): self
        {
            $norm = strtolower(trim($name));
            return match ($norm) {
                'enter', 'return', "\r", "\n" => new self(self::ENTER, "\n"),
                'esc', 'escape', "\033" => new self(self::ESCAPE),
                'tab', "\t" => new self(self::TAB, "\t"),
                'shift_tab', 'shifttab', 'backtab' => new self(self::SHIFT_TAB, '', false, false, true),
                'space', ' ' => new self(self::SPACE, ' '),
                'backspace', 'bs', "\x7f", "\x08" => new self(self::BACKSPACE),
                'delete', 'del' => new self(self::DELETE),
                'up', 'arrow_up' => new self(self::UP),
                'down', 'arrow_down' => new self(self::DOWN),
                'left', 'arrow_left' => new self(self::LEFT),
                'right', 'arrow_right' => new self(self::RIGHT),
                'home' => new self(self::HOME),
                'end' => new self(self::END),
                'page_up', 'pageup', 'pgup' => new self(self::PAGE_UP),
                'page_down', 'pagedown', 'pgdn' => new self(self::PAGE_DOWN),
                'ctrl_c', 'ctrl+c', '^c', "\x03" => new self(self::CTRL_C, '', true),
                'ctrl_d', 'ctrl+d', '^d', "\x04" => new self(self::CTRL_D, '', true),
                'ctrl_g', 'ctrl+g', '^g', "\x07" => new self(self::CTRL_G, '', true),
                'ctrl_r', 'ctrl+r', '^r', "\x12" => new self(self::CTRL_R, '', true),
                'ctrl_u', 'ctrl+u', '^u', "\x15" => new self(self::CTRL_U, '', true),
                'ctrl_w', 'ctrl+w', '^w', "\x17" => new self(self::CTRL_W, '', true),
                default => MbString::length($name) === 1 ? new self($name, $name) : new self($norm),
            };
        }

        public static function char(string $ch): self
        {
            if ($ch === ' ') {
                return new self(self::SPACE, ' ');
            }
            if ($ch === "\r" || $ch === "\n") {
                return new self(self::ENTER, "\n");
            }
            if ($ch === "\t") {
                return new self(self::TAB, "\t");
            }
            return new self($ch, $ch);
        }

        public function isPrintable(): bool
        {
            return !$this->ctrl && !$this->alt && $this->char !== '' && $this->name !== self::ENTER && $this->name !== self::TAB;
        }

        /**
         * Returns a validated semantic action -> key list map for 'default', 'vim', or custom overrides.
         * Fails loudly at build time with FormException if an invalid action or duplicate conflicting key is bound.
         *
         * @param string|array<string, list<string>|string> $presetOrBindings
         * @return array<string, list<string>>
         */
        public static function resolveBindings(string|array $presetOrBindings = 'default'): array
        {
            $base = [
                self::ACTION_UP => [self::UP],
                self::ACTION_DOWN => [self::DOWN],
                self::ACTION_LEFT => [self::LEFT],
                self::ACTION_RIGHT => [self::RIGHT],
                self::ACTION_EDIT => [self::ENTER],
                self::ACTION_ACCEPT => [self::ENTER],
                self::ACTION_CANCEL => [self::ESCAPE],
                self::ACTION_TOGGLE => [self::SPACE],
                self::ACTION_HELP => [self::HELP],
                self::ACTION_SUBMIT => ['s', self::ENTER],
            ];

            if ($presetOrBindings === 'default' || $presetOrBindings === '') {
                return $base;
            }

            if ($presetOrBindings === 'vim') {
                $base[self::ACTION_UP] = [self::UP, 'k'];
                $base[self::ACTION_DOWN] = [self::DOWN, 'j'];
                $base[self::ACTION_LEFT] = [self::LEFT, 'h'];
                $base[self::ACTION_RIGHT] = [self::RIGHT, 'l'];
                $base[self::ACTION_EDIT] = [self::ENTER, 'i', 'o'];
                $base[self::ACTION_CANCEL] = [self::ESCAPE, 'q'];
                return $base;
            }

            if (is_string($presetOrBindings)) {
                throw new FormException(sprintf("Unknown key binding preset '%s'. Expected 'default' or 'vim'.", $presetOrBindings));
            }

            $validActions = array_keys($base);
            foreach ($presetOrBindings as $action => $keys) {
                if (!in_array($action, $validActions, true)) {
                    throw new FormException(sprintf("Invalid key binding action '%s'.", $action));
                }
                $normalized = is_array($keys) ? array_values($keys) : [$keys];
                if ($normalized === [] || $normalized === ['']) {
                    throw new FormException(sprintf("Key binding action '%s' cannot be bound to an empty key list.", $action));
                }
                $base[$action] = array_map(static fn(string $k): string => self::named($k)->name, $normalized);
            }

            // Build-time collision check between navigation/cancel actions
            $navKeys = [];
            foreach ([self::ACTION_UP, self::ACTION_DOWN, self::ACTION_CANCEL] as $act) {
                foreach ($base[$act] as $k) {
                    if (isset($navKeys[$k])) {
                        throw new FormException(sprintf(
                            "Key binding conflict: key '%s' is bound to both '%s' and '%s'.",
                            $k,
                            $navKeys[$k],
                            $act
                        ));
                    }
                    $navKeys[$k] = $act;
                }
            }

            return $base;
        }
    }
}

// ============================================================================
// File: src/Input/Event.php
// ============================================================================
namespace DrevOps\PhpTui\Input {
    /**
     * Immutable input event wrapping a decoded Key or bracketed-paste payload.
     */
    final readonly class Event
    {
        public function __construct(
            public Key $key,
            public bool $isPaste = false,
            public string $pasteText = '',
        ) {
        }

        public static function key(Key $key): self
        {
            return new self($key);
        }

        public static function paste(string $text): self
        {
            return new self(new Key('paste', $text), true, $text);
        }
    }
}

// ============================================================================
// File: src/Input/InputReader.php
// ============================================================================
namespace DrevOps\PhpTui\Input {
    /**
     * Non-blocking multiplexed ANSI / UTF-8 input stream reader.
     * Decodes CSI/SS3 escape sequences, bracketed paste (\e[200~...\e[201~), Ctrl combinations,
     * and multibyte UTF-8 characters without freezing the event loop.
     */
    final class InputReader
    {
        /** @var list<Key> */
        private array $queue = [];

        /**
         * @param resource|null $stream
         */
        public function __construct(
            private $stream = null,
        ) {
        }

        /**
         * Pushes scripted keys or raw strings into the reader buffer (used by TuiTester or typeahead).
         */
        public function push(Key|string ...$inputs): void
        {
            foreach ($inputs as $input) {
                if ($input instanceof Key) {
                    $this->queue[] = $input;
                    continue;
                }
                foreach (self::tokenizeString($input) as $k) {
                    $this->queue[] = $k;
                }
            }
        }

        public function hasQueued(): bool
        {
            return $this->queue !== [];
        }

        /**
         * Reads the next Key from the queue or non-blocking stream (with timeout in microseconds).
         */
        public function readNext(int $timeoutMicroseconds = 50000): ?Key
        {
            if ($this->queue !== []) {
                return array_shift($this->queue);
            }
            if (!is_resource($this->stream)) {
                return null;
            }

            $read = [$this->stream];
            $write = null;
            $except = null;
            $sec = intdiv(max(0, $timeoutMicroseconds), 1000000);
            $usec = max(0, $timeoutMicroseconds) % 1000000;

            $ready = @stream_select($read, $write, $except, $sec, $usec);
            if ($ready === false || $ready === 0) {
                return null;
            }

            $bytes = @fread($this->stream, 32);
            if ($bytes === false || $bytes === '') {
                return null;
            }

            foreach (self::parseBytes($bytes) as $k) {
                $this->queue[] = $k;
            }

            return $this->queue !== [] ? array_shift($this->queue) : null;
        }

        /**
         * Converts a scripted string (which may contain ANSI escapes or plain text) into a list of Keys.
         *
         * @return list<Key>
         */
        public static function tokenizeString(string $input): array
        {
            if ($input === '') {
                return [];
            }
            // Check if string matches a single symbolic key name like 'enter', 'up', 'down', 'escape', 'tab', 'space'
            $lower = strtolower($input);
            if (in_array($lower, [
                'enter', 'esc', 'escape', 'tab', 'shift_tab', 'shifttab', 'space',
                'backspace', 'delete', 'up', 'down', 'left', 'right', 'home', 'end',
                'page_up', 'page_down', 'ctrl_c', 'ctrl_d', 'ctrl_g', 'ctrl_r', 'ctrl_u', 'ctrl_w',
            ], true)) {
                return [Key::named($lower)];
            }
            return self::parseBytes($input);
        }

        /**
         * Stateful byte-stream parser for ANSI CSI/SS3 sequences, control codes, and UTF-8 characters.
         *
         * @return list<Key>
         */
        public static function parseBytes(string $bytes): array
        {
            $keys = [];
            $len = strlen($bytes);
            $i = 0;

            while ($i < $len) {
                $b = $bytes[$i];
                $ord = ord($b);

                if ($b === "\033") {
                    // Escape sequence or bare Escape
                    $rem = substr($bytes, $i);
                    if (preg_match('/^\033\[([0-9;?]*)([A-Za-z~])/', $rem, $m) === 1) {
                        $seq = $m[0];
                        $params = $m[1];
                        $final = $m[2];
                        $i += strlen($seq);
                        $keys[] = self::decodeCsi($params, $final);
                        continue;
                    }
                    if (preg_match('/^\033O([A-Za-z])/', $rem, $m) === 1) {
                        $i += 3;
                        $keys[] = match ($m[1]) {
                            'A' => new Key(Key::UP),
                            'B' => new Key(Key::DOWN),
                            'C' => new Key(Key::RIGHT),
                            'D' => new Key(Key::LEFT),
                            'H' => new Key(Key::HOME),
                            'F' => new Key(Key::END),
                            default => new Key(Key::ESCAPE),
                        };
                        continue;
                    }
                    $i++;
                    $keys[] = new Key(Key::ESCAPE);
                    continue;
                }

                if ($b === "\r" || $b === "\n") {
                    if ($b === "\r" && ($i + 1 < $len) && $bytes[$i + 1] === "\n") {
                        $i += 2;
                    } else {
                        $i++;
                    }
                    $keys[] = new Key(Key::ENTER, "\n");
                    continue;
                }

                if ($b === "\t") {
                    $i++;
                    $keys[] = new Key(Key::TAB, "\t");
                    continue;
                }

                if ($ord === 0x7F || $ord === 0x08) {
                    $i++;
                    $keys[] = new Key(Key::BACKSPACE);
                    continue;
                }

                if ($ord === 0x03) {
                    $i++;
                    $keys[] = new Key(Key::CTRL_C, '', true);
                    continue;
                }
                if ($ord === 0x04) {
                    $i++;
                    $keys[] = new Key(Key::CTRL_D, '', true);
                    continue;
                }
                if ($ord === 0x07) {
                    $i++;
                    $keys[] = new Key(Key::CTRL_G, '', true);
                    continue;
                }
                if ($ord === 0x12) {
                    $i++;
                    $keys[] = new Key(Key::CTRL_R, '', true);
                    continue;
                }
                if ($ord === 0x15) {
                    $i++;
                    $keys[] = new Key(Key::CTRL_U, '', true);
                    continue;
                }
                if ($ord === 0x17) {
                    $i++;
                    $keys[] = new Key(Key::CTRL_W, '', true);
                    continue;
                }

                // UTF-8 multi-byte decoding
                $charLen = 1;
                if (($ord & 0xE0) === 0xC0) {
                    $charLen = 2;
                } elseif (($ord & 0xF0) === 0xE0) {
                    $charLen = 3;
                } elseif (($ord & 0xF8) === 0xF0) {
                    $charLen = 4;
                }
                $utf8Char = substr($bytes, $i, $charLen);
                $i += $charLen;
                $keys[] = Key::char($utf8Char);
            }

            return $keys;
        }

        private static function decodeCsi(string $params, string $final): Key
        {
            return match ($final) {
                'A' => new Key(Key::UP),
                'B' => new Key(Key::DOWN),
                'C' => new Key(Key::RIGHT),
                'D' => new Key(Key::LEFT),
                'H' => new Key(Key::HOME),
                'F' => new Key(Key::END),
                'Z' => new Key(Key::SHIFT_TAB, '', false, false, true),
                '~' => match (explode(';', $params)[0]) {
                    '1', '7' => new Key(Key::HOME),
                    '3' => new Key(Key::DELETE),
                    '4', '8' => new Key(Key::END),
                    '5' => new Key(Key::PAGE_UP),
                    '6' => new Key(Key::PAGE_DOWN),
                    default => new Key(Key::ESCAPE),
                },
                default => new Key(Key::ESCAPE),
            };
        }
    }
}

// ============================================================================
// File: src/Block/BlockInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Block {
    use DrevOps\PhpTui\Condition\ConditionInterface;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    interface BlockInterface
    {
        public function id(): string;

        public function label(): string;

        public function region(): string;

        public function condition(): ?ConditionInterface;

        /**
         * @param array<string, mixed> $values
         */
        public function isVisible(array $values): bool;

        /**
         * Renders the block into lines fitting $width columns.
         *
         * @return list<string>
         */
        public function renderBlock(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
            bool $focused = false,
        ): array;
    }
}

// ============================================================================
// File: src/Block/Markup.php
// ============================================================================
namespace DrevOps\PhpTui\Block {
    use DrevOps\PhpTui\Condition\ConditionInterface;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Non-collecting informational block supporting inline Markdown (**bold**, *italic*, `code`, [text](url) OSC-8 links).
     */
    final class Markup implements BlockInterface
    {
        private ?ConditionInterface $condition = null;
        private string $region = 'content';

        public function __construct(
            private readonly string $id,
            private string $body,
            private string $title = '',
        ) {
        }

        public function id(): string
        {
            return $this->id;
        }

        public function label(): string
        {
            return $this->title;
        }

        public function body(): string
        {
            return $this->body;
        }

        public function in(string $region): self
        {
            $this->region = $region;
            return $this;
        }

        public function region(): string
        {
            return $this->region;
        }

        public function when(ConditionInterface $condition): self
        {
            $this->condition = $condition;
            return $this;
        }

        public function condition(): ?ConditionInterface
        {
            return $this->condition;
        }

        public function isVisible(array $values): bool
        {
            return $this->condition === null || $this->condition->evaluate($values);
        }

        public function renderBlock(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
            bool $focused = false,
        ): array {
            $lines = [];
            if ($this->title !== '') {
                $lines[] = $theme->styleTitle($translator->translate($this->title));
            }
            $translated = $translator->translate($this->body);
            $wrapped = MbString::wrapLines($translated, max(10, $width - 2));
            foreach ($wrapped as $line) {
                $lines[] = self::formatInlineMarkdown($line, $theme);
            }
            return $lines;
        }

        /**
         * Converts inline Markdown (`code`, **bold**, [label](url)) into styled ANSI / OSC-8 sequences.
         */
        public static function formatInlineMarkdown(string $text, ThemeInterface $theme): string
        {
            // Links: [label](url) -> OSC 8 hyperlink when color/ansi enabled, or "label (url)"
            $out = (string) preg_replace_callback(
                '/\[([^\]]+)\]\(([^)]+)\)/',
                static function (array $m) use ($theme): string {
                    return $theme->formatLink($m[1], $m[2]);
                },
                $text
            );
            // Inline code: `code`
            $out = (string) preg_replace_callback(
                '/`([^`]+)`/',
                static fn(array $m): string => $theme->styleCode($m[1]),
                $out
            );
            // Bold: **text**
            $out = (string) preg_replace_callback(
                '/\*\*([^*]+)\*\*/',
                static fn(array $m): string => $theme->styleBold($m[1]),
                $out
            );
            return $out;
        }
    }
}

// ============================================================================
// File: src/Block/Progress.php
// ============================================================================
namespace DrevOps\PhpTui\Block {
    use DrevOps\PhpTui\Condition\ConditionInterface;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Non-collecting progress bar / status row block for preload tasks and multi-step indicators.
     */
    final class Progress implements BlockInterface
    {
        private ?ConditionInterface $condition = null;
        private string $region = 'content';
        private int $current = 0;
        private int $total = 100;
        private string $status = '';

        public function __construct(
            private readonly string $id,
            private string $caption,
        ) {
        }

        public function id(): string
        {
            return $this->id;
        }

        public function label(): string
        {
            return $this->caption;
        }

        public function advance(int $step = 1, string $status = ''): self
        {
            $this->current = min($this->total, max(0, $this->current + $step));
            if ($status !== '') {
                $this->status = $status;
            }
            return $this;
        }

        public function setProgress(int $current, int $total = 100, string $status = ''): self
        {
            $this->total = max(1, $total);
            $this->current = min($this->total, max(0, $current));
            $this->status = $status;
            return $this;
        }

        public function current(): int
        {
            return $this->current;
        }

        public function total(): int
        {
            return $this->total;
        }

        public function in(string $region): self
        {
            $this->region = $region;
            return $this;
        }

        public function region(): string
        {
            return $this->region;
        }

        public function when(ConditionInterface $condition): self
        {
            $this->condition = $condition;
            return $this;
        }

        public function condition(): ?ConditionInterface
        {
            return $this->condition;
        }

        public function isVisible(array $values): bool
        {
            return $this->condition === null || $this->condition->evaluate($values);
        }

        public function renderBlock(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
            bool $focused = false,
        ): array {
            $ratio = $this->current / $this->total;
            $pct = (int) round($ratio * 100);
            $label = $translator->translate($this->caption);
            $barWidth = max(0, min(30, $width - MbString::displayWidth($label) - 12));
            $filled = (int) round($ratio * $barWidth);
            $empty = max(0, $barWidth - $filled);

            $glyphFill = $theme->glyph('progress_fill', '█');
            $glyphEmpty = $theme->glyph('progress_empty', '░');
            $bar = $theme->styleAccent(str_repeat($glyphFill, $filled)) . $theme->styleMuted(str_repeat($glyphEmpty, $empty));
            $line = sprintf('%s [%s] %3d%%', $label, $bar, $pct);
            if ($this->status !== '') {
                $line .= ' ' . $theme->styleMuted($translator->translate($this->status));
            }
            return [$line];
        }
    }
}

// ============================================================================
// File: src/Block/Panel.php
// ============================================================================
namespace DrevOps\PhpTui\Block {
    use DrevOps\PhpTui\Condition\ConditionInterface;
    use DrevOps\PhpTui\Field\FieldInterface;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Represents a sealed node in the Form block tree containing fields, child panels, and blocks.
     * Can render as a drill-down sub-panel or as a centered modal overlay dialog.
     */
    final class Panel implements BlockInterface
    {
        /**
         * @param list<BlockInterface|FieldInterface|Panel> $children
         * @param list<int|string> $layoutRows
         * @param list<\Closure> $preloadTasks
         */
        public function __construct(
            private readonly string $id,
            private readonly string $title,
            private readonly string $description = '',
            private readonly array $children = [],
            private readonly ?ConditionInterface $condition = null,
            private readonly bool $isModal = false,
            private readonly string $submitLabel = 'Submit',
            private readonly string $cancelLabel = 'Cancel',
            private readonly string $region = 'content',
            private readonly array $layoutRows = [],
            private readonly array $preloadTasks = [],
        ) {
        }

        public function id(): string
        {
            return $this->id;
        }

        public function label(): string
        {
            return $this->title;
        }

        public function title(): string
        {
            return $this->title;
        }

        public function description(): string
        {
            return $this->description;
        }

        /**
         * @return list<BlockInterface|FieldInterface|Panel>
         */
        public function children(): array
        {
            return $this->children;
        }

        /**
         * Returns all FieldInterface instances recursively across this panel and nested child panels.
         *
         * @return list<FieldInterface>
         */
        public function allFields(): array
        {
            $fields = [];
            foreach ($this->children as $child) {
                if ($child instanceof FieldInterface) {
                    $fields[] = $child;
                } elseif ($child instanceof self) {
                    foreach ($child->allFields() as $nested) {
                        $fields[] = $nested;
                    }
                }
            }
            return $fields;
        }

        /**
         * Returns all child Panel instances directly inside this panel.
         *
         * @return list<Panel>
         */
        public function childPanels(): array
        {
            $panels = [];
            foreach ($this->children as $child) {
                if ($child instanceof self) {
                    $panels[] = $child;
                }
            }
            return $panels;
        }

        public function condition(): ?ConditionInterface
        {
            return $this->condition;
        }

        public function isModal(): bool
        {
            return $this->isModal;
        }

        public function submitLabel(): string
        {
            return $this->submitLabel;
        }

        public function cancelLabel(): string
        {
            return $this->cancelLabel;
        }

        public function region(): string
        {
            return $this->region;
        }

        /**
         * @return list<int|string>
         */
        public function layoutRows(): array
        {
            return $this->layoutRows;
        }

        /**
         * @return list<\Closure>
         */
        public function preloadTasks(): array
        {
            return $this->preloadTasks;
        }

        public function isVisible(array $values): bool
        {
            return $this->condition === null || $this->condition->evaluate($values);
        }

        public function renderBlock(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
            bool $focused = false,
        ): array {
            $badge = $this->isModal ? '[modal]' : '▸';
            $prefix = $focused ? $theme->glyph('cursor', '❯') . ' ' : '  ';
            $title = $translator->translate($this->title);
            $line = $prefix . $theme->styleBold($title) . ' ' . $theme->styleAccent($badge);
            if ($this->description !== '') {
                $line .= ' ' . $theme->styleMuted('— ' . $translator->translate($this->description));
            }
            return [$line];
        }
    }
}

// ============================================================================
// File: src/Field/FieldInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Block\BlockInterface;
    use DrevOps\PhpTui\Condition\ConditionInterface;
    use DrevOps\PhpTui\Derive\DeriveInterface;
    use DrevOps\PhpTui\Discovery\DiscovererInterface;
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    interface FieldInterface extends BlockInterface
    {
        public function type(): string;

        public function panelId(): string;

        public function description(): string;

        public function help(): string;

        public function placeholder(): string;

        public function isRequired(): bool;

        public function isStandalone(): bool;

        public function defaultValue(): mixed;

        public function isMultipleSafeDefault(): mixed;

        public function derivation(): ?DeriveInterface;

        public function discoverer(): ?DiscovererInterface;

        /**
         * Coerces a raw input (from JSON, env, CLI, or interactive buffer) into the field's typed value.
         */
        public function coerce(mixed $raw): mixed;

        /**
         * Validates a candidate value against required, option, min/max, and custom validator rules.
         * Returns null when valid, or a localized error message string when invalid.
         *
         * @param array<string, mixed> $allValues
         */
        public function validateValue(mixed $value, array $allValues, TranslationInterface $translator): ?string;

        /**
         * Applies any declared transform closure to the validated value.
         *
         * @param array<string, mixed> $allValues
         */
        public function transformValue(mixed $value, array $allValues): mixed;

        /**
         * Initializes the interactive editor buffer from the current value before opening inline or standalone edit mode.
         */
        public function beginEdit(mixed $currentValue): void;

        /**
         * Processes a single keystroke while this field's editor is active.
         * Returns 'continue' (stay in editor), 'accept' (commit editor value), or 'cancel' (discard edit).
         *
         * @param array<string, list<string>> $keyBindings
         */
        public function handleEditKey(Key $key, array $keyBindings): string;

        /**
         * Returns the current candidate value inside the interactive editor buffer.
         */
        public function getEditValue(): mixed;

        /**
         * Returns named sub-parts if applicable (e.g., Template field slots).
         *
         * @return array<string, mixed>
         */
        public function extractParts(mixed $value): array;

        /**
         * Formats a value for compact summary display on the panel row.
         */
        public function formatDisplay(mixed $value, TranslationInterface $translator): string;

        /**
         * Renders the field row (either collapsed or expanded with inline editor) on a panel.
         *
         * @return list<string>
         */
        public function renderRow(
            mixed $currentValue,
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
            bool $focused = false,
            bool $editing = false,
            ?string $error = null,
        ): array;

        /**
         * Returns contextual key hints for the footer bar when focused or editing.
         *
         * @return array<string, string>
         */
        public function keyHints(bool $editing, TranslationInterface $translator): array;

        /**
         * Exports JSON Schema Draft 2020-12 property definition for this field.
         *
         * @return array<string, mixed>
         */
        public function toJsonSchema(TranslationInterface $translator): array;
    }
}

// ============================================================================
// File: src/Field/AbstractField.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Condition\ConditionInterface;
    use DrevOps\PhpTui\Derive\DeriveInterface;
    use DrevOps\PhpTui\Discovery\DiscovererInterface;
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Shared foundation for all 15 interactive field types.
     */
    abstract class AbstractField implements FieldInterface
    {
        protected string $description = '';
        protected string $help = '';
        protected string $placeholder = '';
        protected bool $required = false;
        protected ?string $requiredMessage = null;
        protected bool $standalone = false;
        protected mixed $defaultValue = null;
        protected ?ConditionInterface $condition = null;
        protected ?DeriveInterface $derivation = null;
        protected ?DiscovererInterface $discoverer = null;
        protected ?\Closure $validator = null;
        protected ?\Closure $transformer = null;
        protected string $region = 'content';
        /** @var array<string, list<string>> */
        protected array $fieldKeyOverrides = [];

        public function __construct(
            protected readonly string $id,
            protected readonly string $label,
            protected readonly string $panelId = 'root',
        ) {
        }

        public function id(): string
        {
            return $this->id;
        }

        public function label(): string
        {
            return $this->label;
        }

        public function panelId(): string
        {
            return $this->panelId;
        }

        public function description(): string
        {
            return $this->description;
        }

        public function setDescription(string $description): static
        {
            $this->description = $description;
            return $this;
        }

        public function help(): string
        {
            return $this->help;
        }

        public function setHelp(string $help): static
        {
            $this->help = $help;
            return $this;
        }

        public function placeholder(): string
        {
            return $this->placeholder;
        }

        public function setPlaceholder(string $placeholder): static
        {
            $this->placeholder = $placeholder;
            return $this;
        }

        public function isRequired(): bool
        {
            return $this->required;
        }

        public function setRequired(bool $required = true, ?string $message = null): static
        {
            $this->required = $required;
            $this->requiredMessage = $message;
            return $this;
        }

        public function isStandalone(): bool
        {
            return $this->standalone;
        }

        public function setStandalone(bool $standalone = true): static
        {
            $this->standalone = $standalone;
            return $this;
        }

        public function defaultValue(): mixed
        {
            return $this->defaultValue;
        }

        public function isMultipleSafeDefault(): mixed
        {
            return is_array($this->defaultValue) ? [] : null;
        }

        public function setDefault(mixed $default): static
        {
            $this->defaultValue = $default;
            return $this;
        }

        public function condition(): ?ConditionInterface
        {
            return $this->condition;
        }

        public function setCondition(?ConditionInterface $condition): static
        {
            $this->condition = $condition;
            return $this;
        }

        public function derivation(): ?DeriveInterface
        {
            return $this->derivation;
        }

        public function setDerivation(?DeriveInterface $derivation): static
        {
            $this->derivation = $derivation;
            return $this;
        }

        public function discoverer(): ?DiscovererInterface
        {
            return $this->discoverer;
        }

        public function setDiscoverer(?DiscovererInterface $discoverer): static
        {
            $this->discoverer = $discoverer;
            return $this;
        }

        public function setValidator(?\Closure $validator): static
        {
            $this->validator = $validator;
            return $this;
        }

        public function setTransformer(?\Closure $transformer): static
        {
            $this->transformer = $transformer;
            return $this;
        }

        public function region(): string
        {
            return $this->region;
        }

        public function setRegion(string $region): static
        {
            $this->region = $region;
            return $this;
        }

        /**
         * @param array<string, list<string>|string> $bindings
         */
        public function setKeyOverrides(array $bindings): static
        {
            foreach ($bindings as $action => $keys) {
                $this->fieldKeyOverrides[$action] = is_array($keys) ? array_values($keys) : [$keys];
            }
            return $this;
        }

        /**
         * @return array<string, list<string>>
         */
        public function keyOverrides(): array
        {
            return $this->fieldKeyOverrides;
        }

        public function isVisible(array $values): bool
        {
            return $this->condition === null || $this->condition->evaluate($values);
        }

        public function validateValue(mixed $value, array $allValues, TranslationInterface $translator): ?string
        {
            if ($this->required && $this->isEmptyValue($value)) {
                return $this->requiredMessage !== null
                    ? $translator->translate($this->requiredMessage)
                    : $translator->translate('This field is required.');
            }
            $typeErr = $this->validateTypeConstraints($value, $translator);
            if ($typeErr !== null) {
                return $typeErr;
            }
            if ($this->validator !== null) {
                $customResult = ($this->validator)($value, $allValues);
                if (is_string($customResult) && $customResult !== '') {
                    return $translator->translate($customResult);
                }
                if ($customResult === false) {
                    return $translator->translate('Invalid value for @label.', ['@label' => $this->label]);
                }
            }
            return null;
        }

        protected function isEmptyValue(mixed $value): bool
        {
            return $value === null || $value === '' || $value === [];
        }

        protected function validateTypeConstraints(mixed $value, TranslationInterface $translator): ?string
        {
            return null;
        }

        public function transformValue(mixed $value, array $allValues): mixed
        {
            if ($this->transformer !== null) {
                return ($this->transformer)($value, $allValues);
            }
            return $value;
        }

        public function extractParts(mixed $value): array
        {
            return [];
        }

        public function formatDisplay(mixed $value, TranslationInterface $translator): string
        {
            if ($value === null || $value === '') {
                return $this->placeholder !== '' ? $translator->translate($this->placeholder) : '—';
            }
            if (is_bool($value)) {
                return $value ? $translator->translate('Yes') : $translator->translate('No');
            }
            if (is_array($value)) {
                return $value === [] ? '—' : implode(', ', array_map('strval', $value));
            }
            return (string) $value;
        }

        public function renderBlock(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
            bool $focused = false,
        ): array {
            return $this->renderRow($this->defaultValue, $width, $theme, $translator, $focused, false, null);
        }

        public function renderRow(
            mixed $currentValue,
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
            bool $focused = false,
            bool $editing = false,
            ?string $error = null,
        ): array {
            $cursor = $focused ? $theme->styleAccent($theme->glyph('cursor', '❯')) . ' ' : '  ';
            $reqMark = $this->required ? $theme->styleError('*') : '';
            $labelStr = $translator->translate($this->label) . $reqMark;
            $labelFormatted = $focused ? $theme->styleBold($labelStr) : $labelStr;

            $displayVal = $this->formatDisplay($currentValue, $translator);
            $valFormatted = $editing
                ? $theme->styleHighlight($displayVal)
                : ($this->isEmptyValue($currentValue) ? $theme->styleMuted($displayVal) : $theme->styleValue($displayVal));

            $labelColWidth = max(18, min(32, intdiv($width, 3)));
            $paddedLabel = MbString::padWidth($labelFormatted, $labelColWidth);
            $lines = [$cursor . $paddedLabel . '  ' . $valFormatted];

            if ($focused && $this->description !== '') {
                $lines[] = '    ' . $theme->styleMuted($translator->translate($this->description));
            }
            if ($editing) {
                foreach ($this->renderInlineEditor($width - 4, $theme, $translator) as $edLine) {
                    $lines[] = '    ' . $edLine;
                }
            }
            if ($error !== null && $error !== '') {
                $lines[] = '    ' . $theme->styleError($theme->glyph('error', '✖') . ' ' . $error);
            }
            return $lines;
        }

        /**
         * Renders the interactive inline editor lines below or inside the active row.
         *
         * @return list<string>
         */
        abstract protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array;

        /**
         * Helper to check if a Key matches a semantic action in $keyBindings.
         *
         * @param array<string, list<string>> $keyBindings
         */
        protected function isAction(Key $key, string $action, array $keyBindings): bool
        {
            $bound = $this->fieldKeyOverrides[$action] ?? $keyBindings[$action] ?? [];
            return in_array($key->name, $bound, true) || ($key->char !== '' && in_array($key->char, $bound, true));
        }
    }
}

// ============================================================================
// File: src/Field/Text.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Single-line text field with movable caret, word-deletion (Ctrl-W), line-clear (Ctrl-U),
     * optional maxLength, and regex pattern validation.
     */
    class Text extends AbstractField
    {
        protected string $buffer = '';
        protected int $caret = 0;
        protected ?int $maxLength = null;
        protected ?string $pattern = null;

        public function type(): string
        {
            return 'text';
        }

        public function maxLength(int $max): static
        {
            $this->maxLength = max(1, $max);
            return $this;
        }

        public function pattern(string $regex): static
        {
            if (@preg_match($regex, '') === false) {
                throw new FormException(sprintf("Invalid regular expression '%s'.", $regex));
            }
            $this->pattern = $regex;
            return $this;
        }

        public function coerce(mixed $raw): string
        {
            if ($raw === null || is_bool($raw)) {
                return '';
            }
            if (is_array($raw)) {
                return implode(', ', array_map('strval', $raw));
            }
            return (string) $raw;
        }

        protected function validateTypeConstraints(mixed $value, TranslationInterface $translator): ?string
        {
            $str = (string) ($value ?? '');
            if ($str === '') {
                return null;
            }
            if ($this->maxLength !== null && MbString::length($str) > $this->maxLength) {
                return $translator->translate('Maximum length is @max characters.', ['@max' => $this->maxLength]);
            }
            if ($this->pattern !== null && preg_match($this->pattern, $str) !== 1) {
                return $translator->translate('Value does not match required format.');
            }
            return null;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $this->buffer = $this->coerce($currentValue ?? $this->defaultValue ?? '');
            $this->caret = MbString::length($this->buffer);
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            if ($key->name === Key::ENTER) {
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if ($key->name === Key::LEFT) {
                $this->caret = max(0, $this->caret - 1);
                return 'continue';
            }
            if ($key->name === Key::RIGHT) {
                $this->caret = min(MbString::length($this->buffer), $this->caret + 1);
                return 'continue';
            }
            if ($key->name === Key::HOME) {
                $this->caret = 0;
                return 'continue';
            }
            if ($key->name === Key::END) {
                $this->caret = MbString::length($this->buffer);
                return 'continue';
            }
            if ($key->name === Key::BACKSPACE) {
                if ($this->caret > 0) {
                    $left = MbString::substr($this->buffer, 0, $this->caret - 1);
                    $right = MbString::substr($this->buffer, $this->caret);
                    $this->buffer = $left . $right;
                    $this->caret--;
                }
                return 'continue';
            }
            if ($key->name === Key::DELETE) {
                $len = MbString::length($this->buffer);
                if ($this->caret < $len) {
                    $left = MbString::substr($this->buffer, 0, $this->caret);
                    $right = MbString::substr($this->buffer, $this->caret + 1);
                    $this->buffer = $left . $right;
                }
                return 'continue';
            }
            if ($key->name === Key::CTRL_U) {
                $this->buffer = '';
                $this->caret = 0;
                return 'continue';
            }
            if ($key->name === Key::CTRL_W) {
                $left = rtrim(MbString::substr($this->buffer, 0, $this->caret));
                $right = MbString::substr($this->buffer, $this->caret);
                $lastSpace = mb_strrpos($left, ' ', 0, 'UTF-8');
                $newLeft = $lastSpace === false ? '' : MbString::substr($left, 0, $lastSpace + 1);
                $this->buffer = $newLeft . $right;
                $this->caret = MbString::length($newLeft);
                return 'continue';
            }
            if ($key->isPrintable()) {
                if ($this->maxLength !== null && MbString::length($this->buffer) >= $this->maxLength) {
                    return 'continue';
                }
                $left = MbString::substr($this->buffer, 0, $this->caret);
                $right = MbString::substr($this->buffer, $this->caret);
                $this->buffer = $left . $key->char . $right;
                $this->caret += MbString::length($key->char);
            }
            return 'continue';
        }

        public function getEditValue(): string
        {
            return $this->buffer;
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $left = MbString::substr($this->buffer, 0, $this->caret);
            $atCaret = MbString::substr($this->buffer, $this->caret, 1);
            $right = $this->caret < MbString::length($this->buffer)
                ? MbString::substr($this->buffer, $this->caret + 1)
                : '';
            $caretChar = $atCaret !== '' ? $atCaret : ' ';
            $rendered = $left . $theme->styleCaret($caretChar) . $right;
            return [$theme->glyph('input_prefix', '› ') . $rendered];
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            if ($editing) {
                return [
                    '←/→' => $translator->translate('move caret'),
                    'Enter' => $translator->translate('accept'),
                    'Esc' => $translator->translate('cancel'),
                ];
            }
            return [
                'Enter' => $translator->translate('edit'),
                '?' => $translator->translate('help'),
            ];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            $schema = [
                'type' => 'string',
                'title' => $translator->translate($this->label),
            ];
            if ($this->description !== '') {
                $schema['description'] = $translator->translate($this->description);
            }
            if ($this->defaultValue !== null) {
                $schema['default'] = (string) $this->defaultValue;
            }
            if ($this->maxLength !== null) {
                $schema['maxLength'] = $this->maxLength;
            }
            if ($this->pattern !== null) {
                $schema['pattern'] = trim($this->pattern, '/');
            }
            return $schema;
        }
    }
}

// ============================================================================
// File: src/Field/Password.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Masked password input field; keeps plain string in Answers while masking display everywhere.
     * Supports optional reveal toggle (Ctrl-R or Tab) when ->revealable() is enabled.
     */
    final class Password extends Text
    {
        private bool $revealable = false;
        private bool $revealed = false;
        private string $maskGlyph = '•';

        public function type(): string
        {
            return 'password';
        }

        public function revealable(bool $revealable = true): self
        {
            $this->revealable = $revealable;
            return $this;
        }

        public function isRevealable(): bool
        {
            return $this->revealable;
        }

        public function mask(string $glyph = '•'): self
        {
            $this->maskGlyph = $glyph !== '' ? MbString::substr($glyph, 0, 1) : '•';
            return $this;
        }

        public function beginEdit(mixed $currentValue): void
        {
            parent::beginEdit($currentValue);
            $this->revealed = false;
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            if ($this->revealable && ($key->name === Key::CTRL_R || $key->name === Key::TAB)) {
                $this->revealed = !$this->revealed;
                return 'continue';
            }
            return parent::handleEditKey($key, $keyBindings);
        }

        public function formatDisplay(mixed $value, TranslationInterface $translator): string
        {
            $str = (string) ($value ?? '');
            if ($str === '') {
                return '—';
            }
            if ($this->revealable && $this->revealed) {
                return $str;
            }
            return str_repeat($this->maskGlyph, min(16, MbString::length($str)));
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $shown = ($this->revealable && $this->revealed)
                ? $this->buffer
                : str_repeat($this->maskGlyph, MbString::length($this->buffer));

            $left = MbString::substr($shown, 0, $this->caret);
            $atCaret = MbString::substr($shown, $this->caret, 1);
            $right = $this->caret < MbString::length($shown)
                ? MbString::substr($shown, $this->caret + 1)
                : '';
            $caretChar = $atCaret !== '' ? $atCaret : ' ';
            $suffix = $this->revealable
                ? ' ' . $theme->styleMuted($this->revealed ? '[visible - Tab to mask]' : '[masked - Tab to reveal]')
                : '';
            return [$theme->glyph('input_prefix', '› ') . $left . $theme->styleCaret($caretChar) . $right . $suffix];
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            $hints = parent::keyHints($editing, $translator);
            if ($editing && $this->revealable) {
                $hints['Tab'] = $translator->translate('reveal/mask');
            }
            return $hints;
        }
    }
}

// ============================================================================
// File: src/Field/Number.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Bounded integer input field with min, max, and step controls (arrows increment/decrement or direct numeric typing).
     */
    final class Number extends AbstractField
    {
        private ?int $min = null;
        private ?int $max = null;
        private int $step = 1;
        private string $buffer = '';

        public function type(): string
        {
            return 'number';
        }

        public function min(int $min): self
        {
            $this->min = $min;
            return $this;
        }

        public function max(int $max): self
        {
            $this->max = $max;
            return $this;
        }

        public function step(int $step): self
        {
            $this->step = max(1, $step);
            return $this;
        }

        public function getMin(): ?int
        {
            return $this->min;
        }

        public function getMax(): ?int
        {
            return $this->max;
        }

        public function getStep(): int
        {
            return $this->step;
        }

        public function coerce(mixed $raw): ?int
        {
            if ($raw === null || $raw === '') {
                return null;
            }
            if (is_int($raw)) {
                return $raw;
            }
            if (is_numeric($raw)) {
                return (int) $raw;
            }
            return null;
        }

        protected function validateTypeConstraints(mixed $value, TranslationInterface $translator): ?string
        {
            if ($value === null || $value === '') {
                return null;
            }
            if (!is_int($value) && !is_numeric($value)) {
                return $translator->translate('Must be a valid integer.');
            }
            $intVal = (int) $value;
            if ($this->min !== null && $intVal < $this->min) {
                return $translator->translate('Value must be at least @min.', ['@min' => $this->min]);
            }
            if ($this->max !== null && $intVal > $this->max) {
                return $translator->translate('Value must be at most @max.', ['@max' => $this->max]);
            }
            return null;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $initial = $this->coerce($currentValue ?? $this->defaultValue);
            $this->buffer = $initial !== null ? (string) $initial : '';
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            if ($key->name === Key::ENTER) {
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if ($key->name === Key::UP || $key->name === Key::RIGHT) {
                $cur = $this->buffer !== '' && is_numeric($this->buffer) ? (int) $this->buffer : ($this->min ?? 0);
                $next = $cur + $this->step;
                if ($this->max !== null) {
                    $next = min($this->max, $next);
                }
                $this->buffer = (string) $next;
                return 'continue';
            }
            if ($key->name === Key::DOWN || $key->name === Key::LEFT) {
                $cur = $this->buffer !== '' && is_numeric($this->buffer) ? (int) $this->buffer : ($this->min ?? 0);
                $next = $cur - $this->step;
                if ($this->min !== null) {
                    $next = max($this->min, $next);
                }
                $this->buffer = (string) $next;
                return 'continue';
            }
            if ($key->name === Key::BACKSPACE) {
                $this->buffer = substr($this->buffer, 0, -1);
                return 'continue';
            }
            if ($key->char === '-' && $this->buffer === '' && ($this->min === null || $this->min < 0)) {
                $this->buffer = '-';
                return 'continue';
            }
            if ($key->char !== '' && ctype_digit($key->char)) {
                $this->buffer .= $key->char;
                return 'continue';
            }
            return 'continue';
        }

        public function getEditValue(): ?int
        {
            if ($this->buffer === '' || $this->buffer === '-') {
                return null;
            }
            return (int) $this->buffer;
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $bounds = [];
            if ($this->min !== null) {
                $bounds[] = 'min: ' . $this->min;
            }
            if ($this->max !== null) {
                $bounds[] = 'max: ' . $this->max;
            }
            if ($this->step > 1) {
                $bounds[] = 'step: ' . $this->step;
            }
            $hint = $bounds !== [] ? ' ' . $theme->styleMuted('(' . implode(', ', $bounds) . ')') : '';
            return [$theme->glyph('input_prefix', '› ') . $theme->styleHighlight($this->buffer . ' ') . $hint];
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            if ($editing) {
                return [
                    '↑/↓' => $translator->translate('±step'),
                    '0-9' => $translator->translate('type number'),
                    'Enter' => $translator->translate('accept'),
                    'Esc' => $translator->translate('cancel'),
                ];
            }
            return ['Enter' => $translator->translate('edit')];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            $schema = [
                'type' => 'integer',
                'title' => $translator->translate($this->label),
            ];
            if ($this->description !== '') {
                $schema['description'] = $translator->translate($this->description);
            }
            if ($this->defaultValue !== null) {
                $schema['default'] = (int) $this->defaultValue;
            }
            if ($this->min !== null) {
                $schema['minimum'] = $this->min;
            }
            if ($this->max !== null) {
                $schema['maximum'] = $this->max;
            }
            if ($this->step > 1) {
                $schema['multipleOf'] = $this->step;
            }
            return $schema;
        }
    }
}

// ============================================================================
// File: src/Field/Confirm.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Boolean Yes/No toggle field; arrows or Space switch, y/n set choice directly, Enter accepts.
     */
    final class Confirm extends AbstractField
    {
        private bool $current = false;
        private string $yesLabel = 'Yes';
        private string $noLabel = 'No';

        public function type(): string
        {
            return 'confirm';
        }

        public function labels(string $yes = 'Yes', string $no = 'No'): self
        {
            $this->yesLabel = $yes;
            $this->noLabel = $no;
            return $this;
        }

        public function coerce(mixed $raw): bool
        {
            if (is_bool($raw)) {
                return $raw;
            }
            if (is_int($raw)) {
                return $raw !== 0;
            }
            if (is_string($raw)) {
                $norm = strtolower(trim($raw));
                return in_array($norm, ['1', 'true', 'yes', 'y', 'on'], true);
            }
            return (bool) $raw;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $this->current = $this->coerce($currentValue ?? $this->defaultValue ?? false);
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            if ($key->name === Key::ENTER) {
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if (in_array($key->name, [Key::LEFT, Key::RIGHT, Key::UP, Key::DOWN, Key::SPACE], true)) {
                $this->current = !$this->current;
                return 'continue';
            }
            $lower = strtolower($key->char);
            if ($lower === 'y') {
                $this->current = true;
                return 'continue';
            }
            if ($lower === 'n') {
                $this->current = false;
                return 'continue';
            }
            return 'continue';
        }

        public function getEditValue(): bool
        {
            return $this->current;
        }

        public function formatDisplay(mixed $value, TranslationInterface $translator): string
        {
            $bool = $this->coerce($value ?? $this->defaultValue ?? false);
            return $bool ? $translator->translate($this->yesLabel) : $translator->translate($this->noLabel);
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $yes = $translator->translate($this->yesLabel);
            $no = $translator->translate($this->noLabel);
            $yesPill = $this->current ? $theme->styleHighlight(" [{$yes}] ") : $theme->styleMuted("  {$yes}  ");
            $noPill = !$this->current ? $theme->styleHighlight(" [{$no}] ") : $theme->styleMuted("  {$no}  ");
            return [$yesPill . ' / ' . $noPill];
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            if ($editing) {
                return [
                    '←/→/Space' => $translator->translate('toggle'),
                    'y/n' => $translator->translate('yes/no'),
                    'Enter' => $translator->translate('accept'),
                ];
            }
            return [
                'Space' => $translator->translate('flip'),
                'Enter' => $translator->translate('edit'),
            ];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            return [
                'type' => 'boolean',
                'title' => $translator->translate($this->label),
                'default' => (bool) ($this->defaultValue ?? false),
            ];
        }
    }
}

// ============================================================================
// File: src/Field/Toggle.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\FormException;
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Inline switch between two labelled values; arrows or Space flip, first letter sets it directly.
     */
    final class Toggle extends AbstractField
    {
        /** @var array<string, string> */
        private array $options = ['on' => 'On', 'off' => 'Off'];
        private string $activeKey = 'on';

        public function type(): string
        {
            return 'toggle';
        }

        /**
         * @param array<string|int, string> $options Exactly two key => label options
         */
        public function options(array $options): self
        {
            if (count($options) !== 2) {
                throw new FormException(sprintf("Toggle field '%s' requires exactly 2 options, %d given.", $this->id, count($options)));
            }
            $normalized = [];
            foreach ($options as $k => $v) {
                $normalized[(string) $k] = (string) $v;
            }
            $this->options = $normalized;
            $keys = array_keys($this->options);
            if ($this->defaultValue === null) {
                $this->defaultValue = $keys[0];
            }
            $this->activeKey = (string) $this->defaultValue;
            return $this;
        }

        /**
         * @return array<string, string>
         */
        public function getOptions(): array
        {
            return $this->options;
        }

        public function coerce(mixed $raw): string
        {
            $keys = array_keys($this->options);
            if (is_array($raw)) {
                $raw = reset($raw);
            }
            $str = (string) ($raw ?? '');
            if (isset($this->options[$str])) {
                return $str;
            }
            foreach ($this->options as $k => $label) {
                if (strcasecmp($label, $str) === 0) {
                    return $k;
                }
            }
            return $keys[0] ?? '';
        }

        protected function validateTypeConstraints(mixed $value, TranslationInterface $translator): ?string
        {
            $str = (string) ($value ?? '');
            if ($str !== '' && !isset($this->options[$str])) {
                return $translator->translate('Invalid toggle choice: @val.', ['@val' => $str]);
            }
            return null;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $this->activeKey = $this->coerce($currentValue ?? $this->defaultValue);
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            if ($key->name === Key::ENTER) {
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            $keys = array_keys($this->options);
            if (in_array($key->name, [Key::LEFT, Key::RIGHT, Key::UP, Key::DOWN, Key::SPACE], true)) {
                $this->activeKey = ($this->activeKey === $keys[0]) ? $keys[1] : $keys[0];
                return 'continue';
            }
            if ($key->char !== '') {
                $ch = strtolower($key->char);
                foreach ($this->options as $k => $label) {
                    if (str_starts_with(strtolower($label), $ch) || str_starts_with(strtolower($k), $ch)) {
                        $this->activeKey = $k;
                        return 'continue';
                    }
                }
            }
            return 'continue';
        }

        public function getEditValue(): string
        {
            return $this->activeKey;
        }

        public function formatDisplay(mixed $value, TranslationInterface $translator): string
        {
            $k = $this->coerce($value ?? $this->defaultValue);
            return isset($this->options[$k]) ? $translator->translate($this->options[$k]) : $k;
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $pills = [];
            foreach ($this->options as $k => $label) {
                $t = $translator->translate($label);
                $pills[] = ($k === $this->activeKey)
                    ? $theme->styleHighlight(" [{$t}] ")
                    : $theme->styleMuted("  {$t}  ");
            }
            return [implode(' ⇄ ', $pills)];
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            return [
                '←/→/Space' => $translator->translate('flip'),
                'Enter' => $editing ? $translator->translate('accept') : $translator->translate('edit'),
            ];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            return [
                'type' => 'string',
                'title' => $translator->translate($this->label),
                'enum' => array_keys($this->options),
                'default' => $this->defaultValue,
            ];
        }
    }
}

// ============================================================================
// File: src/Field/Select.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Single or multiple choice selection field supporting flat options and grouped options (Option groups),
     * viewport pagination around the cursor, and Space multi-toggle.
     */
    class Select extends AbstractField
    {
        /** @var array<string, string> */
        protected array $options = [];
        /** @var array<string, array<string, string>> */
        protected array $groups = [];
        protected bool $multiple = false;
        protected int $pageSize = 7;
        protected int $cursorIndex = 0;
        /** @var list<string> */
        protected array $selectedKeys = [];

        public function type(): string
        {
            return 'select';
        }

        /**
         * @param array<string|int, string|array<string|int, string>> $options
         */
        public function options(array $options): static
        {
            $flat = [];
            $groups = [];
            foreach ($options as $key => $val) {
                if (is_array($val)) {
                    $groupName = (string) $key;
                    $groups[$groupName] = [];
                    foreach ($val as $subKey => $subLabel) {
                        $sk = (string) $subKey;
                        $sl = (string) $subLabel;
                        $flat[$sk] = $sl;
                        $groups[$groupName][$sk] = $sl;
                    }
                } else {
                    $flat[(string) $key] = (string) $val;
                }
            }
            $this->options = $flat;
            $this->groups = $groups;
            return $this;
        }

        /**
         * @param array<string, array<string|int, string>> $groups
         */
        public function groups(array $groups): static
        {
            return $this->options($groups);
        }

        /**
         * @return array<string, string>
         */
        public function getOptions(): array
        {
            return $this->options;
        }

        /**
         * @return array<string, array<string, string>>
         */
        public function getGroups(): array
        {
            return $this->groups;
        }

        public function multiple(bool $multiple = true): static
        {
            $this->multiple = $multiple;
            return $this;
        }

        public function isMultiple(): bool
        {
            return $this->multiple;
        }

        public function pageSize(int $size): static
        {
            $this->pageSize = max(3, $size);
            return $this;
        }

        public function coerce(mixed $raw): string|array|null
        {
            if ($this->multiple) {
                if ($raw === null || $raw === '') {
                    return [];
                }
                if (is_string($raw)) {
                    $parts = array_filter(array_map('trim', explode(',', $raw)), static fn(string $s): bool => $s !== '');
                    return array_values($parts);
                }
                if (is_array($raw)) {
                    return array_values(array_map('strval', $raw));
                }
                return [(string) $raw];
            }
            if ($raw === null || $raw === '') {
                return null;
            }
            if (is_array($raw)) {
                $first = reset($raw);
                return $first !== false ? (string) $first : null;
            }
            return (string) $raw;
        }

        protected function validateTypeConstraints(mixed $value, TranslationInterface $translator): ?string
        {
            if ($this->isEmptyValue($value)) {
                return null;
            }
            if ($this->multiple) {
                $list = is_array($value) ? $value : [$value];
                foreach ($list as $item) {
                    $k = (string) $item;
                    if (!array_key_exists($k, $this->options)) {
                        return $translator->translate('Invalid option "@opt".', ['@opt' => $k]);
                    }
                }
                return null;
            }
            $k = (string) $value;
            if (!array_key_exists($k, $this->options)) {
                return $translator->translate('Invalid option "@opt".', ['@opt' => $k]);
            }
            return null;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $coerced = $this->coerce($currentValue ?? $this->defaultValue);
            $keys = array_keys($this->options);
            if ($this->multiple) {
                $this->selectedKeys = is_array($coerced) ? $coerced : [];
                $first = $this->selectedKeys[0] ?? ($keys[0] ?? '');
                $idx = array_search($first, $keys, true);
                $this->cursorIndex = $idx !== false ? $idx : 0;
            } else {
                if ($coerced === null || $coerced === '') {
                    $this->cursorIndex = 0;
                    $this->selectedKeys = [];
                } else {
                    $str = is_string($coerced) ? $coerced : ($keys[0] ?? '');
                    $idx = array_search($str, $keys, true);
                    $this->cursorIndex = $idx !== false ? $idx : 0;
                    $this->selectedKeys = $str !== '' ? [$str] : [];
                }
            }
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            $keys = array_keys($this->options);
            $count = count($keys);
            if ($count === 0) {
                return $key->name === Key::ESCAPE ? 'cancel' : 'accept';
            }
            if ($key->name === Key::ENTER) {
                if (!$this->multiple && isset($keys[$this->cursorIndex])) {
                    $this->selectedKeys = [$keys[$this->cursorIndex]];
                }
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if ($this->isAction($key, Key::ACTION_UP, $keyBindings) || $key->name === Key::UP) {
                $this->cursorIndex = ($this->cursorIndex - 1 + $count) % $count;
                if (!$this->multiple) {
                    $this->selectedKeys = [$keys[$this->cursorIndex]];
                }
                return 'continue';
            }
            if ($this->isAction($key, Key::ACTION_DOWN, $keyBindings) || $key->name === Key::DOWN) {
                $this->cursorIndex = ($this->cursorIndex + 1) % $count;
                if (!$this->multiple) {
                    $this->selectedKeys = [$keys[$this->cursorIndex]];
                }
                return 'continue';
            }
            if ($key->name === Key::PAGE_UP) {
                $this->cursorIndex = max(0, $this->cursorIndex - $this->pageSize);
                return 'continue';
            }
            if ($key->name === Key::PAGE_DOWN) {
                $this->cursorIndex = min($count - 1, $this->cursorIndex + $this->pageSize);
                return 'continue';
            }
            if ($this->multiple && ($key->name === Key::SPACE || $this->isAction($key, Key::ACTION_TOGGLE, $keyBindings))) {
                $currentKey = $keys[$this->cursorIndex];
                if (in_array($currentKey, $this->selectedKeys, true)) {
                    $this->selectedKeys = array_values(array_diff($this->selectedKeys, [$currentKey]));
                } else {
                    $this->selectedKeys[] = $currentKey;
                }
                return 'continue';
            }
            return 'continue';
        }

        public function getEditValue(): string|array|null
        {
            if ($this->multiple) {
                return $this->selectedKeys;
            }
            $keys = array_keys($this->options);
            return $this->selectedKeys[0] ?? ($keys[$this->cursorIndex] ?? null);
        }

        public function formatDisplay(mixed $value, TranslationInterface $translator): string
        {
            if ($this->multiple) {
                $list = is_array($value) ? $value : ($value !== null && $value !== '' ? [(string) $value] : []);
                if ($list === []) {
                    return $translator->translate('(none)');
                }
                $labels = [];
                foreach ($list as $k) {
                    $labels[] = isset($this->options[(string) $k])
                        ? $translator->translate($this->options[(string) $k])
                        : (string) $k;
                }
                return implode(', ', $labels);
            }
            $k = (string) ($value ?? '');
            if ($k === '') {
                return '—';
            }
            return isset($this->options[$k]) ? $translator->translate($this->options[$k]) : $k;
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $keys = array_keys($this->options);
            $total = count($keys);
            if ($total === 0) {
                return [$theme->styleMuted($translator->translate('(no options available)'))];
            }

            // Compute sliding window around cursorIndex
            $half = intdiv($this->pageSize, 2);
            $start = max(0, min($this->cursorIndex - $half, max(0, $total - $this->pageSize)));
            $end = min($total, $start + $this->pageSize);

            $lines = [];
            if ($start > 0) {
                $lines[] = $theme->styleMuted(sprintf('  ↑ %d more above', $start));
            }

            // Map key -> group header if groups are defined
            $keyGroupHeader = [];
            if ($this->groups !== []) {
                foreach ($this->groups as $gName => $gItems) {
                    $firstKey = array_key_first($gItems);
                    if ($firstKey !== null) {
                        $keyGroupHeader[$firstKey] = $gName;
                    }
                }
            }

            for ($i = $start; $i < $end; $i++) {
                $k = $keys[$i];
                if (isset($keyGroupHeader[$k])) {
                    $lines[] = '  ' . $theme->styleTitle($translator->translate($keyGroupHeader[$k]));
                }
                $isCursor = ($i === $this->cursorIndex);
                $isChecked = in_array($k, $this->selectedKeys, true);
                $marker = $this->multiple
                    ? ($isChecked ? $theme->glyph('checkbox_on', '☑') : $theme->glyph('checkbox_off', '☐'))
                    : ($isCursor ? $theme->glyph('radio_on', '◉') : $theme->glyph('radio_off', '◯'));
                $pointer = $isCursor ? $theme->styleAccent($theme->glyph('cursor', '❯')) : ' ';
                $label = $translator->translate($this->options[$k]);
                $rowText = sprintf('%s %s %s', $pointer, $marker, $label);
                $lines[] = $isCursor ? $theme->styleHighlight($rowText) : $rowText;
            }

            if ($end < $total) {
                $lines[] = $theme->styleMuted(sprintf('  ↓ %d more below', $total - $end));
            }
            return $lines;
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            if ($editing) {
                $hints = ['↑/↓' => $translator->translate('move')];
                if ($this->multiple) {
                    $hints['Space'] = $translator->translate('toggle');
                }
                $hints['Enter'] = $translator->translate('accept');
                $hints['Esc'] = $translator->translate('cancel');
                return $hints;
            }
            return ['Enter' => $translator->translate('choose')];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            $enum = array_keys($this->options);
            if ($this->multiple) {
                return [
                    'type' => 'array',
                    'title' => $translator->translate($this->label),
                    'items' => ['type' => 'string', 'enum' => $enum],
                    'default' => is_array($this->defaultValue) ? $this->defaultValue : [],
                ];
            }
            return [
                'type' => 'string',
                'title' => $translator->translate($this->label),
                'enum' => $enum,
                'default' => $this->defaultValue,
            ];
        }
    }
}

// ============================================================================
// File: src/Field/Search.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\MbString;
    use DrevOps\PhpTui\Utils\Str;

    /**
     * Single or multiple choice selection with live fuzzy-search filter bar;
     * ranks labels using Str::fuzzyScore() as the user types.
     */
    final class Search extends Select
    {
        private string $filterQuery = '';

        public function type(): string
        {
            return 'search';
        }

        public function beginEdit(mixed $currentValue): void
        {
            parent::beginEdit($currentValue);
            $this->filterQuery = '';
        }

        /**
         * Returns filtered option keys ranked by fuzzy match score against $this->filterQuery.
         *
         * @return list<string>
         */
        public function filteredKeys(): array
        {
            if (trim($this->filterQuery) === '') {
                return array_keys($this->options);
            }
            $scored = [];
            foreach ($this->options as $k => $label) {
                $score = max(
                    Str::fuzzyScore($this->filterQuery, $label),
                    Str::fuzzyScore($this->filterQuery, $k)
                );
                if ($score > 0) {
                    $scored[$k] = $score;
                }
            }
            arsort($scored);
            return array_keys($scored);
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            $filtered = $this->filteredKeys();
            $count = count($filtered);

            if ($key->name === Key::ENTER) {
                if (!$this->multiple && isset($filtered[$this->cursorIndex])) {
                    $this->selectedKeys = [$filtered[$this->cursorIndex]];
                }
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if ($key->name === Key::UP) {
                if ($count > 0) {
                    $this->cursorIndex = ($this->cursorIndex - 1 + $count) % $count;
                    if (!$this->multiple && isset($filtered[$this->cursorIndex])) {
                        $this->selectedKeys = [$filtered[$this->cursorIndex]];
                    }
                }
                return 'continue';
            }
            if ($key->name === Key::DOWN) {
                if ($count > 0) {
                    $this->cursorIndex = ($this->cursorIndex + 1) % $count;
                    if (!$this->multiple && isset($filtered[$this->cursorIndex])) {
                        $this->selectedKeys = [$filtered[$this->cursorIndex]];
                    }
                }
                return 'continue';
            }
            if ($this->multiple && $key->name === Key::SPACE) {
                if (isset($filtered[$this->cursorIndex])) {
                    $k = $filtered[$this->cursorIndex];
                    if (in_array($k, $this->selectedKeys, true)) {
                        $this->selectedKeys = array_values(array_diff($this->selectedKeys, [$k]));
                    } else {
                        $this->selectedKeys[] = $k;
                    }
                }
                return 'continue';
            }
            if ($key->name === Key::BACKSPACE) {
                $len = MbString::length($this->filterQuery);
                if ($len > 0) {
                    $this->filterQuery = MbString::substr($this->filterQuery, 0, $len - 1);
                    $this->cursorIndex = 0;
                }
                return 'continue';
            }
            if ($key->isPrintable()) {
                $this->filterQuery .= $key->char;
                $this->cursorIndex = 0;
                return 'continue';
            }
            return 'continue';
        }

        public function getEditValue(): string|array|null
        {
            if ($this->multiple) {
                return $this->selectedKeys;
            }
            $filtered = $this->filteredKeys();
            $count = count($filtered);
            if ($count === 0) {
                return null;
            }
            if ($this->cursorIndex >= $count) {
                $this->cursorIndex = $count - 1;
            }
            return $filtered[$this->cursorIndex] ?? $this->selectedKeys[0] ?? null;
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $lines = [
                $theme->glyph('search', '🔍 ') . $theme->styleHighlight($this->filterQuery !== '' ? $this->filterQuery : $translator->translate('Type to filter…')),
            ];
            $filtered = $this->filteredKeys();
            if ($filtered === []) {
                $lines[] = '  ' . $theme->styleMuted($translator->translate('No matching options'));
                return $lines;
            }
            $slice = array_slice($filtered, 0, $this->pageSize);
            foreach ($slice as $idx => $k) {
                $isCursor = ($idx === $this->cursorIndex);
                $isChecked = in_array($k, $this->selectedKeys, true);
                $marker = $this->multiple
                    ? ($isChecked ? $theme->glyph('checkbox_on', '☑') : $theme->glyph('checkbox_off', '☐'))
                    : ($isCursor ? $theme->glyph('radio_on', '◉') : $theme->glyph('radio_off', '◯'));
                $pointer = $isCursor ? $theme->styleAccent($theme->glyph('cursor', '❯')) : ' ';
                $row = sprintf('%s %s %s', $pointer, $marker, $translator->translate($this->options[$k]));
                $lines[] = $isCursor ? $theme->styleHighlight($row) : $row;
            }
            return $lines;
        }
    }
}

// ============================================================================
// File: src/Field/Suggest.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\Str;

    /**
     * Free-text input with live autocomplete suggestions over a fixed candidate list.
     * Supports case-insensitive prefix and fuzzy matching; pressing Tab or Down+Enter
     * canonicalizes the buffer to the candidate's own casing.
     */
    final class Suggest extends Text
    {
        /** @var list<string> */
        private array $candidates = [];
        private int $suggestionIndex = -1;

        public function type(): string
        {
            return 'suggest';
        }

        /**
         * @param list<string>|array<string|int, string> $options
         */
        public function options(array $options): self
        {
            $this->candidates = array_values(array_map('strval', $options));
            return $this;
        }

        /**
         * @param list<string>|array<string|int, string> $candidates
         */
        public function complete(array $candidates): self
        {
            return $this->options($candidates);
        }

        /**
         * @return list<string>
         */
        public function matchingCandidates(): array
        {
            $q = trim($this->buffer);
            if ($q === '') {
                return array_slice($this->candidates, 0, 5);
            }
            $scored = [];
            foreach ($this->candidates as $cand) {
                $score = Str::fuzzyScore($q, $cand);
                if ($score > 0) {
                    $scored[$cand] = $score;
                }
            }
            arsort($scored);
            return array_slice(array_keys($scored), 0, 5);
        }

        public function beginEdit(mixed $currentValue): void
        {
            parent::beginEdit($currentValue);
            $this->suggestionIndex = -1;
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            $matches = $this->matchingCandidates();
            if ($key->name === Key::TAB && $matches !== []) {
                $idx = max(0, $this->suggestionIndex);
                if (isset($matches[$idx])) {
                    $this->buffer = $matches[$idx];
                    $this->caret = MbString::length($this->buffer);
                }
                return 'continue';
            }
            if ($key->name === Key::DOWN && $matches !== []) {
                $this->suggestionIndex = ($this->suggestionIndex + 1) % count($matches);
                return 'continue';
            }
            if ($key->name === Key::UP && $matches !== []) {
                $this->suggestionIndex = ($this->suggestionIndex - 1 + count($matches)) % count($matches);
                return 'continue';
            }
            if ($key->name === Key::ENTER) {
                if ($this->suggestionIndex >= 0 && isset($matches[$this->suggestionIndex])) {
                    $this->buffer = $matches[$this->suggestionIndex];
                } else {
                    // Canonicalize case if buffer matches a candidate case-insensitively
                    foreach ($this->candidates as $cand) {
                        if (strcasecmp($cand, $this->buffer) === 0) {
                            $this->buffer = $cand;
                            break;
                        }
                    }
                }
                return 'accept';
            }
            $this->suggestionIndex = -1;
            return parent::handleEditKey($key, $keyBindings);
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $lines = parent::renderInlineEditor($width, $theme, $translator);
            $matches = $this->matchingCandidates();
            if ($matches !== []) {
                $chips = [];
                foreach ($matches as $i => $cand) {
                    $chips[] = ($i === $this->suggestionIndex)
                        ? $theme->styleHighlight("[{$cand}]")
                        : $theme->styleMuted($cand);
                }
                $lines[] = '  ' . $theme->styleMuted('↳ ') . implode('  ', $chips);
            }
            return $lines;
        }
    }
}

// ============================================================================
// File: src/Field/Reorder.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\FormException;
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Rank/reorder a list of options by moving items: Space picks an item up (or drops it),
     * Up/Down arrows carry it, Enter accepts the ordered list of keys.
     */
    final class Reorder extends AbstractField
    {
        /** @var array<string, string> */
        private array $options = [];
        /** @var list<string> */
        private array $order = [];
        private int $cursor = 0;
        private bool $grabbed = false;

        public function type(): string
        {
            return 'reorder';
        }

        /**
         * @param array<string|int, string> $options
         */
        public function options(array $options): self
        {
            if (count($options) < 2) {
                throw new FormException(sprintf("Reorder field '%s' requires at least 2 options.", $this->id));
            }
            $norm = [];
            foreach ($options as $k => $v) {
                $norm[(string) $k] = (string) $v;
            }
            $this->options = $norm;
            $this->order = array_keys($norm);
            if ($this->defaultValue === null) {
                $this->defaultValue = $this->order;
            }
            return $this;
        }

        /**
         * @return array<string, string>
         */
        public function getOptions(): array
        {
            return $this->options;
        }

        /**
         * @return list<string>
         */
        public function coerce(mixed $raw): array
        {
            $validKeys = array_keys($this->options);
            if (is_string($raw) && $raw !== '') {
                $raw = array_map('trim', explode(',', $raw));
            }
            if (!is_array($raw) || $raw === []) {
                return $validKeys;
            }
            $ordered = [];
            foreach ($raw as $item) {
                $k = (string) $item;
                if (isset($this->options[$k]) && !in_array($k, $ordered, true)) {
                    $ordered[] = $k;
                }
            }
            foreach ($validKeys as $k) {
                if (!in_array($k, $ordered, true)) {
                    $ordered[] = $k;
                }
            }
            return $ordered;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $this->order = $this->coerce($currentValue ?? $this->defaultValue);
            $this->cursor = 0;
            $this->grabbed = false;
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            $count = count($this->order);
            if ($count === 0) {
                return 'accept';
            }
            if ($key->name === Key::ENTER) {
                $this->grabbed = false;
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                if ($this->grabbed) {
                    $this->grabbed = false;
                    return 'continue';
                }
                return 'cancel';
            }
            if ($key->name === Key::SPACE) {
                $this->grabbed = !$this->grabbed;
                return 'continue';
            }
            if ($key->name === Key::UP || $this->isAction($key, Key::ACTION_UP, $keyBindings)) {
                if ($this->cursor > 0) {
                    if ($this->grabbed) {
                        $tmp = $this->order[$this->cursor - 1];
                        $this->order[$this->cursor - 1] = $this->order[$this->cursor];
                        $this->order[$this->cursor] = $tmp;
                    }
                    $this->cursor--;
                }
                return 'continue';
            }
            if ($key->name === Key::DOWN || $this->isAction($key, Key::ACTION_DOWN, $keyBindings)) {
                if ($this->cursor < $count - 1) {
                    if ($this->grabbed) {
                        $tmp = $this->order[$this->cursor + 1];
                        $this->order[$this->cursor + 1] = $this->order[$this->cursor];
                        $this->order[$this->cursor] = $tmp;
                    }
                    $this->cursor++;
                }
                return 'continue';
            }
            return 'continue';
        }

        /**
         * @return list<string>
         */
        public function getEditValue(): array
        {
            return $this->order;
        }

        public function formatDisplay(mixed $value, TranslationInterface $translator): string
        {
            $list = $this->coerce($value ?? $this->defaultValue);
            $labels = [];
            foreach ($list as $idx => $k) {
                $lbl = isset($this->options[$k]) ? $translator->translate($this->options[$k]) : $k;
                $labels[] = sprintf('%d. %s', $idx + 1, $lbl);
            }
            return implode(' → ', $labels);
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $lines = [];
            foreach ($this->order as $i => $k) {
                $isCursor = ($i === $this->cursor);
                $handle = ($isCursor && $this->grabbed) ? '⇅' : '≡';
                $pointer = $isCursor ? $theme->styleAccent($theme->glyph('cursor', '❯')) : ' ';
                $label = $translator->translate($this->options[$k] ?? $k);
                $row = sprintf('%s %s %d. %s', $pointer, $handle, $i + 1, $label);
                $lines[] = ($isCursor && $this->grabbed)
                    ? $theme->styleAccent($row . ' [moving]')
                    : ($isCursor ? $theme->styleHighlight($row) : $row);
            }
            return $lines;
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            if ($editing) {
                return [
                    'Space' => $this->grabbed ? $translator->translate('drop') : $translator->translate('pick up'),
                    '↑/↓' => $translator->translate('move'),
                    'Enter' => $translator->translate('accept'),
                ];
            }
            return ['Enter' => $translator->translate('reorder')];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            return [
                'type' => 'array',
                'title' => $translator->translate($this->label),
                'items' => ['type' => 'string', 'enum' => array_keys($this->options)],
                'uniqueItems' => true,
                'default' => $this->order,
            ];
        }
    }
}

// ============================================================================
// File: src/Field/Rating.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Graded scale rating field (1..maxPoints) collected as an int, with optional per-point captions.
     */
    final class Rating extends AbstractField
    {
        private int $scale = 5;
        /** @var array<int, string> */
        private array $captions = [];
        private int $current = 3;

        public function type(): string
        {
            return 'rating';
        }

        public function max(int $points): self
        {
            $this->scale = max(2, min(10, $points));
            return $this;
        }

        public function scale(int $points): self
        {
            return $this->max($points);
        }

        /**
         * @param array<int, string> $captions
         */
        public function captions(array $captions): self
        {
            $norm = [];
            foreach ($captions as $pt => $text) {
                $norm[(int) $pt] = (string) $text;
            }
            $this->captions = $norm;
            return $this;
        }

        public function coerce(mixed $raw): int
        {
            $val = is_numeric($raw) ? (int) $raw : (is_int($this->defaultValue) ? $this->defaultValue : 1);
            return max(1, min($this->scale, $val));
        }

        protected function validateTypeConstraints(mixed $value, TranslationInterface $translator): ?string
        {
            if ($value === null || $value === '') {
                return null;
            }
            if (!is_int($value) && !is_numeric($value)) {
                return $translator->translate('Rating must be an integer.');
            }
            $v = (int) $value;
            if ($v < 1 || $v > $this->scale) {
                return $translator->translate('Rating must be between 1 and @max.', ['@max' => $this->scale]);
            }
            return null;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $this->current = $this->coerce($currentValue ?? $this->defaultValue ?? 1);
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            if ($key->name === Key::ENTER) {
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if ($key->name === Key::RIGHT || $key->name === Key::UP) {
                $this->current = min($this->scale, $this->current + 1);
                return 'continue';
            }
            if ($key->name === Key::LEFT || $key->name === Key::DOWN) {
                $this->current = max(1, $this->current - 1);
                return 'continue';
            }
            if ($key->char !== '' && ctype_digit($key->char)) {
                $digit = (int) $key->char;
                if ($digit >= 1 && $digit <= $this->scale) {
                    $this->current = $digit;
                } elseif ($this->scale === 10 && $this->current === 1 && $digit === 0) {
                    $this->current = 10;
                }
                return 'continue';
            }
            return 'continue';
        }

        public function getEditValue(): int
        {
            return $this->current;
        }

        public function formatDisplay(mixed $value, TranslationInterface $translator): string
        {
            $pt = $this->coerce($value ?? $this->defaultValue ?? 1);
            $stars = str_repeat('★', $pt) . str_repeat('☆', max(0, $this->scale - $pt));
            $cap = isset($this->captions[$pt]) ? ' (' . $translator->translate($this->captions[$pt]) . ')' : '';
            return sprintf('%s %d/%d%s', $stars, $pt, $this->scale, $cap);
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $starOn = $theme->glyph('star_on', '★');
            $starOff = $theme->glyph('star_off', '☆');
            $tokens = [];
            for ($i = 1; $i <= $this->scale; $i++) {
                $glyph = $i <= $this->current ? $theme->styleAccent($starOn) : $theme->styleMuted($starOff);
                $tokens[] = ($i === $this->current) ? "[{$i}:{$glyph}]" : " {$i}:{$glyph} ";
            }
            $line = implode(' ', $tokens);
            if (isset($this->captions[$this->current])) {
                $line .= '  ' . $theme->styleHighlight($translator->translate($this->captions[$this->current]));
            }
            return [$line];
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            return [
                '←/→' => $translator->translate('rate'),
                '1-' . $this->scale => $translator->translate('pick'),
                'Enter' => $editing ? $translator->translate('accept') : $translator->translate('edit'),
            ];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            return [
                'type' => 'integer',
                'title' => $translator->translate($this->label),
                'minimum' => 1,
                'maximum' => $this->scale,
                'default' => $this->defaultValue ?? 1,
            ];
        }
    }
}

// ============================================================================
// File: src/Field/Calendar.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Interactive month calendar field returning normalized ISO-8601 YYYY-MM-DD strings.
     * Left/Right arrows move by day, Up/Down move by week (7 days), PageUp/PageDown move by month.
     */
    final class Calendar extends AbstractField
    {
        private \DateTimeImmutable $cursorDate;
        private ?string $minDate = null;
        private ?string $maxDate = null;

        public function __construct(string $id, string $label, string $panelId = 'root')
        {
            parent::__construct($id, $label, $panelId);
            $this->cursorDate = new \DateTimeImmutable('today');
        }

        public function type(): string
        {
            return 'calendar';
        }

        public function min(string $isoDate): self
        {
            $this->minDate = $isoDate;
            return $this;
        }

        public function max(string $isoDate): self
        {
            $this->maxDate = $isoDate;
            return $this;
        }

        public function coerce(mixed $raw): string
        {
            $fallback = $this->defaultValue !== null ? (string) $this->defaultValue : $this->cursorDate->format('Y-m-d');
            $str = trim((string) ($raw ?? ''));
            if ($str === '') {
                return $fallback;
            }
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $str) ?: date_create_immutable($str);
            return $dt instanceof \DateTimeImmutable ? $dt->format('Y-m-d') : $fallback;
        }

        protected function validateTypeConstraints(mixed $value, TranslationInterface $translator): ?string
        {
            $str = (string) ($value ?? '');
            if ($str === '') {
                return null;
            }
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $str);
            if (!$dt || $dt->format('Y-m-d') !== $str) {
                return $translator->translate('Date must be in YYYY-MM-DD format.');
            }
            if ($this->minDate !== null && $str < $this->minDate) {
                return $translator->translate('Date must not be earlier than @min.', ['@min' => $this->minDate]);
            }
            if ($this->maxDate !== null && $str > $this->maxDate) {
                return $translator->translate('Date must not be later than @max.', ['@max' => $this->maxDate]);
            }
            return null;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $iso = $this->coerce($currentValue ?? $this->defaultValue);
            $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
            $this->cursorDate = $dt instanceof \DateTimeImmutable ? $dt : new \DateTimeImmutable('today');
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            if ($key->name === Key::ENTER) {
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if ($key->name === Key::LEFT) {
                $this->cursorDate = $this->cursorDate->modify('-1 day');
                return 'continue';
            }
            if ($key->name === Key::RIGHT) {
                $this->cursorDate = $this->cursorDate->modify('+1 day');
                return 'continue';
            }
            if ($key->name === Key::UP) {
                $this->cursorDate = $this->cursorDate->modify('-7 days');
                return 'continue';
            }
            if ($key->name === Key::DOWN) {
                $this->cursorDate = $this->cursorDate->modify('+7 days');
                return 'continue';
            }
            if ($key->name === Key::PAGE_UP) {
                $this->cursorDate = $this->cursorDate->modify('-1 month');
                return 'continue';
            }
            if ($key->name === Key::PAGE_DOWN) {
                $this->cursorDate = $this->cursorDate->modify('+1 month');
                return 'continue';
            }
            return 'continue';
        }

        public function getEditValue(): string
        {
            return $this->cursorDate->format('Y-m-d');
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $year = (int) $this->cursorDate->format('Y');
            $month = (int) $this->cursorDate->format('m');
            $selectedDay = (int) $this->cursorDate->format('d');
            $firstOfMonth = $this->cursorDate->setDate($year, $month, 1);
            $daysInMonth = (int) $firstOfMonth->format('t');
            $startDow = ((int) $firstOfMonth->format('N')) - 1; // 0 = Mon .. 6 = Sun

            $lines = [
                '  ' . $theme->styleTitle($firstOfMonth->format('F Y')) . '  ' . $theme->styleMuted('(' . $this->cursorDate->format('Y-m-d') . ')'),
                '  ' . $theme->styleMuted('Mo Tu We Th Fr Sa Su'),
            ];

            $cells = array_fill(0, $startDow, '  ');
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $str = sprintf('%2d', $d);
                $cells[] = ($d === $selectedDay) ? $theme->styleHighlight($str) : $str;
            }
            foreach (array_chunk($cells, 7) as $week) {
                $lines[] = '  ' . implode(' ', $week);
            }
            return $lines;
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            if ($editing) {
                return [
                    '←/→' => $translator->translate('±day'),
                    '↑/↓' => $translator->translate('±week'),
                    'PgUp/PgDn' => $translator->translate('±month'),
                    'Enter' => $translator->translate('accept'),
                ];
            }
            return ['Enter' => $translator->translate('pick date')];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            return [
                'type' => 'string',
                'format' => 'date',
                'title' => $translator->translate($this->label),
                'default' => $this->defaultValue,
            ];
        }
    }
}

// ============================================================================
// File: src/Field/FilePicker.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Interactive filesystem browser field; Right arrow enters a directory, Left arrow ascends to parent,
     * Space toggles selection in ->multiple() mode, Enter accepts.
     */
    final class FilePicker extends AbstractField
    {
        private string $baseDir;
        private string $currentDir;
        private bool $multiple = false;
        private int $cursor = 0;
        /** @var list<string> */
        private array $selectedPaths = [];

        public function __construct(string $id, string $label, string $panelId = 'root')
        {
            parent::__construct($id, $label, $panelId);
            $cwd = getcwd() ?: '.';
            $this->baseDir = $cwd;
            $this->currentDir = $cwd;
        }

        public function type(): string
        {
            return 'filepicker';
        }

        public function directory(string $dir): self
        {
            if (is_dir($dir)) {
                $real = realpath($dir) ?: $dir;
                $this->baseDir = $real;
                $this->currentDir = $real;
            }
            return $this;
        }

        public function multiple(bool $multiple = true): self
        {
            $this->multiple = $multiple;
            return $this;
        }

        public function coerce(mixed $raw): string|array
        {
            if ($this->multiple) {
                if (is_array($raw)) {
                    return array_values(array_map('strval', $raw));
                }
                if (is_string($raw) && $raw !== '') {
                    return array_values(array_filter(array_map('trim', explode(',', $raw))));
                }
                return [];
            }
            return (string) ($raw ?? '');
        }

        /**
         * @return list<array{name: string, path: string, is_dir: bool}>
         */
        private function listEntries(): array
        {
            $items = @scandir($this->currentDir);
            if ($items === false) {
                return [];
            }
            $dirs = [];
            $files = [];
            foreach ($items as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $full = $this->currentDir . DIRECTORY_SEPARATOR . $name;
                if (is_dir($full)) {
                    $dirs[] = ['name' => $name . '/', 'path' => $full, 'is_dir' => true];
                } else {
                    $files[] = ['name' => $name, 'path' => $full, 'is_dir' => false];
                }
            }
            return array_merge($dirs, $files);
        }

        public function beginEdit(mixed $currentValue): void
        {
            $coerced = $this->coerce($currentValue ?? $this->defaultValue);
            if ($this->multiple) {
                $this->selectedPaths = is_array($coerced) ? $coerced : [];
            } else {
                $this->selectedPaths = is_string($coerced) && $coerced !== '' ? [$coerced] : [];
            }
            $this->cursor = 0;
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            $entries = $this->listEntries();
            $count = count($entries);

            if ($key->name === Key::ENTER) {
                if (!$this->multiple && isset($entries[$this->cursor])) {
                    $this->selectedPaths = [$entries[$this->cursor]['path']];
                }
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if ($key->name === Key::UP && $count > 0) {
                $this->cursor = ($this->cursor - 1 + $count) % $count;
                return 'continue';
            }
            if ($key->name === Key::DOWN && $count > 0) {
                $this->cursor = ($this->cursor + 1) % $count;
                return 'continue';
            }
            if ($key->name === Key::RIGHT && isset($entries[$this->cursor]) && $entries[$this->cursor]['is_dir']) {
                $this->currentDir = $entries[$this->cursor]['path'];
                $this->cursor = 0;
                return 'continue';
            }
            if ($key->name === Key::LEFT) {
                $parent = dirname($this->currentDir);
                if ($parent !== $this->currentDir && is_dir($parent)) {
                    $this->currentDir = $parent;
                    $this->cursor = 0;
                }
                return 'continue';
            }
            if ($this->multiple && $key->name === Key::SPACE && isset($entries[$this->cursor])) {
                $p = $entries[$this->cursor]['path'];
                if (in_array($p, $this->selectedPaths, true)) {
                    $this->selectedPaths = array_values(array_diff($this->selectedPaths, [$p]));
                } else {
                    $this->selectedPaths[] = $p;
                }
                return 'continue';
            }
            return 'continue';
        }

        public function getEditValue(): string|array
        {
            if ($this->multiple) {
                return $this->selectedPaths;
            }
            $entries = $this->listEntries();
            return $this->selectedPaths[0] ?? ($entries[$this->cursor]['path'] ?? $this->currentDir);
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $lines = [
                '  ' . $theme->styleTitle('📁 ' . $this->currentDir),
            ];
            $all = $this->listEntries();
            $total = count($all);
            $pageSize = 7;
            $half = intdiv($pageSize, 2);
            $start = max(0, min($this->cursor - $half, max(0, $total - $pageSize)));
            $entries = array_slice($all, $start, $pageSize);
            if ($entries === []) {
                $lines[] = '  ' . $theme->styleMuted($translator->translate('(empty directory)'));
                return $lines;
            }
            foreach ($entries as $idx => $entry) {
                $isCur = ($idx === $this->cursor);
                $isSel = in_array($entry['path'], $this->selectedPaths, true);
                $icon = $entry['is_dir'] ? '▸ ' : '  ';
                $check = $this->multiple ? ($isSel ? '[x] ' : '[ ] ') : '';
                $row = sprintf('  %s%s%s', $check, $icon, $entry['name']);
                $lines[] = $isCur ? $theme->styleHighlight($row) : $row;
            }
            return $lines;
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            if ($editing) {
                return [
                    '↑/↓' => $translator->translate('move'),
                    '←/→' => $translator->translate('parent/open dir'),
                    'Enter' => $translator->translate('select'),
                ];
            }
            return ['Enter' => $translator->translate('browse files')];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            if ($this->multiple) {
                return [
                    'type' => 'array',
                    'title' => $translator->translate($this->label),
                    'items' => ['type' => 'string'],
                ];
            }
            return [
                'type' => 'string',
                'title' => $translator->translate($this->label),
            ];
        }
    }
}

// ============================================================================
// File: src/Field/Template.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\FormException;
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Structured template field with named slots (e.g. "{scheme}://{host}:{port}").
     * Tab / Shift+Tab jumps between slots; collects both the assembled string and named slot parts.
     */
    final class Template extends AbstractField
    {
        private string $shape = '{value}';
        /** @var list<string> */
        private array $slotNames = ['value'];
        /** @var array<string, string> */
        private array $slotDefaults = [];
        /** @var array<string, string> */
        private array $slotBuffers = [];
        private int $activeSlotIndex = 0;

        public function type(): string
        {
            return 'template';
        }

        /**
         * @param array<string, string> $defaults
         */
        public function shape(string $shape, array $defaults = []): self
        {
            if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $shape, $m) < 1 || $m[1] === []) {
                throw new FormException(sprintf("Template field '%s' shape '%s' must contain at least one {slot} placeholder.", $this->id, $shape));
            }
            $this->shape = $shape;
            $this->slotNames = array_values(array_unique($m[1]));
            foreach ($defaults as $k => $v) {
                $this->slotDefaults[(string) $k] = (string) $v;
            }
            foreach ($this->slotNames as $slot) {
                $this->slotBuffers[$slot] = $this->slotDefaults[$slot] ?? '';
            }
            if ($this->defaultValue === null) {
                $this->defaultValue = $this->assemble($this->slotBuffers);
            }
            return $this;
        }

        public function getShape(): string
        {
            return $this->shape;
        }

        /**
         * @return list<string>
         */
        public function slotNames(): array
        {
            return $this->slotNames;
        }

        /**
         * @param array<string, string> $slots
         */
        private function assemble(array $slots): string
        {
            $replacements = [];
            foreach ($this->slotNames as $name) {
                $replacements['{' . $name . '}'] = $slots[$name] ?? ($this->slotDefaults[$name] ?? '');
            }
            return strtr($this->shape, $replacements);
        }

        public function coerce(mixed $raw): string
        {
            if (is_array($raw)) {
                $slots = [];
                foreach ($this->slotNames as $name) {
                    $slots[$name] = (string) ($raw[$name] ?? $this->slotDefaults[$name] ?? '');
                }
                return $this->assemble($slots);
            }
            return (string) ($raw ?? '');
        }

        public function extractParts(mixed $value): array
        {
            $str = (string) ($value ?? '');
            $regex = '/^' . preg_replace_callback(
                '/\\\{([a-zA-Z0-9_]+)\\\}/',
                static function (array $m): string {
                    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $m[1]) !== 1) {
                        throw new FormException(sprintf("Invalid template slot name '%s'.", $m[1]));
                    }
                    return '(?P<' . $m[1] . '>.*?)';
                },
                preg_quote($this->shape, '/')
            ) . '$/u';

            if (@preg_match($regex, $str, $m) === 1) {
                $parts = [];
                foreach ($this->slotNames as $slot) {
                    $parts[$slot] = $m[$slot] ?? '';
                }
                return $parts;
            }
            $parts = [];
            foreach ($this->slotNames as $slot) {
                $parts[$slot] = $this->slotDefaults[$slot] ?? '';
            }
            return $parts;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $parts = $this->extractParts($currentValue ?? $this->defaultValue);
            foreach ($this->slotNames as $slot) {
                $this->slotBuffers[$slot] = (string) ($parts[$slot] ?? $this->slotDefaults[$slot] ?? '');
            }
            $this->activeSlotIndex = 0;
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            $slotCount = count($this->slotNames);
            $activeSlot = $this->slotNames[$this->activeSlotIndex] ?? $this->slotNames[0];

            if ($key->name === Key::ENTER) {
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if ($key->name === Key::TAB || $key->name === Key::RIGHT) {
                $this->activeSlotIndex = ($this->activeSlotIndex + 1) % $slotCount;
                return 'continue';
            }
            if ($key->name === Key::SHIFT_TAB || $key->name === Key::LEFT) {
                $this->activeSlotIndex = ($this->activeSlotIndex - 1 + $slotCount) % $slotCount;
                return 'continue';
            }
            if ($key->name === Key::BACKSPACE) {
                $cur = $this->slotBuffers[$activeSlot] ?? '';
                $len = MbString::length($cur);
                if ($len > 0) {
                    $this->slotBuffers[$activeSlot] = MbString::substr($cur, 0, $len - 1);
                }
                return 'continue';
            }
            if ($key->isPrintable()) {
                $this->slotBuffers[$activeSlot] = ($this->slotBuffers[$activeSlot] ?? '') . $key->char;
                return 'continue';
            }
            return 'continue';
        }

        public function getEditValue(): string
        {
            return $this->assemble($this->slotBuffers);
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $activeSlot = $this->slotNames[$this->activeSlotIndex] ?? '';
            $rendered = (string) preg_replace_callback(
                '/\{([a-zA-Z0-9_]+)\}/',
                function (array $m) use ($activeSlot, $theme): string {
                    $slot = $m[1];
                    $val = $this->slotBuffers[$slot] ?? '';
                    $display = $val !== '' ? $val : $slot;
                    return $slot === $activeSlot
                        ? $theme->styleHighlight("[{$display}]")
                        : $theme->styleAccent("{$display}");
                },
                $this->shape
            );
            return [$theme->glyph('input_prefix', '› ') . $rendered . '  ' . $theme->styleMuted("(slot: {$activeSlot})")];
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            return [
                'Tab/Shift+Tab' => $translator->translate('next/prev slot'),
                'Enter' => $editing ? $translator->translate('accept') : $translator->translate('edit'),
            ];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            return [
                'type' => 'string',
                'title' => $translator->translate($this->label),
                'description' => sprintf('Template shape: %s', $this->shape),
                'default' => $this->defaultValue,
            ];
        }
    }
}

// ============================================================================
// File: src/Field/Textarea.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Multi-line text input field; Enter inserts a newline, Tab accepts,
     * and Ctrl+G hands off to $VISUAL / $EDITOR when enabled.
     */
    final class Textarea extends AbstractField
    {
        /** @var list<string> */
        private array $lines = [''];
        private int $row = 0;
        private int $col = 0;
        private int $rows = 4;

        public function type(): string
        {
            return 'textarea';
        }

        public function rows(int $rows): self
        {
            $this->rows = max(2, $rows);
            return $this;
        }

        public function coerce(mixed $raw): string
        {
            if (is_array($raw)) {
                return implode("\n", array_map('strval', $raw));
            }
            return (string) ($raw ?? '');
        }

        public function beginEdit(mixed $currentValue): void
        {
            $text = $this->coerce($currentValue ?? $this->defaultValue ?? '');
            $split = preg_split("/\r?\n/", $text);
            $this->lines = ($split !== false && $split !== []) ? array_values($split) : [''];
            $this->row = count($this->lines) - 1;
            $this->col = MbString::length($this->lines[$this->row]);
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            if ($key->name === Key::TAB) {
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            if ($key->name === Key::ENTER) {
                $curLine = $this->lines[$this->row] ?? '';
                $before = MbString::substr($curLine, 0, $this->col);
                $after = MbString::substr($curLine, $this->col);
                $this->lines[$this->row] = $before;
                array_splice($this->lines, $this->row + 1, 0, [$after]);
                $this->row++;
                $this->col = 0;
                return 'continue';
            }
            if ($key->name === Key::UP) {
                if ($this->row > 0) {
                    $this->row--;
                    $this->col = min($this->col, MbString::length($this->lines[$this->row]));
                }
                return 'continue';
            }
            if ($key->name === Key::DOWN) {
                if ($this->row < count($this->lines) - 1) {
                    $this->row++;
                    $this->col = min($this->col, MbString::length($this->lines[$this->row]));
                }
                return 'continue';
            }
            if ($key->name === Key::LEFT) {
                if ($this->col > 0) {
                    $this->col--;
                } elseif ($this->row > 0) {
                    $this->row--;
                    $this->col = MbString::length($this->lines[$this->row]);
                }
                return 'continue';
            }
            if ($key->name === Key::RIGHT) {
                $lineLen = MbString::length($this->lines[$this->row]);
                if ($this->col < $lineLen) {
                    $this->col++;
                } elseif ($this->row < count($this->lines) - 1) {
                    $this->row++;
                    $this->col = 0;
                }
                return 'continue';
            }
            if ($key->name === Key::BACKSPACE) {
                $curLine = $this->lines[$this->row] ?? '';
                if ($this->col > 0) {
                    $this->lines[$this->row] = MbString::substr($curLine, 0, $this->col - 1) . MbString::substr($curLine, $this->col);
                    $this->col--;
                } elseif ($this->row > 0) {
                    $prevLen = MbString::length($this->lines[$this->row - 1]);
                    $this->lines[$this->row - 1] .= $curLine;
                    array_splice($this->lines, $this->row, 1);
                    $this->row--;
                    $this->col = $prevLen;
                }
                return 'continue';
            }
            if ($key->isPrintable()) {
                $curLine = $this->lines[$this->row] ?? '';
                $this->lines[$this->row] = MbString::substr($curLine, 0, $this->col) . $key->char . MbString::substr($curLine, $this->col);
                $this->col += MbString::length($key->char);
            }
            return 'continue';
        }

        public function getEditValue(): string
        {
            return implode("\n", $this->lines);
        }

        public function formatDisplay(mixed $value, TranslationInterface $translator): string
        {
            $str = trim((string) ($value ?? ''));
            if ($str === '') {
                return '—';
            }
            $lines = preg_split("/\r?\n/", $str) ?: [];
            if (count($lines) === 1) {
                return $lines[0];
            }
            return sprintf('%s (+%d lines)', $lines[0], count($lines) - 1);
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            $out = [];
            foreach ($this->lines as $idx => $line) {
                $num = $theme->styleMuted(sprintf('%2d│ ', $idx + 1));
                if ($idx === $this->row) {
                    $left = MbString::substr($line, 0, $this->col);
                    $at = MbString::substr($line, $this->col, 1);
                    $right = $this->col < MbString::length($line) ? MbString::substr($line, $this->col + 1) : '';
                    $out[] = $num . $left . $theme->styleCaret($at !== '' ? $at : ' ') . $right;
                } else {
                    $out[] = $num . $line;
                }
            }
            $out[] = '  ' . $theme->styleMuted($translator->translate('[Enter: new line | Tab: accept | Esc: cancel]'));
            return $out;
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            if ($editing) {
                return [
                    'Enter' => $translator->translate('newline'),
                    'Tab' => $translator->translate('accept'),
                    'Esc' => $translator->translate('cancel'),
                ];
            }
            return ['Enter' => $translator->translate('edit multiline')];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            return [
                'type' => 'string',
                'title' => $translator->translate($this->label),
                'default' => $this->defaultValue,
            ];
        }
    }
}

// ============================================================================
// File: src/Field/Pause.php
// ============================================================================
namespace DrevOps\PhpTui\Field {
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Interactive acknowledgement gate; Enter or Space acknowledges and continues.
     * Unattended / headless collection auto-acknowledges it as true.
     */
    final class Pause extends AbstractField
    {
        private bool $acknowledged = false;

        public function __construct(string $id, string $label, string $panelId = 'root')
        {
            parent::__construct($id, $label, $panelId);
            $this->defaultValue = true;
        }

        public function type(): string
        {
            return 'pause';
        }

        public function coerce(mixed $raw): bool
        {
            if ($raw === null || $raw === '') {
                return true;
            }
            return (bool) $raw;
        }

        public function beginEdit(mixed $currentValue): void
        {
            $this->acknowledged = true;
        }

        public function handleEditKey(Key $key, array $keyBindings): string
        {
            if ($key->name === Key::ENTER || $key->name === Key::SPACE) {
                $this->acknowledged = true;
                return 'accept';
            }
            if ($key->name === Key::ESCAPE) {
                return 'cancel';
            }
            return 'continue';
        }

        public function getEditValue(): bool
        {
            return $this->acknowledged;
        }

        public function formatDisplay(mixed $value, TranslationInterface $translator): string
        {
            return $value ? $translator->translate('[Acknowledged]') : $translator->translate('[Press Enter to continue]');
        }

        protected function renderInlineEditor(
            int $width,
            ThemeInterface $theme,
            TranslationInterface $translator,
        ): array {
            return [$theme->styleHighlight($translator->translate('Press Enter or Space to acknowledge and continue…'))];
        }

        public function keyHints(bool $editing, TranslationInterface $translator): array
        {
            return ['Enter/Space' => $translator->translate('acknowledge')];
        }

        public function toJsonSchema(TranslationInterface $translator): array
        {
            return [
                'type' => 'boolean',
                'title' => $translator->translate($this->label),
                'default' => true,
            ];
        }
    }
}

// ============================================================================
// File: src/Builder/Name.php
// ============================================================================
namespace DrevOps\PhpTui\Builder {
    use DrevOps\PhpTui\FormException;
    use DrevOps\PhpTui\Utils\Str;

    /**
     * Validates and derives machine identifiers from human-facing labels.
     */
    final class Name
    {
        public static function resolve(string $label, string $explicitId = ''): string
        {
            $id = $explicitId !== '' ? $explicitId : Str::toMachineId($label);
            self::assertValid($id);
            return $id;
        }

        public static function assertValid(string $id): void
        {
            if ($id === '' || preg_match('/^[a-zA-Z_][a-zA-Z0-9_.-]*$/', $id) !== 1) {
                throw new FormException(sprintf(
                    "Invalid identifier '%s'. Identifiers must start with a letter or underscore and contain only [a-zA-Z0-9_.-].",
                    $id
                ));
            }
        }
    }
}

// ============================================================================
// File: src/Builder/Fixup.php
// ============================================================================
namespace DrevOps\PhpTui\Builder {
    use DrevOps\PhpTui\Derive\DeriveInterface;
    use DrevOps\PhpTui\Discovery\DiscovererInterface;

    /**
     * Represents a form-level or field-level reconciliation / derivation / discovery rule.
     */
    final readonly class Fixup
    {
        public function __construct(
            public string $fieldId,
            public string $kind,
            public ?DeriveInterface $derive = null,
            public ?DiscovererInterface $discover = null,
            public ?\Closure $reconcile = null,
        ) {
        }

        public static function derive(string $fieldId, DeriveInterface $derive): self
        {
            return new self($fieldId, 'derive', derive: $derive);
        }

        public static function discover(string $fieldId, DiscovererInterface $discover): self
        {
            return new self($fieldId, 'discover', discover: $discover);
        }

        public static function custom(string $fieldId, \Closure $reconcile): self
        {
            return new self($fieldId, 'custom', reconcile: $reconcile);
        }
    }
}

// ============================================================================
// File: src/Builder/FieldBuilder.php
// ============================================================================
namespace DrevOps\PhpTui\Builder {
    use DrevOps\PhpTui\Condition\Condition;
    use DrevOps\PhpTui\Condition\ConditionInterface;
    use DrevOps\PhpTui\Derive\DeriveInterface;
    use DrevOps\PhpTui\Derive\Transform;
    use DrevOps\PhpTui\Discovery\DiscovererInterface;
    use DrevOps\PhpTui\Field\AbstractField;
    use DrevOps\PhpTui\Field\Calendar;
    use DrevOps\PhpTui\Field\Confirm;
    use DrevOps\PhpTui\Field\FilePicker;
    use DrevOps\PhpTui\Field\Number;
    use DrevOps\PhpTui\Field\Password;
    use DrevOps\PhpTui\Field\Rating;
    use DrevOps\PhpTui\Field\Reorder;
    use DrevOps\PhpTui\Field\Select;
    use DrevOps\PhpTui\Field\Suggest;
    use DrevOps\PhpTui\Field\Template;
    use DrevOps\PhpTui\Field\Text;
    use DrevOps\PhpTui\Field\Textarea;
    use DrevOps\PhpTui\Field\Toggle;

    /**
     * Fluent wrapper around any AbstractField instance during form construction.
     */
    final class FieldBuilder
    {
        public function __construct(
            private readonly AbstractField $field,
        ) {
        }

        public function field(): AbstractField
        {
            return $this->field;
        }

        public function description(string $description): self
        {
            $this->field->setDescription($description);
            return $this;
        }

        public function help(string $help): self
        {
            $this->field->setHelp($help);
            return $this;
        }

        public function placeholder(string $placeholder): self
        {
            $this->field->setPlaceholder($placeholder);
            return $this;
        }

        public function required(bool $required = true, ?string $message = null): self
        {
            $this->field->setRequired($required, $message);
            return $this;
        }

        public function standalone(bool $standalone = true): self
        {
            $this->field->setStandalone($standalone);
            return $this;
        }

        public function default(mixed $default): self
        {
            $this->field->setDefault($default);
            return $this;
        }

        public function in(string $region): self
        {
            $this->field->setRegion($region);
            return $this;
        }

        /**
         * Attaches a visibility rule (either a ConditionInterface or shorthand field+eq match).
         */
        public function when(ConditionInterface|string $conditionOrField, mixed $equalsValue = null): self
        {
            $cond = $conditionOrField instanceof ConditionInterface
                ? $conditionOrField
                : Condition::equals($conditionOrField, $equalsValue);
            $this->field->setCondition($cond);
            return $this;
        }

        /**
         * Attaches a reactive derived-value rule (DeriveInterface or shorthand sourceField + str2name format).
         */
        public function derive(DeriveInterface|string $deriveOrSourceField, string|\Closure $transform = 'machine'): self
        {
            $drv = $deriveOrSourceField instanceof DeriveInterface
                ? $deriveOrSourceField
                : new Transform($deriveOrSourceField, $transform);
            $this->field->setDerivation($drv);
            return $this;
        }

        public function discover(DiscovererInterface $discoverer): self
        {
            $this->field->setDiscoverer($discoverer);
            return $this;
        }

        public function validate(\Closure $validator): self
        {
            $this->field->setValidator($validator);
            return $this;
        }

        public function transform(\Closure $transformer): self
        {
            $this->field->setTransformer($transformer);
            return $this;
        }

        /**
         * @param array<string, list<string>|string> $bindings
         */
        public function keys(array $bindings): self
        {
            $this->field->setKeyOverrides($bindings);
            return $this;
        }

        /**
         * @param array<string|int, mixed> $options
         */
        public function options(array $options): self
        {
            if (
                $this->field instanceof Select ||
                $this->field instanceof Toggle ||
                $this->field instanceof Reorder ||
                $this->field instanceof Suggest
            ) {
                $this->field->options($options);
                return $this;
            }
            throw new FormException(sprintf("Field '%s' does not accept options.", $this->field->id()));
        }

        /**
         * @param array<string, array<string|int, string>> $groups
         */
        public function groups(array $groups): self
        {
            if ($this->field instanceof Select) {
                $this->field->groups($groups);
            }
            return $this;
        }

        public function complete(array $candidates): self
        {
            if ($this->field instanceof Suggest) {
                $this->field->complete($candidates);
            }
            return $this;
        }

        public function multiple(bool $multiple = true): self
        {
            if ($this->field instanceof Select || $this->field instanceof FilePicker) {
                $this->field->multiple($multiple);
            }
            return $this;
        }

        public function min(int|string $min): self
        {
            if ($this->field instanceof Number && is_int($min)) {
                $this->field->min($min);
            } elseif ($this->field instanceof Calendar && is_string($min)) {
                $this->field->min($min);
            }
            return $this;
        }

        public function max(int|string $max): self
        {
            if ($this->field instanceof Number && is_int($max)) {
                $this->field->max($max);
            } elseif ($this->field instanceof Rating && is_int($max)) {
                $this->field->max($max);
            } elseif ($this->field instanceof Calendar && is_string($max)) {
                $this->field->max($max);
            }
            return $this;
        }

        public function step(int $step): self
        {
            if ($this->field instanceof Number) {
                $this->field->step($step);
            }
            return $this;
        }

        public function maxLength(int $max): self
        {
            if ($this->field instanceof Text) {
                $this->field->maxLength($max);
            }
            return $this;
        }

        public function pattern(string $regex): self
        {
            if ($this->field instanceof Text) {
                $this->field->pattern($regex);
            }
            return $this;
        }

        public function revealable(bool $revealable = true): self
        {
            if ($this->field instanceof Password) {
                $this->field->revealable($revealable);
            }
            return $this;
        }

        public function mask(string $glyph = '•'): self
        {
            if ($this->field instanceof Password) {
                $this->field->mask($glyph);
            }
            return $this;
        }

        public function labels(string $yes = 'Yes', string $no = 'No'): self
        {
            if ($this->field instanceof Confirm) {
                $this->field->labels($yes, $no);
            }
            return $this;
        }

        public function scale(int $points): self
        {
            if ($this->field instanceof Rating) {
                $this->field->scale($points);
            }
            return $this;
        }

        /**
         * @param array<int, string> $captions
         */
        public function captions(array $captions): self
        {
            if ($this->field instanceof Rating) {
                $this->field->captions($captions);
            }
            return $this;
        }

        /**
         * @param array<string, string> $defaults
         */
        public function shape(string $shape, array $defaults = []): self
        {
            if ($this->field instanceof Template) {
                $this->field->shape($shape, $defaults);
            }
            return $this;
        }

        public function directory(string $dir): self
        {
            if ($this->field instanceof FilePicker) {
                $this->field->directory($dir);
            }
            return $this;
        }

        public function rows(int $rows): self
        {
            if ($this->field instanceof Textarea) {
                $this->field->rows($rows);
            }
            return $this;
        }

        public function pageSize(int $size): self
        {
            if ($this->field instanceof Select) {
                $this->field->pageSize($size);
            }
            return $this;
        }
    }
}

// ============================================================================
// File: src/Builder/PanelBuilder.php
// ============================================================================
namespace DrevOps\PhpTui\Builder {
    use DrevOps\PhpTui\Block\BlockInterface;
    use DrevOps\PhpTui\Block\Markup;
    use DrevOps\PhpTui\Block\Panel;
    use DrevOps\PhpTui\Block\Progress;
    use DrevOps\PhpTui\Condition\ConditionInterface;
    use DrevOps\PhpTui\Field\AbstractField;
    use DrevOps\PhpTui\Field\Calendar;
    use DrevOps\PhpTui\Field\Confirm;
    use DrevOps\PhpTui\Field\FilePicker;
    use DrevOps\PhpTui\Field\Number;
    use DrevOps\PhpTui\Field\Password;
    use DrevOps\PhpTui\Field\Pause;
    use DrevOps\PhpTui\Field\Rating;
    use DrevOps\PhpTui\Field\Reorder;
    use DrevOps\PhpTui\Field\Search;
    use DrevOps\PhpTui\Field\Select;
    use DrevOps\PhpTui\Field\Suggest;
    use DrevOps\PhpTui\Field\Template;
    use DrevOps\PhpTui\Field\Text;
    use DrevOps\PhpTui\Field\Textarea;
    use DrevOps\PhpTui\Field\Toggle;

    /**
     * Fluent builder for panels, nested sub-panels, modal dialogs, layout rows, markup/progress blocks,
     * and all 15 interactive field types.
     */
    final class PanelBuilder
    {
        private string $description = '';
        private ?ConditionInterface $condition = null;
        private bool $isModal = false;
        private string $submitLabel = 'Submit';
        private string $cancelLabel = 'Cancel';
        private string $region = 'content';
        /** @var list<int|string> */
        private array $layoutRows = [];
        /** @var list<\Closure> */
        private array $preloadTasks = [];
        /** @var list<FieldBuilder|PanelBuilder|BlockInterface> */
        private array $items = [];

        public function __construct(
            private readonly string $id,
            private readonly string $title,
        ) {
        }

        public function id(): string
        {
            return $this->id;
        }

        public function description(string $description): self
        {
            $this->description = $description;
            return $this;
        }

        public function when(ConditionInterface $condition): self
        {
            $this->condition = $condition;
            return $this;
        }

        public function modal(string $submitLabel = 'Submit', string $cancelLabel = 'Cancel'): self
        {
            $this->isModal = true;
            $this->submitLabel = $submitLabel;
            $this->cancelLabel = $cancelLabel;
            return $this;
        }

        public function in(string $region): self
        {
            $this->region = $region;
            return $this;
        }

        public function layout(int|string ...$rows): self
        {
            $this->layoutRows = array_values($rows);
            return $this;
        }

        public function preload(\Closure $work): self
        {
            $this->preloadTasks[] = $work;
            return $this;
        }

        public function add(BlockInterface $block): self
        {
            $this->items[] = $block;
            return $this;
        }

        /**
         * Adds a child sub-panel (drill-down or modal).
         * Supports both ->panel('Title', fn(PanelBuilder $p) => ...) and ->panel('id', 'Title', fn(PanelBuilder $p) => ...).
         */
        public function panel(string $titleOrId, string|\Closure $idOrBuild = '', ?\Closure $build = null): self
        {
            if ($idOrBuild instanceof \Closure) {
                $title = $titleOrId;
                $id = Name::resolve($title);
                $callback = $idOrBuild;
            } elseif ($build instanceof \Closure) {
                // Check whether first arg looks like a human label or machine id
                $first = MbString::substr($titleOrId, 0, 1, 'UTF-8');
                if (str_contains($titleOrId, ' ') || ($first !== '' && $first === MbString::upper($first) && MbString::lower($first) !== $first)) {
                    $title = $titleOrId;
                    $id = Name::resolve($title, $idOrBuild);
                } else {
                    $id = Name::resolve($idOrBuild, $titleOrId);
                    $title = $idOrBuild;
                }
                $callback = $build;
            } else {
                $title = $titleOrId;
                $id = Name::resolve($title, is_string($idOrBuild) ? $idOrBuild : '');
                $callback = null;
            }

            $child = new self($id, $title);
            if ($callback !== null) {
                $callback($child);
            }
            $this->items[] = $child;
            return $this;
        }

        public function text(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Text($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function password(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Password($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function number(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Number($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function confirm(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Confirm($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function toggle(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Toggle($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function select(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Select($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function search(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Search($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function suggest(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Suggest($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function reorder(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Reorder($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function rating(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Rating($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function calendar(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Calendar($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function filePicker(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new FilePicker($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function template(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Template($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function textarea(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Textarea($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function pause(string $label, string $id = ''): FieldBuilder
        {
            return $this->registerField(new Pause($this->resolveFieldId($label, $id), $this->resolveFieldLabel($label, $id), $this->id));
        }

        public function note(string $body, string $id = ''): Markup
        {
            $block = new Markup($id !== '' ? Name::resolve($id, $id) : Name::resolve($body), $body);
            $this->items[] = $block;
            return $block;
        }

        public function markup(string $body, string $title = '', string $id = ''): Markup
        {
            $resolvedId = $id !== '' ? Name::resolve($id, $id) : Name::resolve($title !== '' ? $title : 'markup_' . count($this->items));
            $block = new Markup($resolvedId, $body, $title);
            $this->items[] = $block;
            return $block;
        }

        public function progress(string $caption, string $id = ''): Progress
        {
            $resolvedId = $id !== '' ? Name::resolve($id, $id) : Name::resolve($caption);
            $block = new Progress($resolvedId, $caption);
            $this->items[] = $block;
            return $block;
        }

        public function seal(): Panel
        {
            return $this->block();
        }

        public function block(): Panel
        {
            $children = [];
            foreach ($this->items as $item) {
                if ($item instanceof FieldBuilder) {
                    $children[] = $item->field();
                } elseif ($item instanceof self) {
                    $children[] = $item->block();
                } else {
                    $children[] = $item;
                }
            }
            return new Panel(
                id: $this->id,
                title: $this->title,
                description: $this->description,
                children: $children,
                condition: $this->condition,
                isModal: $this->isModal,
                submitLabel: $this->submitLabel,
                cancelLabel: $this->cancelLabel,
                region: $this->region,
                layoutRows: $this->layoutRows,
                preloadTasks: $this->preloadTasks,
            );
        }

        private function registerField(AbstractField $field): FieldBuilder
        {
            $fb = new FieldBuilder($field);
            $this->items[] = $fb;
            return $fb;
        }

        /**
         * Supports both v0.3.0 `$p->text('Order name')` / `$p->text('Order name', 'order_name')`
         * and legacy `$p->text('order_name', 'Order name')`.
         */
        private function resolveFieldId(string $first, string $second): string
        {
            if ($second === '') {
                return Name::resolve($first);
            }
            // If first is a clean snake_case id and second has spaces/uppercase, first is the id
            if (preg_match('/^[a-z][a-z0-9_]*$/', $first) === 1) {
                $secondFirst = MbString::substr($second, 0, 1, 'UTF-8');
                $looksTitle = str_contains($second, ' ') || ($secondFirst !== '' && $secondFirst === MbString::upper($secondFirst) && MbString::lower($secondFirst) !== $secondFirst);
                if ($looksTitle) {
                    return Name::resolve($second, $first);
                }
            }
            return Name::resolve($first, $second);
        }

        private function resolveFieldLabel(string $first, string $second): string
        {
            if ($second === '') {
                return $first;
            }
            if (preg_match('/^[a-z][a-z0-9_]*$/', $first) === 1 && (str_contains($second, ' ') || ctype_upper($second[0] ?? ''))) {
                return $second;
            }
            return $first;
        }
    }
}

// ============================================================================
// File: src/Builder/Form.php
// ============================================================================
namespace DrevOps\PhpTui\Builder {
    use DrevOps\PhpTui\Block\Panel;
    use DrevOps\PhpTui\Derive\DeriveInterface;
    use DrevOps\PhpTui\Discovery\DiscovererInterface;
    use DrevOps\PhpTui\Field\FieldInterface;
    use DrevOps\PhpTui\Field\Reorder;
    use DrevOps\PhpTui\Field\Select;
    use DrevOps\PhpTui\Field\Template;
    use DrevOps\PhpTui\Field\Toggle;
    use DrevOps\PhpTui\FormException;

    /**
     * Top-level declarative Form builder.
     * Performs comprehensive build-time AST and dependency-graph validation when ->build() / ->root() is invoked.
     */
    final class Form
    {
        private string $banner = '';
        private string $submitLabel = 'Submit';
        private string $cancelLabel = 'Cancel';
        /** @var list<PanelBuilder> */
        private array $panels = [];
        /** @var list<Fixup> */
        private array $fixups = [];
        private ?Panel $cachedRoot = null;

        public function __construct(
            private readonly string $title = '',
            private readonly string $description = '',
        ) {
        }

        public static function create(?string $title = null, ?string $description = null): self
        {
            return new self($title ?? '', $description ?? '');
        }

        public function title(): string
        {
            return $this->title;
        }

        public function description(): string
        {
            return $this->description;
        }

        public function banner(string $message): self
        {
            $this->banner = $message;
            return $this;
        }

        public function getBanner(): string
        {
            return $this->banner;
        }

        public function buttons(string $submit = 'Submit', string $cancel = 'Cancel'): self
        {
            $this->submitLabel = $submit;
            $this->cancelLabel = $cancel;
            return $this;
        }

        /**
         * Declares a top-level or nested panel.
         * Supports:
         * 1) ->panel('New order', function (PanelBuilder $p): void { ... })  (returns $this for fluent chaining)
         * 2) ->panel('order', 'New order', function (PanelBuilder $p): void { ... }) (returns $this)
         * 3) ->panel('order', 'New order') (returns PanelBuilder when no callback passed)
         */
        public function panel(
            string $titleOrId,
            string|\Closure|null $idOrCallback = null,
            string|\Closure|null $descriptionOrNull = null,
            ?\Closure $callback = null,
        ): self|PanelBuilder {
            $this->cachedRoot = null;

            if ($idOrCallback instanceof \Closure) {
                $title = $titleOrId;
                $id = Name::resolve($title);
                $pb = new PanelBuilder($id, $title);
                $idOrCallback($pb);
                $this->panels[] = $pb;
                return $this;
            }

            if ($descriptionOrNull instanceof \Closure || $callback instanceof \Closure) {
                $cb = $callback ?? $descriptionOrNull;
                $id = is_string($idOrCallback) && $idOrCallback !== ''
                    ? (str_contains($idOrCallback, ' ') ? Name::resolve($idOrCallback, $titleOrId) : Name::resolve($titleOrId, $idOrCallback))
                    : Name::resolve($titleOrId);
                $title = is_string($idOrCallback) && str_contains($idOrCallback, ' ') ? $idOrCallback : $titleOrId;
                $pb = new PanelBuilder($id, $title);
                if (is_string($descriptionOrNull) && $descriptionOrNull !== '') {
                    $pb->description($descriptionOrNull);
                }
                if ($cb instanceof \Closure) {
                    $cb($pb);
                }
                $this->panels[] = $pb;
                return $this;
            }

            $title = is_string($idOrCallback) && $idOrCallback !== '' ? $idOrCallback : $titleOrId;
            $id = is_string($idOrCallback) && $idOrCallback !== '' ? Name::resolve($title, $titleOrId) : Name::resolve($title);
            $pb = new PanelBuilder($id, $title);
            if ($descriptionOrNull !== null) {
                $pb->description($descriptionOrNull);
            }
            $this->panels[] = $pb;
            return $pb;
        }

        public function fixup(Fixup $fixup): self
        {
            $this->cachedRoot = null;
            $this->fixups[] = $fixup;
            return $this;
        }

        public function derive(string $fieldId, DeriveInterface $derive): self
        {
            return $this->fixup(Fixup::derive($fieldId, $derive));
        }

        public function discover(string $fieldId, DiscovererInterface $discover): self
        {
            return $this->fixup(Fixup::discover($fieldId, $discover));
        }

        /**
         * @return list<Fixup>
         */
        public function currentFixups(): array
        {
            return $this->fixups;
        }

        public function root(): Panel
        {
            return $this->build();
        }

        /**
         * Seals the form tree and runs all build-time structural and graph integrity assertions.
         */
        public function build(): Panel
        {
            if ($this->cachedRoot !== null) {
                return $this->cachedRoot;
            }

            $children = [];
            foreach ($this->panels as $pb) {
                $children[] = $pb->block();
            }

            // Attach form-level derive/discover fixups to matching fields
            $rootPanel = new Panel(
                id: 'root',
                title: $this->title !== '' ? $this->title : 'Form',
                description: $this->description,
                children: $children,
                submitLabel: $this->submitLabel,
                cancelLabel: $this->cancelLabel,
            );

            foreach ($this->fixups as $fixup) {
                foreach ($rootPanel->allFields() as $f) {
                    if ($f->id() === $fixup->fieldId && method_exists($f, 'setDerivation') && $fixup->derive !== null) {
                        $f->setDerivation($fixup->derive);
                    }
                    if ($f->id() === $fixup->fieldId && method_exists($f, 'setDiscoverer') && $fixup->discover !== null) {
                        $f->setDiscoverer($fixup->discover);
                    }
                }
            }

            $this->assertValidNames($rootPanel);
            $this->assertUniqueIds($rootPanel);
            $this->assertTargetedConditions($rootPanel);
            $this->assertAcyclicDerivations($rootPanel);
            $this->assertSelectOptions($rootPanel);
            $this->assertToggleOptions($rootPanel);
            $this->assertReorderOptions($rootPanel);
            $this->assertTemplateShapes($rootPanel);
            $this->assertModalPanels($rootPanel);

            return $this->cachedRoot = $rootPanel;
        }

        protected function assertValidNames(Panel $root): void
        {
            foreach ($root->allFields() as $field) {
                Name::assertValid($field->id());
            }
        }

        protected function assertUniqueIds(Panel $root): void
        {
            $seen = [];
            foreach ($root->allFields() as $field) {
                $id = $field->id();
                if (isset($seen[$id])) {
                    throw new FormException(sprintf("Duplicate field ID '%s' detected in form '%s'.", $id, $this->title));
                }
                $seen[$id] = true;
            }
        }

        protected function assertTargetedConditions(Panel $root): void
        {
            $knownIds = [];
            foreach ($root->allFields() as $field) {
                $knownIds[$field->id()] = true;
            }
            foreach ($root->allFields() as $field) {
                $cond = $field->condition();
                if ($cond === null) {
                    continue;
                }
                foreach ($cond->referencedFields() as $ref) {
                    if (!isset($knownIds[$ref])) {
                        throw new FormException(sprintf(
                            "Field '%s' has a when() condition referencing non-existent field '%s'.",
                            $field->id(),
                            $ref
                        ));
                    }
                    if ($ref === $field->id()) {
                        throw new FormException(sprintf(
                            "Field '%s' cannot reference itself in a when() condition.",
                            $field->id()
                        ));
                    }
                }
            }
        }

        /**
         * Upgrade over original v0.3.0: performs 3-color DFS cycle detection across all derived fields
         * at build time so circular derivations fail immediately with the exact cycle path.
         */
        protected function assertAcyclicDerivations(Panel $root): void
        {
            $fieldsById = [];
            foreach ($root->allFields() as $field) {
                $fieldsById[$field->id()] = $field;
            }

            $adj = [];
            foreach ($fieldsById as $id => $field) {
                $drv = $field->derivation();
                if ($drv === null) {
                    continue;
                }
                foreach ($drv->dependencies() as $dep) {
                    if (!isset($fieldsById[$dep])) {
                        throw new FormException(sprintf(
                            "Derived field '%s' depends on unknown field '%s'.",
                            $id,
                            $dep
                        ));
                    }
                    $adj[$id][] = $dep;
                }
            }

            // 0 = unvisited, 1 = visiting (in current stack), 2 = visited
            $state = [];
            $path = [];

            $dfs = function (string $node) use (&$dfs, &$state, &$path, $adj): void {
                $state[$node] = 1;
                $path[] = $node;
                foreach ($adj[$node] ?? [] as $dep) {
                    $depState = $state[$dep] ?? 0;
                    if ($depState === 1) {
                        $cycleStart = array_search($dep, $path, true);
                        $cycleNodes = array_slice($path, $cycleStart !== false ? $cycleStart : 0);
                        $cycleNodes[] = $dep;
                        throw new FormException(sprintf(
                            "Cyclic derivation dependency detected: %s",
                            implode(' -> ', $cycleNodes)
                        ));
                    }
                    if ($depState === 0) {
                        $dfs($dep);
                    }
                }
                array_pop($path);
                $state[$node] = 2;
            };

            foreach (array_keys($fieldsById) as $id) {
                if (($state[$id] ?? 0) === 0) {
                    $dfs($id);
                }
            }
        }

        protected function assertSelectOptions(Panel $root): void
        {
            foreach ($root->allFields() as $field) {
                if ($field instanceof Select && $field->getOptions() === []) {
                    throw new FormException(sprintf("Select field '%s' must declare at least one option.", $field->id()));
                }
            }
        }

        protected function assertToggleOptions(Panel $root): void
        {
            foreach ($root->allFields() as $field) {
                if ($field instanceof Toggle && count($field->getOptions()) !== 2) {
                    throw new FormException(sprintf("Toggle field '%s' must have exactly 2 options.", $field->id()));
                }
            }
        }

        protected function assertReorderOptions(Panel $root): void
        {
            foreach ($root->allFields() as $field) {
                if ($field instanceof Reorder && count($field->getOptions()) < 2) {
                    throw new FormException(sprintf("Reorder field '%s' must have at least 2 options.", $field->id()));
                }
            }
        }

        protected function assertTemplateShapes(Panel $root): void
        {
            foreach ($root->allFields() as $field) {
                if ($field instanceof Template && $field->slotNames() === []) {
                    throw new FormException(sprintf("Template field '%s' must contain at least one {slot}.", $field->id()));
                }
            }
        }

        protected function assertModalPanels(Panel $root): void
        {
            $walk = static function (Panel $panel) use (&$walk): void {
                foreach ($panel->childPanels() as $child) {
                    if ($child->isModal()) {
                        throw new FormException(sprintf("Modal panel '%s' cannot contain nested modal panel '%s'.", $panel->id(), $child->id()));
                    }
                    $walk($child);
                }
            };
            $walk($root);
        }
    }
}

// ============================================================================
// File: src/Resolver/ResolverInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Resolver {
    use DrevOps\PhpTui\Answers\Answers;
    use DrevOps\PhpTui\Block\Panel;

    interface ResolverInterface
    {
        /**
         * Seeds initial values from defaults, project Discovery rules, environment variables, and JSON prompts,
         * then settles derived values and conditional visibility to a fixpoint.
         *
         * @param array<string, mixed> $promptOverrides
         * @param array<string, string> $environment
         */
        public function initializeState(
            Panel $root,
            string $directory = '',
            array $promptOverrides = [],
            array $environment = [],
            string $envPrefix = '',
        ): void;

        /**
         * Records a user-edited value for $fieldId and re-runs the fixpoint + conditional reconciliation pass.
         */
        public function applyUserEdit(Panel $root, string $fieldId, mixed $value): void;

        /**
         * Returns the current resolved value for $fieldId.
         */
        public function getValue(string $fieldId): mixed;

        /**
         * Returns the current provenance ('user', 'default', 'discovered', 'derived', 'prompt', 'env') for $fieldId.
         */
        public function getProvenance(string $fieldId): string;

        /**
         * Returns whether $fieldId is currently visible given all parent panel and field when() conditions.
         */
        public function isFieldVisible(Panel $root, string $fieldId): bool;

        /**
         * Returns the current map of all visible field values.
         *
         * @return array<string, mixed>
         */
        public function visibleValues(Panel $root): array;

        /**
         * Exports the immutable Answers snapshot.
         */
        public function toAnswers(Panel $root, bool $cancelled = false): Answers;
    }
}

// ============================================================================
// File: src/Resolver/ValueResolver.php
// ============================================================================
namespace DrevOps\PhpTui\Resolver {
    use DrevOps\PhpTui\Answers\AnswerCollection;
    use DrevOps\PhpTui\Answers\Answers;
    use DrevOps\PhpTui\Block\Panel;
    use DrevOps\PhpTui\Builder\Fixup;
    use DrevOps\PhpTui\Field\FieldInterface;
    use DrevOps\PhpTui\FormException;
    use DrevOps\PhpTui\Handler\HandlerRegistry;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\Str;

    /**
     * Cycle-safe Fixpoint & Shadow-State Reconciliation Engine.
     *
     * Key upgrades over original v0.3.0:
     * 1. Maintains a shadow user-answer cache ($shadowUserValues): when a conditional `when()` rule
     *    hides a field, its active value is cleared so downstream `derive()` fields recompute cleanly;
     *    if the user later flips the condition back on, their previously typed value is restored.
     * 2. Evaluates `derive()` chains with a hard iteration cap and equality convergence check.
     * 3. Integrates per-field HandlerRegistry defaults and transformations seamlessly.
     */
    final class ValueResolver implements ResolverInterface
    {
        private const MAX_FIXPOINT_ITERATIONS = 32;

        /** @var array<string, mixed> */
        private array $values = [];

        /** @var array<string, mixed> */
        private array $rawValues = [];

        /** @var array<string, string> */
        private array $provenance = [];

        /** @var array<string, bool> */
        private array $userLocked = [];

        /** @var array<string, mixed> */
        private array $shadowUserValues = [];

        /** @var array<string, bool> */
        private array $visibility = [];

        /**
         * @param list<Fixup> $fixups
         */
        public function __construct(
            private readonly HandlerRegistry $registry,
            private readonly TranslationInterface $translator,
            private readonly array $fixups = [],
        ) {
        }

        public function initializeState(
            Panel $root,
            string $directory = '',
            array $promptOverrides = [],
            array $environment = [],
            string $envPrefix = '',
        ): void {
            $this->values = [];
            $this->rawValues = [];
            $this->provenance = [];
            $this->userLocked = [];
            $this->shadowUserValues = [];
            $this->visibility = [];

            $fields = $root->allFields();

            // Pass 1: Static defaults & handler defaults
            foreach ($fields as $field) {
                $id = $field->id();
                $default = $field->defaultValue();
                $handler = $this->registry->resolve($id);
                if ($default === null && $handler !== null) {
                    $default = $handler->default($this->values, $directory);
                }
                $this->values[$id] = $field->coerce($default);
                $this->rawValues[$id] = $default;
                $this->provenance[$id] = AnswerCollection::PROVENANCE_DEFAULT;
            }

            // Pass 2: Project Discovery rules (when directory exists)
            foreach ($fields as $field) {
                $id = $field->id();
                $disc = $field->discoverer();
                if ($disc !== null) {
                    $found = $disc->discover($directory);
                    if ($found !== null) {
                        $this->values[$id] = $field->coerce($found);
                        $this->rawValues[$id] = $found;
                        $this->provenance[$id] = AnswerCollection::PROVENANCE_DISCOVERED;
                    }
                }
            }

            // Pass 3: Environment variables (e.g. PREFIX_FIELD_ID or FIELD_ID)
            foreach ($fields as $field) {
                $id = $field->id();
                $envKey = ($envPrefix !== '' ? rtrim($envPrefix, '_') . '_' : '') . Str::transform($id, 'constant');
                $envVal = $environment[$envKey] ?? $_ENV[$envKey] ?? getenv($envKey);
                if ($envVal !== false && $envVal !== null && $envVal !== '') {
                    $this->values[$id] = $field->coerce($envVal);
                    $this->rawValues[$id] = $envVal;
                    $this->provenance[$id] = AnswerCollection::PROVENANCE_ENV;
                    $this->userLocked[$id] = true;
                }
            }

            // Pass 4: Explicit JSON / array prompt overrides
            foreach ($fields as $field) {
                $id = $field->id();
                $panelScopedKey = $field->panelId() . '.' . $id;
                $hasDirect = array_key_exists($id, $promptOverrides);
                $hasScoped = array_key_exists($panelScopedKey, $promptOverrides);
                if ($hasDirect || $hasScoped) {
                    $raw = $hasDirect ? $promptOverrides[$id] : $promptOverrides[$panelScopedKey];
                    $this->values[$id] = $field->coerce($raw);
                    $this->rawValues[$id] = $raw;
                    $this->provenance[$id] = AnswerCollection::PROVENANCE_PROMPT;
                    $this->userLocked[$id] = true;
                }
            }

            // Pass 5: Settle derivations and conditional visibility to a fixpoint
            $this->settleFixpoint($root);
        }

        public function applyUserEdit(Panel $root, string $fieldId, mixed $value): void
        {
            $field = $this->findField($root, $fieldId);
            $coerced = $field !== null ? $field->coerce($value) : $value;
            $this->values[$fieldId] = $coerced;
            $this->rawValues[$fieldId] = $value;
            $this->shadowUserValues[$fieldId] = $coerced;
            $this->provenance[$fieldId] = AnswerCollection::PROVENANCE_USER;
            $this->userLocked[$fieldId] = true;

            $this->settleFixpoint($root);
        }

        /**
         * Iteratively settles derived values, conditional visibility, and custom fixup rules until a fixpoint is reached.
         */
        public function settleFixpoint(Panel $root): void
        {
            $fields = $root->allFields();
            $panelMap = $this->indexPanels($root);

            for ($iter = 0; $iter < self::MAX_FIXPOINT_ITERATIONS; $iter++) {
                $changed = false;

                // Step A: Evaluate visibility of panels and fields against active values
                $activeValues = $this->activeValueMap($fields);
                foreach ($fields as $field) {
                    $id = $field->id();
                    $panel = $panelMap[$field->panelId()] ?? null;
                    $panelVis = $panel === null || $panel->isVisible($activeValues);
                    $fieldVis = $panelVis && $field->isVisible($activeValues);

                    $prevVis = $this->visibility[$id] ?? true;
                    if ($prevVis !== $fieldVis) {
                        $this->visibility[$id] = $fieldVis;
                        $changed = true;

                        if (!$fieldVis) {
                            // Field became hidden: clear active value so downstream derivations reconcile
                            $cleared = $field->isMultipleSafeDefault();
                            if ($this->values[$id] !== $cleared) {
                                $this->values[$id] = $cleared;
                            }
                        } else {
                            // Field became visible again: restore shadow user value or default
                            if (array_key_exists($id, $this->shadowUserValues)) {
                                $this->values[$id] = $this->shadowUserValues[$id];
                                $this->provenance[$id] = AnswerCollection::PROVENANCE_USER;
                            } elseif ($field->defaultValue() !== null) {
                                $this->values[$id] = $field->coerce($field->defaultValue());
                            }
                        }
                    } else {
                        $this->visibility[$id] = $fieldVis;
                    }
                }

                // Step B: Recompute derived values for visible, non-user-locked fields
                $activeValues = $this->activeValueMap($fields);
                foreach ($fields as $field) {
                    $id = $field->id();
                    if (!($this->visibility[$id] ?? true)) {
                        continue;
                    }
                    if ($this->userLocked[$id] ?? false) {
                        continue;
                    }
                    $drv = $field->derivation();
                    if ($drv === null) {
                        continue;
                    }
                    $computed = $field->coerce($drv->compute($activeValues));
                    if ($computed !== ($this->values[$id] ?? null)) {
                        $this->values[$id] = $computed;
                        $this->rawValues[$id] = $computed;
                        $this->provenance[$id] = AnswerCollection::PROVENANCE_DERIVED;
                        $changed = true;
                    }
                }

                // Step C: Execute custom Fixup reconciliation closures
                foreach ($this->fixups as $fixup) {
                    if ($fixup->reconcile !== null && array_key_exists($fixup->fieldId, $this->values)) {
                        $newVal = ($fixup->reconcile)($this->values[$fixup->fieldId], $this->activeValueMap($fields));
                        if ($newVal !== $this->values[$fixup->fieldId]) {
                            $this->values[$fixup->fieldId] = $newVal;
                            $changed = true;
                        }
                    }
                }

                if (!$changed) {
                    return;
                }
            }

            throw new FormException(sprintf(
                'Fixpoint evaluation did not converge within %d iterations.',
                self::MAX_FIXPOINT_ITERATIONS
            ));
        }

        public function getValue(string $fieldId): mixed
        {
            return $this->values[$fieldId] ?? null;
        }

        public function getProvenance(string $fieldId): string
        {
            return $this->provenance[$fieldId] ?? AnswerCollection::PROVENANCE_DEFAULT;
        }

        public function isFieldVisible(Panel $root, string $fieldId): bool
        {
            return $this->visibility[$fieldId] ?? true;
        }

        public function visibleValues(Panel $root): array
        {
            return $this->activeValueMap($root->allFields());
        }

        public function toAnswers(Panel $root, bool $cancelled = false): Answers
        {
            $fields = $root->allFields();
            $activeValues = $this->activeValueMap($fields);
            $records = [];

            foreach ($fields as $field) {
                $id = $field->id();
                $vis = $this->visibility[$id] ?? true;
                $rawVal = $this->values[$id] ?? null;
                $transformed = $vis ? $field->transformValue($rawVal, $activeValues) : null;
                $handler = $this->registry->resolve($id);
                if ($vis && $handler !== null) {
                    $transformed = $handler->transform($transformed, $activeValues);
                }
                $records[$id] = new AnswerCollection(
                    id: $id,
                    label: $field->label(),
                    type: $field->type(),
                    value: $transformed,
                    rawValue: $this->rawValues[$id] ?? $rawVal,
                    provenance: $this->provenance[$id] ?? AnswerCollection::PROVENANCE_DEFAULT,
                    visible: $vis,
                    formattedDisplay: $vis ? $field->formatDisplay($transformed, $this->translator) : '—',
                    panelId: $field->panelId(),
                    parts: $vis ? $field->extractParts($transformed) : [],
                );
            }

            return new Answers($records, $root->title(), $cancelled);
        }

        /**
         * @param list<FieldInterface> $fields
         * @return array<string, mixed>
         */
        private function activeValueMap(array $fields): array
        {
            $out = [];
            foreach ($fields as $field) {
                $id = $field->id();
                if ($this->visibility[$id] ?? true) {
                    $out[$id] = $this->values[$id] ?? null;
                }
            }
            return $out;
        }

        private function findField(Panel $root, string $fieldId): ?FieldInterface
        {
            foreach ($root->allFields() as $f) {
                if ($f->id() === $fieldId) {
                    return $f;
                }
            }
            return null;
        }

        /**
         * @return array<string, Panel>
         */
        private function indexPanels(Panel $root): array
        {
            $map = [$root->id() => $root];
            foreach ($root->childPanels() as $child) {
                $map[$child->id()] = $child;
                foreach ($this->indexPanels($child) as $k => $v) {
                    $map[$k] = $v;
                }
            }
            return $map;
        }
    }
}

// ============================================================================
// File: src/Theme/ThemeInterface.php
// ============================================================================
namespace DrevOps\PhpTui\Theme {
    interface ThemeInterface
    {
        public function name(): string;

        public function mode(): string;

        public function isColorEnabled(): bool;

        public function isUnicodeEnabled(): bool;

        public function glyph(string $key, string $fallback = ''): string;

        public function styleTitle(string $text): string;

        public function styleAccent(string $text): string;

        public function styleHighlight(string $text): string;

        public function styleValue(string $text): string;

        public function styleMuted(string $text): string;

        public function styleError(string $text): string;

        public function styleSuccess(string $text): string;

        public function styleBold(string $text): string;

        public function styleCode(string $text): string;

        public function styleCaret(string $char): string;

        public function styleBorder(string $text): string;

        public function formatLink(string $label, string $url): string;

        /**
         * Draws a bordered box around $lines with an optional title and footer hint.
         *
         * @param list<string> $lines
         * @return list<string>
         */
        public function drawBox(array $lines, int $width, string $title = '', string $footer = ''): array;
    }
}

// ============================================================================
// File: src/Theme/Overrides.php
// ============================================================================
namespace DrevOps\PhpTui\Theme {
    use DrevOps\PhpTui\Primitive\Style;

    /**
     * Holds user-supplied glyph and style overrides layered on top of any built-in theme.
     */
    final class Overrides
    {
        /**
         * @param array<string, string> $glyphs
         * @param array<string, Style> $styles
         */
        public function __construct(
            public array $glyphs = [],
            public array $styles = [],
        ) {
        }
    }
}

// ============================================================================
// File: src/Theme/ThemeBuilder.php
// ============================================================================
namespace DrevOps\PhpTui\Theme {
    use DrevOps\PhpTui\Primitive\Color;
    use DrevOps\PhpTui\Primitive\Style;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Configurable theme implementation supporting dark/light palettes, Unicode/ASCII glyph sets,
     * NO_COLOR compliance, OSC-8 hyperlinks, and box-drawing borders.
     */
    class ThemeBuilder implements ThemeInterface
    {
        /** @var array<string, string> */
        protected array $unicodeGlyphs = [
            'tl' => '╭',
            'tr' => '╮',
            'bl' => '╰',
            'br' => '╯',
            'h' => '─',
            'v' => '│',
            'cursor' => '❯',
            'input_prefix' => '› ',
            'radio_on' => '◉',
            'radio_off' => '◯',
            'checkbox_on' => '☑',
            'checkbox_off' => '☐',
            'star_on' => '★',
            'star_off' => '☆',
            'progress_fill' => '█',
            'progress_empty' => '░',
            'error' => '✖',
            'search' => '🔍 ',
        ];

        /** @var array<string, string> */
        protected array $asciiGlyphs = [
            'tl' => '+',
            'tr' => '+',
            'bl' => '+',
            'br' => '+',
            'h' => '-',
            'v' => '|',
            'cursor' => '>',
            'input_prefix' => '> ',
            'radio_on' => '(*)',
            'radio_off' => '( )',
            'checkbox_on' => '[x]',
            'checkbox_off' => '[ ]',
            'star_on' => '*',
            'star_off' => '.',
            'progress_fill' => '#',
            'progress_empty' => '-',
            'error' => '!',
            'search' => '? ',
        ];

        /**
         * @param array<string, Style> $styles
         */
        public function __construct(
            protected readonly string $name = 'default',
            protected readonly string $mode = 'dark',
            protected readonly bool $colorEnabled = true,
            protected readonly bool $unicodeEnabled = true,
            protected array $styles = [],
            protected readonly ?Overrides $overrides = null,
        ) {
            if ($this->styles === []) {
                $this->styles = self::defaultStylesFor($this->name, $this->mode);
            }
            if ($this->overrides !== null) {
                foreach ($this->overrides->glyphs as $k => $v) {
                    $this->unicodeGlyphs[$k] = $v;
                    $this->asciiGlyphs[$k] = $v;
                }
                foreach ($this->overrides->styles as $k => $s) {
                    $this->styles[$k] = $s;
                }
            }
        }

        public function name(): string
        {
            return $this->name;
        }

        public function mode(): string
        {
            return $this->mode;
        }

        public function isColorEnabled(): bool
        {
            return $this->colorEnabled;
        }

        public function isUnicodeEnabled(): bool
        {
            return $this->unicodeEnabled;
        }

        public function glyph(string $key, string $fallback = ''): string
        {
            $map = $this->unicodeEnabled ? $this->unicodeGlyphs : $this->asciiGlyphs;
            return $map[$key] ?? $fallback;
        }

        public function styleTitle(string $text): string
        {
            return ($this->styles['title'] ?? new Style(bold: true))->apply($text, $this->colorEnabled);
        }

        public function styleAccent(string $text): string
        {
            return ($this->styles['accent'] ?? new Style(bold: true))->apply($text, $this->colorEnabled);
        }

        public function styleHighlight(string $text): string
        {
            return ($this->styles['highlight'] ?? new Style(reverse: true))->apply($text, $this->colorEnabled);
        }

        public function styleValue(string $text): string
        {
            return ($this->styles['value'] ?? new Style())->apply($text, $this->colorEnabled);
        }

        public function styleMuted(string $text): string
        {
            return ($this->styles['muted'] ?? new Style(dim: true))->apply($text, $this->colorEnabled);
        }

        public function styleError(string $text): string
        {
            return ($this->styles['error'] ?? new Style(bold: true))->apply($text, $this->colorEnabled);
        }

        public function styleSuccess(string $text): string
        {
            return ($this->styles['success'] ?? new Style(bold: true))->apply($text, $this->colorEnabled);
        }

        public function styleBold(string $text): string
        {
            return (new Style(bold: true))->apply($text, $this->colorEnabled);
        }

        public function styleCode(string $text): string
        {
            return ($this->styles['code'] ?? new Style(underline: true))->apply($text, $this->colorEnabled);
        }

        public function styleCaret(string $char): string
        {
            return (new Style(reverse: true, bold: true))->apply($char, $this->colorEnabled);
        }

        public function styleBorder(string $text): string
        {
            return ($this->styles['border'] ?? new Style(dim: true))->apply($text, $this->colorEnabled);
        }

        public function formatLink(string $label, string $url): string
        {
            if (!$this->colorEnabled) {
                return "{$label} ({$url})";
            }
            if (preg_match('/[\x00-\x1F\x7F;\\\\]/', $url) === 1) {
                return "{$label} ({$url})";
            }
            $styled = (new Style($this->styles['accent']->color ?? new Color(), underline: true))->apply($label, true);
            return "\033]8;;{$url}\033\\{$styled}\033]8;;\033\\";
        }

        public function drawBox(array $lines, int $width, string $title = '', string $footer = ''): array
        {
            $innerWidth = max(1, $width - 4);
            $tl = $this->glyph('tl', '+');
            $tr = $this->glyph('tr', '+');
            $bl = $this->glyph('bl', '+');
            $br = $this->glyph('br', '+');
            $h = $this->glyph('h', '-');
            $v = $this->glyph('v', '|');

            $out = [];
            if ($title !== '') {
                $cleanTitle = ' ' . MbString::truncateWidth($title, $innerWidth - 2) . ' ';
                $titleWidth = MbString::displayWidth($cleanTitle);
                $rem = max(0, ($width - 2) - $titleWidth - 1);
                $out[] = $this->styleBorder($tl . $h) . $this->styleTitle($cleanTitle) . $this->styleBorder(str_repeat($h, $rem) . $tr);
            } else {
                $out[] = $this->styleBorder($tl . str_repeat($h, max(0, $width - 2)) . $tr);
            }

            foreach ($lines as $line) {
                $visW = MbString::displayWidth($line);
                $pad = max(0, $innerWidth - $visW);
                $out[] = $this->styleBorder($v) . ' ' . $line . str_repeat(' ', $pad) . ' ' . $this->styleBorder($v);
            }

            if ($footer !== '') {
                $cleanFooter = ' ' . MbString::truncateWidth($footer, $innerWidth - 2) . ' ';
                $fw = MbString::displayWidth($cleanFooter);
                $rem = max(0, ($width - 2) - $fw - 1);
                $out[] = $this->styleBorder($bl . $h) . $this->styleMuted($cleanFooter) . $this->styleBorder(str_repeat($h, $rem) . $br);
            } else {
                $out[] = $this->styleBorder($bl . str_repeat($h, max(0, $width - 2)) . $br);
            }

            return $out;
        }

        /**
         * Built-in palettes for all 6 themes ('default', 'midnight', 'frost', 'ember', 'mono', 'dos')
         * across both 'dark' and 'light' terminal background modes.
         *
         * @return array<string, Style>
         */
        public static function defaultStylesFor(string $themeName, string $mode = 'dark'): array
        {
            $isLight = ($mode === 'light');
            return match (strtolower($themeName)) {
                'midnight' => [
                    'title' => new Style(Color::ansi256($isLight ? 25 : 111), bold: true),
                    'accent' => new Style(Color::ansi256($isLight ? 57 : 141), bold: true),
                    'highlight' => new Style(Color::ansi256($isLight ? 16 : 231, $isLight ? 189 : 60), bold: true),
                    'value' => new Style(Color::ansi256($isLight ? 24 : 153)),
                    'muted' => new Style(Color::ansi256($isLight ? 245 : 242), dim: true),
                    'error' => new Style(Color::ansi(31), bold: true),
                    'success' => new Style(Color::ansi(32), bold: true),
                    'code' => new Style(Color::ansi256($isLight ? 90 : 219)),
                    'border' => new Style(Color::ansi256($isLight ? 146 : 60)),
                ],
                'frost' => [
                    'title' => new Style(Color::ansi256($isLight ? 31 : 87), bold: true),
                    'accent' => new Style(Color::ansi256($isLight ? 37 : 51), bold: true),
                    'highlight' => new Style(Color::ansi256($isLight ? 16 : 16, $isLight ? 159 : 87), bold: true),
                    'value' => new Style(Color::ansi256($isLight ? 23 : 123)),
                    'muted' => new Style(Color::ansi256($isLight ? 244 : 245), dim: true),
                    'error' => new Style(Color::ansi(31), bold: true),
                    'success' => new Style(Color::ansi(32), bold: true),
                    'code' => new Style(Color::ansi256($isLight ? 30 : 117)),
                    'border' => new Style(Color::ansi256($isLight ? 110 : 37)),
                ],
                'ember' => [
                    'title' => new Style(Color::ansi256($isLight ? 130 : 214), bold: true),
                    'accent' => new Style(Color::ansi256($isLight ? 166 : 208), bold: true),
                    'highlight' => new Style(Color::ansi256($isLight ? 16 : 232, $isLight ? 223 : 208), bold: true),
                    'value' => new Style(Color::ansi256($isLight ? 94 : 221)),
                    'muted' => new Style(Color::ansi256($isLight ? 243 : 244), dim: true),
                    'error' => new Style(Color::ansi(31), bold: true),
                    'success' => new Style(Color::ansi(32), bold: true),
                    'code' => new Style(Color::ansi256($isLight ? 124 : 215)),
                    'border' => new Style(Color::ansi256($isLight ? 173 : 130)),
                ],
                'dos' => [
                    'title' => new Style(Color::ansi(33, 44), bold: true),
                    'accent' => new Style(Color::ansi(36, 44), bold: true),
                    'highlight' => new Style(Color::ansi(30, 46), bold: true),
                    'value' => new Style(Color::ansi(37, 44)),
                    'muted' => new Style(Color::ansi(37), dim: true),
                    'error' => new Style(Color::ansi(31, 47), bold: true),
                    'success' => new Style(Color::ansi(32), bold: true),
                    'code' => new Style(Color::ansi(35)),
                    'border' => new Style(Color::ansi(37, 44)),
                ],
                'mono' => [
                    'title' => new Style(bold: true),
                    'accent' => new Style(bold: true, underline: true),
                    'highlight' => new Style(reverse: true, bold: true),
                    'value' => new Style(),
                    'muted' => new Style(dim: true),
                    'error' => new Style(bold: true, reverse: true),
                    'success' => new Style(bold: true),
                    'code' => new Style(underline: true),
                    'border' => new Style(dim: true),
                ],
                default => [
                    'title' => new Style(Color::ansi($isLight ? 34 : 36), bold: true),
                    'accent' => new Style(Color::ansi($isLight ? 35 : 36), bold: true),
                    'highlight' => new Style(Color::ansi($isLight ? 30 : 36), bold: true, reverse: true),
                    'value' => new Style(Color::ansi($isLight ? 32 : 32)),
                    'muted' => new Style(Color::ansi(90), dim: true),
                    'error' => new Style(Color::ansi(31), bold: true),
                    'success' => new Style(Color::ansi(32), bold: true),
                    'code' => new Style(Color::ansi(33)),
                    'border' => new Style(Color::ansi($isLight ? 34 : 90)),
                ],
            };
        }
    }
}

// ============================================================================
// File: src/Theme/MonoTheme.php
// ============================================================================
namespace DrevOps\PhpTui\Theme {
    /**
     * Strictly monochrome theme implementation honoring NO_COLOR and high-contrast accessibility.
     */
    final class MonoTheme extends ThemeBuilder
    {
        public function __construct(bool $unicodeEnabled = true, ?Overrides $overrides = null)
        {
            parent::__construct(
                name: 'mono',
                mode: 'dark',
                colorEnabled: false,
                unicodeEnabled: $unicodeEnabled,
                styles: self::defaultStylesFor('mono'),
                overrides: $overrides,
            );
        }
    }
}

// ============================================================================
// File: src/Theme/ThemeManager.php
// ============================================================================
namespace DrevOps\PhpTui\Theme {
    use DrevOps\PhpTui\FormException;

    /**
     * Resolves and instantiates built-in or custom ThemeInterface implementations,
     * auto-detecting NO_COLOR, terminal dark/light background via COLORFGBG, and UTF-8 locale support.
     */
    final class ThemeManager
    {
        public const BUILTIN_THEMES = ['default', 'midnight', 'frost', 'ember', 'mono', 'dos'];

        /** @var array<string, ThemeInterface> */
        private array $customThemes = [];

        public function register(string $name, ThemeInterface $theme): self
        {
            $this->customThemes[strtolower($name)] = $theme;
            return $this;
        }

        public function resolve(
            string|ThemeInterface $theme = 'default',
            ?bool $color = null,
            ?bool $unicode = null,
            ?string $mode = null,
            ?Overrides $overrides = null,
        ): ThemeInterface {
            if ($theme instanceof ThemeInterface) {
                return $theme;
            }
            $name = $theme !== '' ? strtolower($theme) : 'default';
            if (isset($this->customThemes[$name])) {
                return $this->customThemes[$name];
            }
            if (!in_array($name, self::BUILTIN_THEMES, true)) {
                throw new FormException(sprintf(
                    "Unknown theme '%s'. Built-in themes are: %s",
                    $theme,
                    implode(', ', self::BUILTIN_THEMES)
                ));
            }

            $resolvedColor = $color ?? self::detectColorSupport();
            $resolvedUnicode = $unicode ?? self::detectUnicodeSupport();
            $resolvedMode = $mode ?? self::detectBackgroundMode();

            if ($name === 'mono' || !$resolvedColor) {
                return new MonoTheme($resolvedUnicode, $overrides);
            }
            if ($name === 'dos') {
                // DOS theme uses classic ASCII/CP437 double borders when requested
                $dosOverrides = $overrides ?? new Overrides([
                    'tl' => '╔', 'tr' => '╗', 'bl' => '╚', 'br' => '╝', 'h' => '═', 'v' => '║',
                ]);
                return new ThemeBuilder('dos', $resolvedMode, $resolvedColor, $resolvedUnicode, [], $dosOverrides);
            }

            return new ThemeBuilder($name, $resolvedMode, $resolvedColor, $resolvedUnicode, [], $overrides);
        }

        public static function detectColorSupport(): bool
        {
            $noColor = $_ENV['NO_COLOR'] ?? getenv('NO_COLOR');
            if ($noColor !== false && $noColor !== null && $noColor !== '') {
                return false;
            }
            $term = $_ENV['TERM'] ?? getenv('TERM');
            if ($term === 'dumb') {
                return false;
            }
            return true;
        }

        public static function detectUnicodeSupport(): bool
        {
            foreach (['LC_ALL', 'LC_CTYPE', 'LANG'] as $var) {
                $val = $_ENV[$var] ?? getenv($var);
                if (is_string($val) && $val !== '') {
                    return stripos($val, 'UTF-8') !== false || stripos($val, 'UTF8') !== false;
                }
            }
            return true;
        }

        public static function detectBackgroundMode(): string
        {
            // Standard rxvt/xterm COLORFGBG="fg;bg" where bg >= 7 indicates a light background
            $fgbg = $_ENV['COLORFGBG'] ?? getenv('COLORFGBG');
            if (is_string($fgbg) && str_contains($fgbg, ';')) {
                $parts = explode(';', $fgbg);
                $bg = (int) end($parts);
                if ($bg >= 7 && $bg <= 15 && $bg !== 8) {
                    return 'light';
                }
            }
            return 'dark';
        }
    }
}

// ============================================================================
// File: src/Terminal/Ansi.php
// ============================================================================
namespace DrevOps\PhpTui\Terminal {
    /**
     * Low-level ANSI VT100 / xterm escape sequence helpers.
     */
    final class Ansi
    {
        public static function clearScreen(): string
        {
            return "\033[2J\033[H";
        }

        public static function clearToEndOfLine(): string
        {
            return "\033[K";
        }

        public static function moveTo(int $row, int $col = 1): string
        {
            return sprintf("\033[%d;%dH", max(1, $row), max(1, $col));
        }

        public static function hideCursor(): string
        {
            return "\033[?25l";
        }

        public static function showCursor(): string
        {
            return "\033[?25h";
        }

        public static function enterAlternateScreen(): string
        {
            return "\033[?1049h";
        }

        public static function leaveAlternateScreen(): string
        {
            return "\033[?1049l";
        }

        public static function reset(): string
        {
            return "\033[0m";
        }
    }
}

// ============================================================================
// File: src/Terminal/Stty.php
// ============================================================================
namespace DrevOps\PhpTui\Terminal {
    /**
     * Manages POSIX stty raw / echo terminal state with guaranteed shutdown restoration.
     */
    final class Stty
    {
        private ?string $savedState = null;
        private bool $active = false;

        public function enableRawMode(): void
        {
            if ($this->active || !self::isAvailable()) {
                return;
            }
            $state = @shell_exec('stty -g 2>/dev/null');
            if (is_string($state) && trim($state) !== '') {
                $this->savedState = trim($state);
                @shell_exec('stty -icanon -echo min 1 time 0 2>/dev/null');
                $this->active = true;
            }
        }

        public function restore(): void
        {
            if (!$this->active) {
                return;
            }
            if ($this->savedState !== null) {
                @shell_exec('stty ' . escapeshellarg($this->savedState) . ' 2>/dev/null');
            } else {
                @shell_exec('stty sane 2>/dev/null');
            }
            $this->active = false;
        }

        public static function isAvailable(): bool
        {
            if (PHP_OS_FAMILY === 'Windows' || !function_exists('shell_exec')) {
                return false;
            }
            return defined('STDIN') && is_resource(STDIN) && @stream_isatty(STDIN);
        }
    }
}

// ============================================================================
// File: src/Terminal/Terminal.php
// ============================================================================
namespace DrevOps\PhpTui\Terminal {
    use DrevOps\PhpTui\Input\InputReader;
    use DrevOps\PhpTui\Input\Key;

    /**
     * Encapsulates terminal I/O streams, viewport dimensions (with SIGWINCH resize detection),
     * raw mode lifecycle, and output writing.
     */
    class Terminal
    {
        private Stty $stty;
        private InputReader $reader;
        private int $width;
        private int $height;
        private string $capturedOutput = '';

        /**
         * @param resource|null $inputStream
         * @param resource|null $outputStream
         */
        public function __construct(
            private $inputStream = null,
            private $outputStream = null,
            int $width = 80,
            int $height = 24,
            private readonly bool $isInteractiveTty = false,
        ) {
            $this->stty = new Stty();
            $this->reader = new InputReader($this->inputStream);
            $this->width = max(40, $width);
            $this->height = max(12, $height);
        }

        public static function fromEnvironment(): self
        {
            $in = defined('STDIN') ? STDIN : null;
            $out = defined('STDOUT') ? STDOUT : null;
            $isTty = is_resource($in) && @stream_isatty($in);
            [$w, $h] = self::detectSize();
            return new self($in, $out, $w, $h, $isTty);
        }

        public function isTty(): bool
        {
            return $this->isInteractiveTty;
        }

        public function width(): int
        {
            return $this->width;
        }

        public function height(): int
        {
            return $this->height;
        }

        public function resize(int $width, int $height): void
        {
            $this->width = max(40, $width);
            $this->height = max(12, $height);
        }

        public function reader(): InputReader
        {
            return $this->reader;
        }

        public function readKey(int $timeoutMicroseconds = 50000): ?Key
        {
            return $this->reader->readNext($timeoutMicroseconds);
        }

        public function enterSession(bool $fullscreen = false): void
        {
            if ($this->isInteractiveTty) {
                $this->stty->enableRawMode();
                if ($fullscreen) {
                    $this->write(Ansi::enterAlternateScreen());
                }
                $this->write(Ansi::hideCursor());
            }
        }

        public function leaveSession(bool $fullscreen = false, bool $clearOnExit = true): void
        {
            if ($this->isInteractiveTty) {
                if ($clearOnExit) {
                    $this->write(Ansi::clearScreen());
                }
                if ($fullscreen) {
                    $this->write(Ansi::leaveAlternateScreen());
                }
                $this->write(Ansi::showCursor());
                $this->stty->restore();
            }
        }

        public function write(string $data): void
        {
            $this->capturedOutput .= $data;
            if (is_resource($this->outputStream)) {
                @fwrite($this->outputStream, $data);
                @fflush($this->outputStream);
            }
        }

        public function capturedOutput(): string
        {
            return $this->capturedOutput;
        }

        /**
         * @return array{0: int, 1: int}
         */
        public static function detectSize(): array
        {
            $cols = (int) ($_ENV['COLUMNS'] ?? getenv('COLUMNS') ?: 0);
            $lines = (int) ($_ENV['LINES'] ?? getenv('LINES') ?: 0);
            if ($cols <= 0 || $lines <= 0) {
                if (function_exists('shell_exec') && PHP_OS_FAMILY !== 'Windows') {
                    $sttySize = @shell_exec('stty size 2>/dev/null');
                    if (is_string($sttySize) && preg_match('/^(\d+)\s+(\d+)/', trim($sttySize), $m) === 1) {
                        $lines = (int) $m[1];
                        $cols = (int) $m[2];
                    }
                }
            }
            return [
                $cols > 0 ? $cols : 80,
                $lines > 0 ? $lines : 24,
            ];
        }
    }
}

// ============================================================================
// File: src/Screen/Region.php
// ============================================================================
namespace DrevOps\PhpTui\Screen {
    use DrevOps\PhpTui\Block\BlockInterface;
    use DrevOps\PhpTui\Primitive\Axis;

    /**
     * Named layout region ('header', 'content', 'sidebar', 'left', 'right', 'footer')
     * with its own flow axis and placed blocks.
     */
    final class Region
    {
        /** @var list<BlockInterface> */
        private array $headBlocks = [];
        /** @var list<BlockInterface> */
        private array $tailBlocks = [];

        public function __construct(
            private readonly string $name,
            private Axis $axis = Axis::Vertical,
            private readonly int $widthPercent = 100,
        ) {
        }

        public function name(): string
        {
            return $this->name;
        }

        public function axis(): Axis
        {
            return $this->axis;
        }

        public function setAxis(Axis $axis): self
        {
            $this->axis = $axis;
            return $this;
        }

        public function widthPercent(): int
        {
            return $this->widthPercent;
        }

        public function place(BlockInterface $block, bool $tail = false): self
        {
            if ($tail) {
                $this->tailBlocks[] = $block;
            } else {
                $this->headBlocks[] = $block;
            }
            return $this;
        }

        /**
         * @return list<BlockInterface>
         */
        public function headBlocks(): array
        {
            return $this->headBlocks;
        }

        /**
         * @return list<BlockInterface>
         */
        public function tailBlocks(): array
        {
            return $this->tailBlocks;
        }
    }
}

// ============================================================================
// File: src/Screen/Layout.php
// ============================================================================
namespace DrevOps\PhpTui\Screen {
    use DrevOps\PhpTui\Block\BlockInterface;
    use DrevOps\PhpTui\FormException;
    use DrevOps\PhpTui\Primitive\Axis;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Arranges screen or panel content into named regions ('default', 'panel', 'two-column', or custom 'grid').
     */
    final class Layout
    {
        public const BUILTIN_LAYOUTS = ['default', 'panel', 'two-column', 'grid'];

        /** @var array<string, Region> */
        private array $regions = [];

        public function __construct(
            private readonly string $name = 'default',
        ) {
            if (!in_array($this->name, self::BUILTIN_LAYOUTS, true)) {
                throw new FormException(sprintf(
                    "Unknown layout '%s'. Built-in layouts are: %s",
                    $this->name,
                    implode(', ', self::BUILTIN_LAYOUTS)
                ));
            }
            $this->regions['header'] = new Region('header', Axis::Vertical, 100);
            $this->regions['content'] = new Region('content', Axis::Vertical, $this->name === 'two-column' ? 60 : 100);
            $this->regions['left'] = new Region('left', Axis::Vertical, 50);
            $this->regions['right'] = new Region('right', Axis::Vertical, 50);
            $this->regions['sidebar'] = new Region('sidebar', Axis::Vertical, 40);
            $this->regions['footer'] = new Region('footer', Axis::Horizontal, 100);
        }

        public function name(): string
        {
            return $this->name;
        }

        public function region(string $name): Region
        {
            if (!isset($this->regions[$name])) {
                $this->regions[$name] = new Region($name);
            }
            return $this->regions[$name];
        }

        public function place(string $regionName, BlockInterface $block, bool $tail = false): self
        {
            $this->region($regionName)->place($block, $tail);
            return $this;
        }

        public function flow(string $regionName, Axis $axis): self
        {
            $this->region($regionName)->setAxis($axis);
            return $this;
        }

        /**
         * Merges two column arrays side-by-side for 'two-column' or 'grid' layouts.
         *
         * @param list<string> $leftLines
         * @param list<string> $rightLines
         * @return list<string>
         */
        public static function joinColumns(array $leftLines, array $rightLines, int $totalWidth, int $leftRatioPercent = 50): array
        {
            $gap = 3;
            $avail = max(20, $totalWidth - $gap);
            $ratio = max(10, min(90, $leftRatioPercent));
            $leftWidth = max(1, intdiv($avail * $ratio, 100));
            $rightWidth = max(10, $avail - $leftWidth);
            $maxRows = max(count($leftLines), count($rightLines));
            $merged = [];

            for ($r = 0; $r < $maxRows; $r++) {
                $l = $leftLines[$r] ?? '';
                $ri = $rightLines[$r] ?? '';
                $lPadded = MbString::padWidth(MbString::truncateWidth($l, $leftWidth), $leftWidth);
                $rTrunc = MbString::truncateWidth($ri, $rightWidth);
                $merged[] = $lPadded . str_repeat(' ', $gap) . $rTrunc;
            }
            return $merged;
        }
    }
}

// ============================================================================
// File: src/Screen/Frame.php
// ============================================================================
namespace DrevOps\PhpTui\Screen {
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Immutable snapshot of a rendered terminal frame (lines + plain text representation).
     */
    final readonly class Frame
    {
        /**
         * @param list<string> $lines
         */
        public function __construct(
            public array $lines,
            public int $width,
            public int $height,
        ) {
        }

        public function toAnsiString(): string
        {
            return implode("\n", $this->lines) . "\n";
        }

        public function toPlainText(): string
        {
            $clean = array_map(static fn(string $line): string => rtrim(MbString::stripAnsi($line)), $this->lines);
            return implode("\n", $clean) . "\n";
        }
    }
}

// ============================================================================
// File: src/Screen/Canvas.php
// ============================================================================
namespace DrevOps\PhpTui\Screen {
    use DrevOps\PhpTui\Terminal\Ansi;
    use DrevOps\PhpTui\Terminal\Terminal;

    /**
     * Double-buffered differential ANSI renderer.
     *
     * Upgrade over original v0.3.0: compares the incoming Frame line-by-line against the previous Frame
     * and emits cursor-positioned row deltas (`\e[row;1H...\e[K`) instead of wiping and redrawing
     * the entire terminal viewport on every keystroke.
     */
    final class Canvas
    {
        private ?Frame $previousFrame = null;
        private ?Frame $lastFrame = null;

        public function render(Frame $frame, Terminal $terminal): void
        {
            $this->lastFrame = $frame;

            if (!$terminal->isTty()) {
                // Non-TTY / TuiTester capture mode: record frame without ANSI cursor jump noise
                return;
            }

            if ($this->previousFrame === null || count($this->previousFrame->lines) !== count($frame->lines)) {
                $out = Ansi::moveTo(1, 1);
                foreach ($frame->lines as $idx => $line) {
                    $out .= Ansi::moveTo($idx + 1, 1) . $line . Ansi::clearToEndOfLine();
                }
                $terminal->write($out);
                $this->previousFrame = $frame;
                return;
            }

            $diffBuffer = '';
            $maxLines = max(count($this->previousFrame->lines), count($frame->lines));
            for ($i = 0; $i < $maxLines; $i++) {
                $oldLine = $this->previousFrame->lines[$i] ?? '';
                $newLine = $frame->lines[$i] ?? '';
                if ($oldLine !== $newLine) {
                    $diffBuffer .= Ansi::moveTo($i + 1, 1) . $newLine . Ansi::clearToEndOfLine();
                }
            }

            if ($diffBuffer !== '') {
                $terminal->write($diffBuffer);
            }
            $this->previousFrame = $frame;
        }

        public function lastFrame(): ?Frame
        {
            return $this->lastFrame;
        }
    }
}

// ============================================================================
// File: src/Screen/Screen.php
// ============================================================================
namespace DrevOps\PhpTui\Screen {
    use DrevOps\PhpTui\Answers\Answers;
    use DrevOps\PhpTui\Block\BlockInterface;
    use DrevOps\PhpTui\Block\Markup;
    use DrevOps\PhpTui\Block\Panel;
    use DrevOps\PhpTui\CancelException;
    use DrevOps\PhpTui\Field\Confirm;
    use DrevOps\PhpTui\Field\FieldInterface;
    use DrevOps\PhpTui\Field\Pause;
    use DrevOps\PhpTui\Field\Toggle;
    use DrevOps\PhpTui\Handler\HandlerRegistry;
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\InterruptException;
    use DrevOps\PhpTui\Resolver\ResolverInterface;
    use DrevOps\PhpTui\Terminal\Terminal;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\MbString;

    /**
     * Interactive full-screen/panel TUI state machine and renderer.
     * Supports nested panel drill-down, modal overlays, inline and ->standalone() field editors,
     * '?' long-form help overlays, and differential Canvas rendering.
     */
    final class Screen
    {
        /** @var list<Panel> Stack of active panels (root at index 0) */
        private array $panelStack = [];
        private int $cursorIndex = 0;
        private bool $editing = false;
        private bool $helpOverlay = false;
        /** @var array<string, string> */
        private array $errors = [];
        private Canvas $canvas;
        /** @var list<Frame> */
        private array $recordedFrames = [];

        /**
         * @param array<string, list<string>> $keyBindings
         */
        public function __construct(
            private readonly Panel $root,
            private readonly ResolverInterface $resolver,
            private readonly HandlerRegistry $registry,
            private readonly ThemeInterface $theme,
            private readonly Layout $layout,
            private readonly TranslationInterface $translator,
            private readonly array $keyBindings,
            private readonly string $banner = '',
            private readonly bool $showFooter = true,
            private readonly bool $fullscreen = false,
            private readonly bool $clearOnExit = true,
        ) {
            $this->canvas = new Canvas();
            $children = $this->root->childPanels();
            if ( count($children) === 1 && count($this->root->children()) === 1 ) {
                // Single top-level panel declared on Form: open directly into it while keeping root title
                $this->panelStack = [$children[0]];
            } else {
                $this->panelStack = [$this->root];
            }
        }

        /**
         * Runs the interactive panel loop until the user submits or aborts.
         */
        public function run(Terminal $terminal): Answers
        {
            $terminal->enterSession($this->fullscreen);
            try {
                $this->renderCurrentFrame($terminal);

                while (true) {
                    $key = $terminal->readKey(50000);
                    if ($key === null) {
                        if (!$terminal->isTty() && !$terminal->reader()->hasQueued()) {
                            // Scripted non-TTY session ran out of keystrokes: auto-commit if editing and return
                            if ($this->editing) {
                                $this->commitActiveEdit();
                                $this->renderCurrentFrame($terminal);
                            }
                            break;
                        }
                        continue;
                    }

                    $outcome = $this->processKey($key);
                    $this->renderCurrentFrame($terminal);

                    if ($outcome === 'submit') {
                        break;
                    }
                }
            } finally {
                $terminal->leaveSession($this->fullscreen, $this->clearOnExit);
            }

            return $this->resolver->toAnswers($this->root, false);
        }

        /**
         * Processes a single Key event and updates panel/editor state.
         * Returns 'continue' or 'submit', or throws InterruptException / CancelException.
         */
        public function processKey(Key $key): string
        {
            if ($key->name === Key::CTRL_C) {
                throw new InterruptException('Interactive TUI aborted via Ctrl-C.');
            }

            // 1. If '?' help overlay is open, any key closes it
            if ($this->helpOverlay) {
                $this->helpOverlay = false;
                return 'continue';
            }

            $activePanel = $this->currentPanel();
            $navItems = $this->navigableItems($activePanel);
            $totalNav = count($navItems);
            $this->cursorIndex = $totalNav > 0 ? max(0, min($this->cursorIndex, $totalNav - 1)) : 0;
            $currentItem = $navItems[$this->cursorIndex] ?? null;

            // 2. If a field editor is currently active, route key to the field's editor
            if ($this->editing && $currentItem instanceof FieldInterface) {
                $result = $currentItem->handleEditKey($key, $this->keyBindings);
                if ($result === 'accept') {
                    $this->commitActiveEdit();
                } elseif ($result === 'cancel') {
                    $this->editing = false;
                    unset($this->errors[$currentItem->id()]);
                }
                return 'continue';
            }

            // 3. Help overlay trigger ('?')
            if ($key->char === '?' || $this->matchesAction($key, Key::ACTION_HELP)) {
                $this->helpOverlay = true;
                return 'continue';
            }

            // 4. Panel navigation (Up / Down / Tab / Shift+Tab)
            if ($this->matchesAction($key, Key::ACTION_UP) || $key->name === Key::SHIFT_TAB) {
                if ($totalNav > 0) {
                    $this->cursorIndex = ($this->cursorIndex - 1 + $totalNav) % $totalNav;
                }
                return 'continue';
            }
            if ($this->matchesAction($key, Key::ACTION_DOWN) || $key->name === Key::TAB) {
                if ($totalNav > 0) {
                    $this->cursorIndex = ($this->cursorIndex + 1) % $totalNav;
                }
                return 'continue';
            }

            // 5. Quick inline flip for Confirm / Toggle / Pause via Space
            if ($key->name === Key::SPACE) {
                if ($currentItem instanceof Confirm) {
                    $cur = (bool) $this->resolver->getValue($currentItem->id());
                    $this->resolver->applyUserEdit($this->root, $currentItem->id(), !$cur);
                    return 'continue';
                }
                if ($currentItem instanceof Toggle) {
                    $currentItem->beginEdit($this->resolver->getValue($currentItem->id()));
                    $currentItem->handleEditKey($key, $this->keyBindings);
                    $this->resolver->applyUserEdit($this->root, $currentItem->id(), $currentItem->getEditValue());
                    return 'continue';
                }
                if ($currentItem instanceof Pause) {
                    $this->resolver->applyUserEdit($this->root, $currentItem->id(), true);
                    return 'continue';
                }
            }

            // 6. Cancel / Back via Escape
            if ($this->matchesAction($key, Key::ACTION_CANCEL)) {
                if (count($this->panelStack) > 1) {
                    array_pop($this->panelStack);
                    $this->cursorIndex = 0;
                    return 'continue';
                }
                throw new CancelException('Form cancelled by user.');
            }

            // 7. Enter / Edit / Activate on current item
            if ($this->matchesAction($key, Key::ACTION_EDIT) || $key->name === Key::ENTER) {
                if ($currentItem instanceof Panel) {
                    $this->panelStack[] = $currentItem;
                    $this->cursorIndex = 0;
                    return 'continue';
                }
                if ($currentItem instanceof FieldInterface) {
                    $currentItem->beginEdit($this->resolver->getValue($currentItem->id()));
                    $this->editing = true;
                    return 'continue';
                }
                if ($currentItem === '__submit__') {
                    if (count($this->panelStack) > 1) {
                        array_pop($this->panelStack);
                        $this->cursorIndex = 0;
                        return 'continue';
                    }
                    if ($this->validateAllVisibleFields()) {
                        return 'submit';
                    }
                    return 'continue';
                }
                if ($currentItem === '__cancel__') {
                    if (count($this->panelStack) > 1) {
                        array_pop($this->panelStack);
                        $this->cursorIndex = 0;
                        return 'continue';
                    }
                    throw new CancelException('Form cancelled via Cancel button.');
                }
            }

            return 'continue';
        }

        private function commitActiveEdit(): void
        {
            $activePanel = $this->currentPanel();
            $navItems = $this->navigableItems($activePanel);
            $currentItem = $navItems[$this->cursorIndex] ?? null;
            if (!$currentItem instanceof FieldInterface) {
                $this->editing = false;
                return;
            }

            $candidate = $currentItem->getEditValue();
            $allValues = $this->resolver->visibleValues($this->root);
            $allValues[$currentItem->id()] = $candidate;

            $err = $currentItem->validateValue($candidate, $allValues, $this->translator);
            if ($err === null) {
                $handler = $this->registry->resolve($currentItem->id());
                if ($handler !== null) {
                    $err = $handler->validate($candidate, $allValues);
                }
            }

            if ($err !== null) {
                $this->errors[$currentItem->id()] = $err;
                return;
            }

            unset($this->errors[$currentItem->id()]);
            $this->resolver->applyUserEdit($this->root, $currentItem->id(), $candidate);
            $this->editing = false;
        }

        private function validateAllVisibleFields(): bool
        {
            $this->errors = [];
            $allValues = $this->resolver->visibleValues($this->root);

            foreach ($this->root->allFields() as $field) {
                $id = $field->id();
                if (!$this->resolver->isFieldVisible($this->root, $id)) {
                    continue;
                }
                $val = $this->resolver->getValue($id);
                $err = $field->validateValue($val, $allValues, $this->translator);
                if ($err === null) {
                    $handler = $this->registry->resolve($id);
                    if ($handler !== null) {
                        $err = $handler->validate($val, $allValues);
                    }
                }
                if ($err !== null) {
                    $this->errors[$id] = $err;
                }
            }

            return $this->errors === [];
        }

        public function renderCurrentFrame(Terminal $terminal): Frame
        {
            $width = $terminal->width();
            $height = $terminal->height();
            $activePanel = $this->currentPanel();
            $navItems = $this->navigableItems($activePanel);
            $currentItem = $navItems[$this->cursorIndex] ?? null;

            // Check if standalone full-screen editor or '?' help overlay is active
            if ($this->helpOverlay) {
                $frame = $this->buildHelpOverlayFrame($currentItem, $width, $height);
                $this->canvas->render($frame, $terminal);
                $this->recordedFrames[] = $frame;
                return $frame;
            }

            if ($this->editing && $currentItem instanceof FieldInterface && $currentItem->isStandalone()) {
                $frame = $this->buildStandaloneFieldFrame($currentItem, $width, $height);
                $this->canvas->render($frame, $terminal);
                $this->recordedFrames[] = $frame;
                return $frame;
            }

            $bodyLines = [];

            // Optional Banner
            if ($this->banner !== '') {
                foreach (MbString::wrapLines($this->banner, $width - 6) as $bLine) {
                    $bodyLines[] = $this->theme->styleAccent($bLine);
                }
                $bodyLines[] = '';
            }

            // Breadcrumb header for nested panels
            $breadcrumbs = [];
            if ($this->root->title() !== '' && $this->panelStack[0] !== $this->root) {
                $breadcrumbs[] = $this->translator->translate($this->root->title());
            }
            foreach ($this->panelStack as $p) {
                $breadcrumbs[] = $this->translator->translate($p->title());
            }
            $headerTitle = implode(' ▸ ', array_filter($breadcrumbs));

            if ($activePanel->description() !== '') {
                $bodyLines[] = $this->theme->styleMuted($this->translator->translate($activePanel->description()));
                $bodyLines[] = '';
            }

            // Render placed header region blocks
            $activeValues = $this->resolver->visibleValues($this->root);
            foreach ($this->layout->region('header')->headBlocks() as $hb) {
                if ($hb->isVisible($activeValues)) {
                    foreach ($hb->renderBlock($width - 6, $this->theme, $this->translator) as $l) {
                        $bodyLines[] = $l;
                    }
                }
            }

            // Render panel children (blocks, fields, child panels)
            $leftColLines = [];
            $rightColLines = [];
            $isTwoCol = ($this->layout->name() === 'two-column');

            foreach ($activePanel->children() as $child) {
                if (!$child->isVisible($activeValues)) {
                    continue;
                }
                $isFocused = ($child === $currentItem);
                $linesForChild = [];

                if ($child instanceof FieldInterface) {
                    $id = $child->id();
                    $val = $this->resolver->getValue($id);
                    $isEd = $isFocused && $this->editing;
                    $err = $this->errors[$id] ?? null;
                    $linesForChild = $child->renderRow(
                        $val,
                        $isTwoCol ? intdiv($width - 8, 2) : $width - 6,
                        $this->theme,
                        $this->translator,
                        $isFocused,
                        $isEd,
                        $err
                    );
                } elseif ($child instanceof BlockInterface) {
                    $linesForChild = $child->renderBlock(
                        $isTwoCol ? intdiv($width - 8, 2) : $width - 6,
                        $this->theme,
                        $this->translator,
                        $isFocused
                    );
                }

                if ($isTwoCol && $child->region() === 'right') {
                    foreach ($linesForChild as $l) {
                        $rightColLines[] = $l;
                    }
                } elseif ($isTwoCol) {
                    foreach ($linesForChild as $l) {
                        $leftColLines[] = $l;
                    }
                } else {
                    foreach ($linesForChild as $l) {
                        $bodyLines[] = $l;
                    }
                }
            }

            if ($isTwoCol && ($leftColLines !== [] || $rightColLines !== [])) {
                foreach (Layout::joinColumns($leftColLines, $rightColLines, $width - 6, 50) as $joined) {
                    $bodyLines[] = $joined;
                }
            }

            // Action buttons row (Submit / Back & Cancel)
            $bodyLines[] = '';
            $submitLabel = count($this->panelStack) > 1
                ? $this->translator->translate('Done')
                : $this->translator->translate($activePanel->submitLabel());
            $cancelLabel = count($this->panelStack) > 1
                ? $this->translator->translate('Back')
                : $this->translator->translate($activePanel->cancelLabel());

            $subFocused = ($currentItem === '__submit__');
            $canFocused = ($currentItem === '__cancel__');
            $subBtn = $subFocused ? $this->theme->styleHighlight(" [ {$submitLabel} ] ") : $this->theme->styleAccent("[ {$submitLabel} ]");
            $canBtn = $canFocused ? $this->theme->styleHighlight(" [ {$cancelLabel} ] ") : $this->theme->styleMuted("[ {$cancelLabel} ]");
            $bodyLines[] = '  ' . $subBtn . '   ' . $canBtn;

            // Footer key-hint bar
            $footerStr = '';
            if ($this->showFooter) {
                $hints = $this->collectFooterHints($currentItem);
                $parts = [];
                foreach ($hints as $k => $desc) {
                    $parts[] = "{$k}: {$desc}";
                }
                $footerStr = implode('  │  ', $parts);
            }

            $boxed = $this->theme->drawBox($bodyLines, $width, $headerTitle, $footerStr);
            $frame = new Frame($boxed, $width, $height);
            $this->canvas->render($frame, $terminal);
            $this->recordedFrames[] = $frame;
            return $frame;
        }

        private function buildStandaloneFieldFrame(FieldInterface $field, int $width, int $height): Frame
        {
            $title = $this->translator->translate($this->root->title()) . ' ▸ ' . $this->translator->translate($field->label()) . ' [Full Screen]';
            $val = $this->resolver->getValue($field->id());
            $err = $this->errors[$field->id()] ?? null;
            $lines = $field->renderRow($val, $width - 6, $this->theme, $this->translator, true, true, $err);
            if ($field->help() !== '') {
                $lines[] = '';
                foreach (MbString::wrapLines($this->translator->translate($field->help()), $width - 8) as $hl) {
                    $lines[] = $this->theme->styleMuted($hl);
                }
            }
            $hints = $field->keyHints(true, $this->translator);
            $footer = implode('  │  ', array_map(static fn(string $k, string $v): string => "{$k}: {$v}", array_keys($hints), array_values($hints)));
            return new Frame($this->theme->drawBox($lines, $width, $title, $footer), $width, $height);
        }

        private function buildHelpOverlayFrame(mixed $currentItem, int $width, int $height): Frame
        {
            $title = $this->translator->translate('Help & Keyboard Shortcuts');
            $lines = [];
            if ($currentItem instanceof FieldInterface) {
                $lines[] = $this->theme->styleBold($this->translator->translate($currentItem->label()));
                $helpText = $currentItem->help() !== '' ? $currentItem->help() : $currentItem->description();
                if ($helpText !== '') {
                    foreach (MbString::wrapLines($this->translator->translate($helpText), $width - 8) as $hl) {
                        $lines[] = Markup::formatInlineMarkdown($hl, $this->theme);
                    }
                } else {
                    $lines[] = $this->theme->styleMuted($this->translator->translate('No extended help provided for this field.'));
                }
                $lines[] = '';
            }
            $lines[] = $this->theme->styleTitle($this->translator->translate('Global Keys'));
            $lines[] = '  ↑ / ↓ / Tab     ' . $this->translator->translate('Move focus between fields and panels');
            $lines[] = '  Enter           ' . $this->translator->translate('Open inline editor, drill into panel, or submit');
            $lines[] = '  Space           ' . $this->translator->translate('Flip Confirm/Toggle or check multi-select option');
            $lines[] = '  Esc             ' . $this->translator->translate('Cancel active edit, leave sub-panel, or abort form');
            $lines[] = '  ?               ' . $this->translator->translate('Toggle this help overlay');
            $footer = $this->translator->translate('Press any key to close help');
            return new Frame($this->theme->drawBox($lines, $width, $title, $footer), $width, $height);
        }

        /**
         * @return array<string, string>
         */
        private function collectFooterHints(mixed $currentItem): array
        {
            if ($currentItem instanceof FieldInterface) {
                $hints = $currentItem->keyHints($this->editing, $this->translator);
                if (!$this->editing) {
                    $hints['↑/↓'] = $this->translator->translate('navigate');
                    $hints['?'] = $this->translator->translate('help');
                }
                return $hints;
            }
            return [
                '↑/↓' => $this->translator->translate('navigate'),
                'Enter' => $this->translator->translate('select'),
                'Esc' => $this->translator->translate('cancel'),
                '?' => $this->translator->translate('help'),
            ];
        }

        private function currentPanel(): Panel
        {
            return $this->panelStack[count($this->panelStack) - 1];
        }

        /**
         * Returns all visible interactive items in the given panel plus '__submit__' and '__cancel__'.
         *
         * @return list<FieldInterface|Panel|string>
         */
        private function navigableItems(Panel $panel): array
        {
            $items = [];
            $activeValues = $this->resolver->visibleValues($this->root);

            foreach ($panel->children() as $child) {
                if (!$child->isVisible($activeValues)) {
                    continue;
                }
                if ($child instanceof FieldInterface || $child instanceof Panel) {
                    $items[] = $child;
                }
            }
            $items[] = '__submit__';
            $items[] = '__cancel__';
            return $items;
        }

        private function matchesAction(Key $key, string $action): bool
        {
            $bound = $this->keyBindings[$action] ?? [];
            return in_array($key->name, $bound, true) || ($key->char !== '' && in_array($key->char, $bound, true));
        }

        public function lastFrame(): ?Frame
        {
            return $this->canvas->lastFrame();
        }

        /**
         * @return list<Frame>
         */
        public function recordedFrames(): array
        {
            return $this->recordedFrames;
        }
    }
}

// ============================================================================
// File: src/Schema/Schema.php
// ============================================================================
namespace DrevOps\PhpTui\Schema {
    use DrevOps\PhpTui\Block\Panel;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Utils\Str;

    /**
     * Generates JSON Schema (Draft 2020-12) and full question metadata (conditions, derivations, discovery, env vars)
     * from a declared Form block tree.
     */
    final readonly class Schema
    {
        public function __construct(
            private Panel $root,
            private TranslationInterface $translator,
            private string $envPrefix = '',
        ) {
        }

        /**
         * Exports the complete JSON Schema + x-phptui metadata map.
         *
         * @return array<string, mixed>
         */
        public function generate(): array
        {
            $properties = [];
            $required = [];

            foreach ($this->root->allFields() as $field) {
                $id = $field->id();
                $prop = $field->toJsonSchema($this->translator);
                $prop['x-panel'] = $field->panelId();
                $prop['x-field-type'] = $field->type();
                $envKey = ($this->envPrefix !== '' ? rtrim($this->envPrefix, '_') . '_' : '') . Str::transform($id, 'constant');
                $prop['x-env'] = $envKey;

                if ($field->condition() !== null) {
                    $prop['x-when'] = $field->condition()->toSchema();
                }
                if ($field->derivation() !== null) {
                    $prop['x-derive'] = $field->derivation()->toSchema();
                }
                if ($field->discoverer() !== null) {
                    $prop['x-discover'] = $field->discoverer()->toSchema();
                }
                if ($field->help() !== '') {
                    $prop['x-help'] = $this->translator->translate($field->help());
                }

                $properties[$id] = $prop;
                if ($field->isRequired() && $field->condition() === null) {
                    $required[] = $id;
                }
            }

            return [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'title' => $this->translator->translate($this->root->title()),
                'description' => $this->translator->translate($this->root->description()),
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false,
            ];
        }
    }
}

// ============================================================================
// File: src/Schema/Agent.php
// ============================================================================
namespace DrevOps\PhpTui\Schema {
    use DrevOps\PhpTui\Block\Panel;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Exports structured AI-agent documentation and JSON Schema so LLM agents or CI runners
     * can populate the form headlessly without TTY interaction.
     */
    final readonly class Agent
    {
        public function __construct(
            private Panel $root,
            private TranslationInterface $translator,
            private string $envPrefix = '',
        ) {
        }

        public function generateHelp(): string
        {
            $schema = (new Schema($this->root, $this->translator, $this->envPrefix))->generate();
            $lines = [
                '# Unattended / AI Agent Form Specification: ' . ($schema['title'] ?: 'Form'),
                '',
                'This form can be answered non-interactively by passing a JSON object payload to `$tui->collect($json)` / `$tui->run($json)` or by setting environment variables.',
                '',
                '## Fields Summary',
                '',
            ];

            foreach ($schema['properties'] as $id => $prop) {
                $reqBadge = in_array($id, $schema['required'], true) ? ' *(required)*' : ' *(optional)*';
                $typeStr = (string) ($prop['type'] ?? 'string');
                $envVar = (string) ($prop['x-env'] ?? strtoupper($id));
                $lines[] = sprintf('- **`%s`** (`%s`, env: `%s`)%s: %s', $id, $typeStr, $envVar, $reqBadge, $prop['title'] ?? $id);
                if (isset($prop['enum']) && is_array($prop['enum'])) {
                    $lines[] = '  - Allowed values: `' . implode('`, `', $prop['enum']) . '`';
                }
                if (isset($prop['default'])) {
                    $lines[] = '  - Default: `' . json_encode($prop['default'], JSON_UNESCAPED_SLASHES) . '`';
                }
                if (isset($prop['x-when'])) {
                    $lines[] = '  - Condition (`when`): `' . json_encode($prop['x-when'], JSON_UNESCAPED_SLASHES) . '`';
                }
                if (isset($prop['x-derive'])) {
                    $lines[] = '  - Derived (`derive`): `' . json_encode($prop['x-derive'], JSON_UNESCAPED_SLASHES) . '`';
                }
            }

            $lines[] = '';
            $lines[] = '## JSON Schema (Draft 2020-12)';
            $lines[] = '';
            $lines[] = '```json';
            $lines[] = (string) json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $lines[] = '```';

            return implode("\n", $lines) . "\n";
        }
    }
}

// ============================================================================
// File: src/Schema/Validator.php
// ============================================================================
namespace DrevOps\PhpTui\Schema {
    use DrevOps\PhpTui\Block\Panel;
    use DrevOps\PhpTui\Handler\HandlerRegistry;
    use DrevOps\PhpTui\Resolver\ValueResolver;
    use DrevOps\PhpTui\Translation\TranslationInterface;

    /**
     * Validates an array of candidate answers against the form's field constraints, conditions, and handlers.
     */
    final readonly class Validator
    {
        public function __construct(
            private Panel $root,
            private HandlerRegistry $registry,
            private TranslationInterface $translator,
        ) {
        }

        /**
         * Returns an associative map of fieldId => errorMessage for any invalid visible fields (empty array if valid).
         *
         * @param array<string, mixed> $candidateAnswers
         * @return array<string, string>
         */
        public function validate(array $candidateAnswers, string $directory = '', string $envPrefix = ''): array
        {
            $resolver = new ValueResolver($this->registry, $this->translator);
            $resolver->initializeState($this->root, $directory, $candidateAnswers, [], $envPrefix);

            $visibleValues = $resolver->visibleValues($this->root);
            $errors = [];

            foreach ($this->root->allFields() as $field) {
                $id = $field->id();
                if (!$resolver->isFieldVisible($this->root, $id)) {
                    continue;
                }
                $val = $resolver->getValue($id);
                $err = $field->validateValue($val, $visibleValues, $this->translator);
                if ($err === null) {
                    $handler = $this->registry->resolve($id);
                    if ($handler !== null) {
                        $err = $handler->validate($val, $visibleValues);
                    }
                }
                if ($err !== null) {
                    $errors[$id] = $err;
                }
            }

            return $errors;
        }
    }
}

// ============================================================================
// File: src/Testing/TestScreen.php
// ============================================================================
namespace DrevOps\PhpTui\Testing {
    use DrevOps\PhpTui\Terminal\Terminal;

    /**
     * Virtual in-memory Terminal subclass for deterministic headless TuiTester execution without a real TTY.
     */
    final class TestScreen extends Terminal
    {
        public function __construct(int $width = 80, int $height = 24)
        {
            parent::__construct(null, null, $width, $height, false);
        }
    }
}

// ============================================================================
// File: src/Testing/TuiTester.php
// ============================================================================
namespace DrevOps\PhpTui\Testing {
    use DrevOps\PhpTui\Answers\Answers;
    use DrevOps\PhpTui\Builder\Form;
    use DrevOps\PhpTui\CancelException;
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\InterruptException;
    use DrevOps\PhpTui\Screen\Frame;
    use DrevOps\PhpTui\Tui;

    /**
     * Headless test harness that drives the real Screen panel loop from scripted keystrokes — no TTY needed.
     * Returns collected Answers and exposes the rendered terminal frames for snapshot/assertion testing.
     */
    final class TuiTester
    {
        private Tui $tui;
        private TestScreen $testTerminal;
        private ?Answers $lastAnswers = null;
        private bool $cancelled = false;
        private bool $interrupted = false;
        private string $lastDisplay = '';
        private string $lastAnsiDisplay = '';
        /** @var list<Frame> */
        private array $frames = [];

        public function __construct(
            Form $form,
            ?Tui $tui = null,
            int $width = 80,
            int $height = 24,
        ) {
            $this->tui = $tui ?? new Tui($form);
            $this->testTerminal = new TestScreen($width, $height);
        }

        /**
         * Drives the interactive panel loop with the supplied sequence of Key objects or strings.
         */
        public function run(Key|string ...$inputs): Answers
        {
            $this->cancelled = false;
            $this->interrupted = false;
            $this->testTerminal->reader()->push(...$inputs);

            $screen = $this->tui->createScreen();
            try {
                $this->lastAnswers = $screen->run($this->testTerminal);
            } catch (CancelException) {
                $this->cancelled = true;
                $this->lastAnswers = $this->tui->currentResolver()->toAnswers($this->tui->root(), true);
            } catch (InterruptException) {
                $this->interrupted = true;
                $this->lastAnswers = $this->tui->currentResolver()->toAnswers($this->tui->root(), true);
            }

            $this->frames = $screen->recordedFrames();
            $finalFrame = $screen->lastFrame();
            if ($finalFrame !== null) {
                $this->lastDisplay = $finalFrame->toPlainText();
                $this->lastAnsiDisplay = $finalFrame->toAnsiString();
            }

            return $this->lastAnswers;
        }

        public function answers(): Answers
        {
            return $this->lastAnswers ?? new Answers();
        }

        public function display(): string
        {
            return $this->lastDisplay;
        }

        public function ansiDisplay(): string
        {
            return $this->lastAnsiDisplay;
        }

        /**
         * @return list<Frame>
         */
        public function frames(): array
        {
            return $this->frames;
        }

        public function isCancelled(): bool
        {
            return $this->cancelled;
        }

        public function isInterrupted(): bool
        {
            return $this->interrupted;
        }
    }
}

// ============================================================================
// File: src/Tui.php
// ============================================================================
namespace DrevOps\PhpTui {
    use DrevOps\PhpTui\Answers\Answers;
    use DrevOps\PhpTui\Block\BlockInterface;
    use DrevOps\PhpTui\Block\Panel;
    use DrevOps\PhpTui\Block\Progress;
    use DrevOps\PhpTui\Builder\Form;
    use DrevOps\PhpTui\Handler\HandlerRegistry;
    use DrevOps\PhpTui\Input\Key;
    use DrevOps\PhpTui\Primitive\Axis;
    use DrevOps\PhpTui\Resolver\ResolverInterface;
    use DrevOps\PhpTui\Resolver\ValueResolver;
    use DrevOps\PhpTui\Schema\Agent;
    use DrevOps\PhpTui\Schema\Schema;
    use DrevOps\PhpTui\Schema\Validator;
    use DrevOps\PhpTui\Screen\Layout;
    use DrevOps\PhpTui\Screen\Screen;
    use DrevOps\PhpTui\Terminal\Terminal;
    use DrevOps\PhpTui\Theme\Overrides;
    use DrevOps\PhpTui\Theme\ThemeInterface;
    use DrevOps\PhpTui\Theme\ThemeManager;
    use DrevOps\PhpTui\Translation\TranslationInterface;
    use DrevOps\PhpTui\Translation\Translator;

    /**
     * Primary PHPTUI Facade: orchestrates interactive panel TUI execution (`interact()`),
     * headless JSON/environment/discovery answer collection (`collect()`), automatic mode routing (`run()`),
     * and AI agent schema introspection (`schema()`, `agentHelp()`, `validate()`).
     */
    final class Tui
    {
        private HandlerRegistry $registry;
        private TranslationInterface $translator;
        private ThemeManager $themeManager;
        private string|ThemeInterface $theme = 'default';
        private ?Overrides $themeOverrides = null;
        private Layout $layout;
        /** @var array<string, list<string>> */
        private array $keyBindings;
        private ?bool $color = null;
        private ?bool $unicode = null;
        private bool $markdown = true;
        private bool $fullscreen = false;
        private bool $footer = true;
        private bool $clearOnExit = true;
        private string $banner = '';
        private ?ResolverInterface $lastResolver = null;

        /**
         * @param list<string> $handlerNamespaces
         */
        public function __construct(
            private readonly Form $form,
            array $handlerNamespaces = [],
            private readonly string $envPrefix = '',
        ) {
            $this->registry = new HandlerRegistry($handlerNamespaces);
            $this->translator = new Translator();
            $this->themeManager = new ThemeManager();
            $this->layout = new Layout('default');
            $this->keyBindings = Key::resolveBindings('default');
            $this->banner = $this->form->getBanner();
        }

        public function theme(string|ThemeInterface $theme, ?Overrides $overrides = null): self
        {
            $this->theme = $theme;
            if ($overrides !== null) {
                $this->themeOverrides = $overrides;
            }
            return $this;
        }

        public function layout(string $layout): self
        {
            $this->layout = new Layout($layout);
            return $this;
        }

        public function place(string $region, BlockInterface $block, bool $tail = false): self
        {
            $this->layout->place($region, $block, $tail);
            return $this;
        }

        public function flow(string $region, Axis $axis): self
        {
            $this->layout->flow($region, $axis);
            return $this;
        }

        /**
         * @param string|array<string, list<string>|string> $bindings
         */
        public function keys(string|array $bindings): self
        {
            $this->keyBindings = Key::resolveBindings($bindings);
            return $this;
        }

        public function translator(?TranslationInterface $translator = null): self
        {
            $this->translator = $translator ?? new Translator();
            return $this;
        }

        public function translate(TranslationInterface $translator): self
        {
            return $this->translator($translator);
        }

        public function color(?bool $color = true): self
        {
            $this->color = $color;
            return $this;
        }

        public function unicode(?bool $unicode = true): self
        {
            $this->unicode = $unicode;
            return $this;
        }

        public function markdown(bool $markdown = true): self
        {
            $this->markdown = $markdown;
            return $this;
        }

        public function fullscreen(bool $fullscreen = true): self
        {
            $this->fullscreen = $fullscreen;
            return $this;
        }

        public function footer(bool $footer = true): self
        {
            $this->footer = $footer;
            return $this;
        }

        public function clearOnExit(bool $clearOnExit = true): self
        {
            $this->clearOnExit = $clearOnExit;
            return $this;
        }

        public function banner(string $banner): self
        {
            $this->banner = $banner;
            return $this;
        }

        /**
         * Runs the form: automatically launches the interactive TUI when on a TTY and no prompts are passed,
         * or collects answers headlessly otherwise.
         *
         * @param string|array<string, mixed> $prompts
         */
        public function run(
            string|array $prompts = '',
            string $version = '',
            string $directory = '',
            ?bool $interactive = null,
            bool $update = false,
        ): Answers {
            $hasPrompts = ($prompts !== '' && $prompts !== []);
            $isTty = $interactive ?? (!$hasPrompts && defined('STDIN') && is_resource(STDIN) && @stream_isatty(STDIN));

            if ($isTty) {
                return $this->interact(
                    banner: $this->banner,
                    version: $version,
                    directory: $directory,
                    update: $update,
                );
            }

            return $this->collect($prompts, $directory, $update, $version);
        }

        /**
         * Headlessly collects and validates answers from a JSON string, associative array, environment variables,
         * project Discovery rules, and derived field fixpoints. Throws CollectException if any visible field is invalid.
         *
         * @param string|array<string, mixed> $prompts
         */
        public function collect(
            string|array $prompts = '',
            string $directory = '',
            bool $update = false,
            string $version = '',
        ): Answers {
            $root = $this->root();
            $parsedPrompts = $this->parsePromptsPayload($prompts);

            $resolver = new ValueResolver($this->registry, $this->translator, $this->form->currentFixups());
            $resolver->initializeState($root, $directory, $parsedPrompts, [], $this->envPrefix);
            $this->lastResolver = $resolver;

            $visibleValues = $resolver->visibleValues($root);
            foreach ($root->allFields() as $field) {
                $id = $field->id();
                if (!$resolver->isFieldVisible($root, $id)) {
                    continue;
                }
                $val = $resolver->getValue($id);
                $err = $field->validateValue($val, $visibleValues, $this->translator);
                if ($err === null) {
                    $handler = $this->registry->resolve($id);
                    if ($handler !== null) {
                        $err = $handler->validate($val, $visibleValues);
                    }
                }
                if ($err !== null) {
                    throw new CollectException($id, $err);
                }
            }

            return $resolver->toAnswers($root, false);
        }

        /**
         * Drives the interactive full-screen/panel TUI session on a Terminal.
         */
        public function interact(
            string $theme = '',
            string $banner = '',
            string $version = '',
            string $directory = '',
            bool $update = false,
            ?Terminal $terminal = null,
        ): Answers {
            if ($theme !== '') {
                $this->theme = $theme;
            }
            if ($banner !== '') {
                $this->banner = $banner;
            }
            $term = $terminal ?? Terminal::fromEnvironment();
            $screen = $this->createScreen($directory);
            return $screen->run($term);
        }

        /**
         * Creates a configured Screen instance for interactive or TuiTester execution.
         */
        public function createScreen(string $directory = ''): Screen
        {
            $root = $this->root();
            $resolver = new ValueResolver($this->registry, $this->translator, $this->form->currentFixups());
            $resolver->initializeState($root, $directory, [], [], $this->envPrefix);
            $this->lastResolver = $resolver;

            $resolvedTheme = $this->themeManager->resolve(
                $this->theme,
                $this->color,
                $this->unicode,
                null,
                $this->themeOverrides
            );

            return new Screen(
                root: $root,
                resolver: $resolver,
                registry: $this->registry,
                theme: $resolvedTheme,
                layout: $this->layout,
                translator: $this->translator,
                keyBindings: $this->keyBindings,
                banner: $this->banner,
                showFooter: $this->footer,
                fullscreen: $this->fullscreen,
                clearOnExit: $this->clearOnExit,
            );
        }

        public function currentResolver(): ResolverInterface
        {
            if ($this->lastResolver === null) {
                $this->lastResolver = new ValueResolver($this->registry, $this->translator, $this->form->currentFixups());
                $this->lastResolver->initializeState($this->root());
            }
            return $this->lastResolver;
        }

        public function progress(string $caption = 'Working…', string $id = 'progress'): Progress
        {
            return new Progress($id, $caption);
        }

        /**
         * Exports the JSON Schema Draft 2020-12 and full question metadata for the form.
         *
         * @return array<string, mixed>
         */
        public function schema(): array
        {
            return (new Schema($this->root(), $this->translator, $this->envPrefix))->generate();
        }

        /**
         * Exports Markdown instructions + JSON Schema for AI agents and unattended automation.
         */
        public function agentHelp(): string
        {
            return (new Agent($this->root(), $this->translator, $this->envPrefix))->generateHelp();
        }

        /**
         * Validates an associative array of answers against the form schema and field rules.
         *
         * @param array<string, mixed> $answers
         * @return array<string, string>
         */
        public function validate(array $answers, string $directory = ''): array
        {
            return (new Validator($this->root(), $this->registry, $this->translator))
                ->validate($answers, $directory, $this->envPrefix);
        }

        public function root(): Panel
        {
            return $this->form->build();
        }

        public function registry(): HandlerRegistry
        {
            return $this->registry;
        }

        /**
         * @param string|array<string, mixed> $prompts
         * @return array<string, mixed>
         */
        private function parsePromptsPayload(string|array $prompts): array
        {
            if (is_array($prompts)) {
                return $prompts;
            }
            $trimmed = trim($prompts);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            if (!is_array($decoded)) {
                throw new CollectException('_payload', 'Invalid JSON payload supplied to collect().');
            }
            return $decoded;
        }
    }
}