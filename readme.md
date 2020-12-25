# Filer Filesystem

A Seaweedfs file adapter for Flysystem/Laravel filesystem 



# Contribution

- Chạy docker
- Chạy composer
- Chạy phpunit

```
PHPUnit 8.5.13 by Sebastian Bergmann and contributors.

Runtime:       PHP 7.4.13
Configuration: /ThikDev/Documents/my_packages/filer_filesystem/phpunit.xml

...........E....E..                                               19 / 19 (100%)

Time: 878 ms, Memory: 8.00 MB

There were 2 errors:

1) FilerAdapterTest::testCreateDir
count(): Parameter must be an array or an object that implements Countable

/ThikDev/Documents/my_packages/filer_filesystem/tests/FilerAdapterTest.php:50

2) FilerAdapterTest::testListContents
Trying to access array offset on value of type int

/ThikDev/Documents/my_packages/filer_filesystem/src/FilerAdapter.php:341
/ThikDev/Documents/my_packages/filer_filesystem/src/FilerAdapter.php:342
/ThikDev/Documents/my_packages/filer_filesystem/src/FilerAdapter.php:207
/ThikDev/Documents/my_packages/filer_filesystem/tests/FilerAdapterTest.php:139

ERRORS!
Tests: 19, Assertions: 45, Errors: 2.

```

