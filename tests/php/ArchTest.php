<?php

declare(strict_types=1);

// Architecture rules: what the source is allowed to depend on, and conventions every
// file keeps. They run with the rest of the suite, so CI gates on them too.
//
// Pest's `php` and `security` presets are not used: they forbid `exit`, which every
// file's ABSPATH guard uses, and `md5`, which the transient keys use.

const DAF_SRC = __DIR__ . '/../../src';

/**
 * Every PHP file under src/, relative to src/ with forward slashes.
 *
 * @return array<string, string> Relative path => contents.
 */
function dafSourceFiles(): array
{
    $files    = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(DAF_SRC, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && 'php' === $file->getExtension()) {
            $relative           = str_replace('\\', '/', substr($file->getPathname(), strlen(DAF_SRC) + 1));
            $files[ $relative ] = (string) file_get_contents($file->getPathname());
        }
    }
    ksort($files);

    return $files;
}

arch('every source file declares strict types')
    ->expect('DiviActiveForm')
    ->toUseStrictTypes();

arch('every class is final')
    ->expect('DiviActiveForm')
    ->classes()
    ->toBeFinal();

arch('no debugging calls are left in')
    ->expect([ 'dd', 'dump', 'var_dump', 'var_export', 'print_r', 'error_log', 'debug_backtrace', 'ray' ])
    ->not->toBeUsed();

arch('Divi\'s own classes are used only by the Divi wiring')
    ->expect('ET\Builder')
    ->toOnlyBeUsedIn([ 'DiviActiveForm\Divi', 'DiviActiveForm\Plugin', 'DiviActiveForm\Assets' ]);

arch('the form logic knows nothing of rendering, REST, Divi or the WordPress wiring')
    ->expect('DiviActiveForm\Form')
    ->not->toUse([ 'DiviActiveForm\Render', 'DiviActiveForm\Rest', 'DiviActiveForm\Divi', 'DiviActiveForm\Plugin', 'DiviActiveForm\Assets', 'ET\Builder' ]);

arch('rendering knows nothing of REST or Divi')
    ->expect('DiviActiveForm\Render')
    ->not->toUse([ 'DiviActiveForm\Rest', 'DiviActiveForm\Divi', 'ET\Builder' ]);

arch('Support depends on nothing else in the plugin')
    ->expect('DiviActiveForm\Support')
    ->not->toUse([ 'DiviActiveForm\Form', 'DiviActiveForm\Render', 'DiviActiveForm\Rest', 'DiviActiveForm\Divi', 'DiviActiveForm\Plugin', 'DiviActiveForm\Assets' ]);

arch('field types are a string-backed enum')
    ->expect('DiviActiveForm\Form\FieldType')
    ->toBeStringBackedEnum();

arch('option sources share one interface')
    ->expect('DiviActiveForm\Form\Options\OptionProvider')
    ->toBeInterface();

it('stops every source file when it is loaded outside WordPress', function (): void {
    $unguarded = [];
    foreach (dafSourceFiles() as $path => $source) {
        if (! preg_match("/if \\(! defined\\('ABSPATH'\\)\\) \\{\\s*exit;\\s*\\}/", $source)) {
            $unguarded[] = $path;
        }
    }

    expect($unguarded)->toBe([]);
});

it('reads request superglobals only where requests are handled', function (): void {
    $allowed = [ 'Rest/FormController.php', 'Divi/FormModule.php' ];
    $readers = [];
    foreach (dafSourceFiles() as $path => $source) {
        if (preg_match('/\$_(GET|POST|REQUEST|SERVER|COOKIE|FILES)\b/', $source)) {
            $readers[] = $path;
        }
    }

    expect(array_values(array_diff($readers, $allowed)))->toBe([]);
});
