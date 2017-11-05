# JSON5 for PHP - JSON for Humans

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]


This library is a PHP fork of the [JSON5 parser][link-json5].

JSON5 allows comments, trailing commas, single-quoted strings, and more - [see their documentation for details][link-json5-site].


## Install

Via Composer

``` bash
$ composer require colinodell/json5
```

## Usage

``` php
$json = file_get_contents('foo.json');
$arr = Json5Decoder::decode($json);
```

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email colinodell@gmail.com instead of using the issue tracker.

## Credits

- [Colin O'Dell][link-author]
- [Aseem Kishore][link-upstream-author], [the JSON5 project][link-json5], and [their contributors][link-upstream-contributors]
- [All other contributors to this project][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/colinodell/json5.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/colinodell/json5/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/colinodell/json5.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/colinodell/json5.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/colinodell/json5.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/colinodell/json5
[link-travis]: https://travis-ci.org/colinodell/json5
[link-scrutinizer]: https://scrutinizer-ci.com/g/colinodell/json5/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/colinodell/json5
[link-downloads]: https://packagist.org/packages/colinodell/json5
[link-author]: https://github.com/colinodell
[link-json5]: https://github.com/json5/json5
[link-upstream-author]: https://github.com/aseemk
[link-upstream-contributors]: https://github.com/json5/json5#credits
[link-json5-site]: http://json5.org
[link-contributors]: ../../contributors
