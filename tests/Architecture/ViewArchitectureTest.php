<?php

use Symfony\Component\Finder\Finder;

it('namespaces package-owned Blade identifiers away from host pages', function () {
    $views = dirname(__DIR__, 2).'/resources/views';
    $attributeViolations = [];
    $literalIdViolations = [];
    $alpineIdViolations = [];

    foreach ((new Finder)->files()->in($views)->name('*.blade.php') as $file) {
        $relativePath = $file->getRelativePathname();
        $contents = file_get_contents($file->getPathname());

        preg_match_all('/(?:^|\s):?data-(?<name>[a-z0-9_-]+)/m', $contents, $attributes);

        foreach (array_unique($attributes['name']) as $name) {
            if (! str_starts_with($name, 'ndb-')) {
                $attributeViolations[] = $relativePath.': data-'.$name;
            }
        }

        preg_match_all('/(?:^|\s)(?:::|:)?id="(?<id>[^"]+)"/m', $contents, $ids);

        foreach (array_unique($ids['id']) as $id) {
            if (! str_contains($id, '{{') && ! str_contains($id, '$id(') && ! str_starts_with($id, 'newdebugbar')) {
                $literalIdViolations[] = $relativePath.': '.$id;
            }
        }

        preg_match_all('/x-id="\[(?<ids>[^]]+)]"/', $contents, $alpineGroups);

        foreach ($alpineGroups['ids'] as $group) {
            preg_match_all("/'(?<id>[^']+)'/", $group, $alpineIds);

            foreach ($alpineIds['id'] as $id) {
                if (! str_starts_with($id, 'newdebugbar')) {
                    $alpineIdViolations[] = $relativePath.': '.$id;
                }
            }
        }
    }

    expect($attributeViolations)->toBe([])
        ->and($literalIdViolations)->toBe([])
        ->and($alpineIdViolations)->toBe([]);
});
