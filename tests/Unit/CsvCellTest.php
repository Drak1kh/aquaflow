<?php

use App\CsvCell;

test('spreadsheet formula prefixes are escaped in report cells', function () {
    expect(CsvCell::safe('=HYPERLINK("x")'))->toBe("'=HYPERLINK(\"x\")")
        ->and(CsvCell::safe(' Ordinary name'))->toBe(' Ordinary name');
});
