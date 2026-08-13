<?php

declare(strict_types=1);

namespace Webidea24\MagentoComposerPatches\Patch;

use RuntimeException;

final class PatchFileSplitter
{
    /**
     * @return list<array{contents: string, target: string, vendorPackage: string|null}>
     */
    public static function splitByFile(string $patchPath): array
    {
        $contents = file_get_contents($patchPath);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Cannot read patch file: %s', $patchPath));
        }

        $hasGitHeaders = preg_match('/^diff --git /m', $contents) === 1;
        $sections = preg_split(
            $hasGitHeaders
                ? '/(?=^diff --git )/m'
                : '/(?=^--- [^\r\n]+\r?\n\+\+\+ [^\r\n]+)/m',
            $contents,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );
        if ($sections === false || $sections === []) {
            throw new RuntimeException(sprintf('Patch file does not contain a usable diff: %s', $patchPath));
        }

        $fragments = [];
        foreach ($sections as $index => $section) {
            // E-mail style patches can contain a cover letter before the first
            // diff. It is metadata, not an applicable fragment.
            if (($hasGitHeaders && !str_starts_with($section, 'diff --git '))
                || (!$hasGitHeaders && !str_starts_with($section, '--- '))) {
                continue;
            }

            // Some unified-diff generators retain the a/ or b/ prefix on
            // /dev/null. Normalize it so patch and git apply recognize file
            // additions and deletions.
            if (!$hasGitHeaders) {
                $section = preg_replace('/^(---|\+\+\+) [ab]\/dev\/null(?=\t|$)/m', '$1 /dev/null', $section) ?? $section;
            }

            $target = $hasGitHeaders
                ? self::getGitTarget($section, $index)
                : self::getUnifiedTarget($section, $index);
            $fragments[] = [
                'contents' => $section,
                'target' => $target,
                'vendorPackage' => self::getVendorPackageName($target),
            ];
        }

        if ($fragments === []) {
            throw new RuntimeException(sprintf('Patch file does not contain a usable diff: %s', $patchPath));
        }

        return $fragments;
    }

    public static function replaceTargetPath(string $contents, string $targetPath, string $sourcePath): string
    {
        $targetPath = preg_quote($targetPath, '#');

        $contents = preg_replace(
            '#^(diff --git a/)' . $targetPath . '( b/)' . $targetPath . '$#m',
            '$1' . $sourcePath . '$2' . $sourcePath,
            $contents,
        ) ?? $contents;

        return preg_replace(
            '#^((?:---|\+\+\+) (?:[ab]/)?)' . $targetPath . '(?=\t|$)#m',
            '$1' . $sourcePath,
            $contents,
        ) ?? $contents;
    }

    private static function getGitTarget(string $fragment, int $index): string
    {
        if (preg_match('#^diff --git a/(.+) b/(.+)$#m', $fragment, $matches) === 1) {
            return $matches[2];
        }

        return sprintf('fragment %d', $index + 1);
    }

    private static function getUnifiedTarget(string $fragment, int $index): string
    {
        if (preg_match('/^--- ([^\t\r\n]+)(?:\t[^\r\n]*)?\r?\n\+\+\+ ([^\t\r\n]+)(?:\t[^\r\n]*)?$/m', $fragment, $matches) !== 1) {
            return sprintf('fragment %d', $index + 1);
        }

        $from = $matches[1];
        $to = $matches[2];
        $target = self::isNullPath($to) ? $from : $to;

        return preg_replace('#^[ab]/#', '', $target) ?? $target;
    }

    private static function isNullPath(string $path): bool
    {
        return in_array($path, ['/dev/null', 'a/dev/null', 'b/dev/null'], true);
    }

    private static function getVendorPackageName(string $target): ?string
    {
        if (preg_match('#^vendor/([^/]+)/([^/]+)/#', $target, $matches) !== 1) {
            return null;
        }

        if (in_array($matches[1], ['bin', 'composer'], true)) {
            return null;
        }

        return $matches[1] . '/' . $matches[2];
    }
}
