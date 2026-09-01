<?php

declare(strict_types=1);

namespace RunOrg\Chart;

final class SvgPostprocessor
{
    public function setA4Landscape(string $path): void
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            return;
        }

        $updated = preg_replace(
            '/width="[0-9.]+" height="[0-9.]+"/',
            'width="297mm" height="210mm"',
            $contents,
            1
        );
        if (is_string($updated) && $updated !== $contents) {
            file_put_contents($path, $updated);
        }
    }
}
