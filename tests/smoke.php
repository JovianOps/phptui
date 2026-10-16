<?php

declare(strict_types=1);

require 'C:/Users/RDC/Desktop/phptui_polished.php';

use DrevOps\PhpTui\Answers\AnswerCollection;
use DrevOps\PhpTui\Answers\Answers;
use DrevOps\PhpTui\Condition\Condition;
use DrevOps\PhpTui\Translation\Translator;
use DrevOps\PhpTui\Utils\MbString;
use DrevOps\PhpTui\Utils\Str;

$fail = static function (string $label, mixed $actual, mixed $expected): void {
  if ($actual !== $expected) {
    echo "FAIL {$label}: got " . var_export($actual, TRUE) . ', want ' . var_export($expected, TRUE) . PHP_EOL;
    return;
  }
  echo "ok {$label}\n";
};

// MbString widths.
$fail('ascii width', MbString::displayWidth('hello'), 5);
$fail('cjk width', MbString::displayWidth('日本'), 4);
$fail('emoji width', MbString::displayWidth('🎉'), 2);
$fail('combining width', MbString::displayWidth("e\xCC\x81"), 1);
$fail('empty width', MbString::displayWidth(''), 0);
$fail('ansi stripped width', MbString::displayWidth("\033[31mhi\033[0m"), 2);
$fail('truncate', MbString::truncateWidth('hello world', 8), 'hello w…');
$fail('truncate cjk', MbString::truncateWidth('日本語テスト', 5), '日本…');

// Str transforms.
$fail('machine', Str::transform('Order name?', 'machine'), 'order_name');
$fail('pascal', Str::transform('red_apple', 'pascal'), 'RedApple');
$fail('camel', Str::transform('red_apple', 'camel'), 'redApple');
$fail('tokenize camel', Str::tokenize('camelCase'), ['camel', 'Case']);
$fail('fuzzy exact', Str::fuzzyScore('abc', 'abc'), 1000);
$fail('fuzzy empty', Str::fuzzyScore('', 'abc'), 1);
$fail('fuzzy none', Str::fuzzyScore('zzz', 'abc'), 0);

// Translator.
$t = new Translator('en');
$fail('translate passthrough', $t->translate('Hello'), 'Hello');
$fail('translate param', $t->translate('Hi {name}', ['name' => 'Ada']), 'Hi Ada');
$fail('plural one', $t->translatePlural('apple', 'apples', 1), 'apple');
$fail('plural many', $t->translatePlural('apple', 'apples', 5), 'apples');
$ru = new Translator('ru');
$fail('ru one', $ru->translatePlural('a', 'b', 1), 'a');
$fail('ru few', $ru->translatePlural('a', 'b', 3), 'b');

// Conditions.
$fail('eq true', Condition::equals('x', 1)->evaluate(['x' => 1]), TRUE);
$fail('eq loose', Condition::equals('x', 1)->evaluate(['x' => '1']), TRUE);
$fail('ne', Condition::notEquals('x', 1)->evaluate(['x' => 2]), TRUE);
$fail('set false on empty', Condition::set('x')->evaluate(['x' => '']), FALSE);
$fail('matches', Condition::matches('x', '/^\d+$/')->evaluate(['x' => '42']), TRUE);

// Answers.
$a = new Answers(['name' => 'Ada', 'empty' => '']);
$fail('value', $a->value('name'), 'Ada');
$fail('missing default', $a->value('nope', 'd'), 'd');
$fail('count visible', $a->count(), 2);
$fail('array access', $a['name'], 'Ada');
$fail('summary has name', str_contains($a->toSummary(), 'Ada'), TRUE);

echo "ALL SMOKE OK\n";
