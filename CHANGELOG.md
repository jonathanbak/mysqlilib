# Changelog

모든 변경 사항은 [GitHub Releases](https://github.com/jonathanbak/mysqlilib/releases)에서 확인할 수 있습니다.

## [1.4.3](https://github.com/jonathanbak/mysqlilib/compare/v1.4.2...v1.4.3) (2025-09-08)


### Bug Fixes

* prevent missing params error in fetchOne after bind_param usage ([64cd231](https://github.com/jonathanbak/mysqlilib/commit/64cd231de4cb085d7aac32ad4cc755c020726b75))

## [1.4.2](https://github.com/jonathanbak/mysqlilib/compare/v1.4.1...v1.4.2) (2025-06-26)


### Bug Fixes

* correct named param regex to exclude time values like 10:00:00 ([2c42176](https://github.com/jonathanbak/mysqlilib/commit/2c421769765e32484d44e2c32e65bb5153b76d53))

## [1.4.1](https://github.com/jonathanbak/mysqlilib/compare/v1.4.0...v1.4.1) (2025-05-20)


### Bug Fixes

* ensure correct bind param handling and reuse in repeated queries ([c865478](https://github.com/jonathanbak/mysqlilib/commit/c865478651065ce74839bc5f9be1bfeefd40596d))

## [1.4.0](https://github.com/jonathanbak/mysqlilib/compare/v1.3.7...v1.4.0) (2025-05-19)


### Features

* 구조 정비 및 테스트 환경 개선 ([77b5ba3](https://github.com/jonathanbak/mysqlilib/commit/77b5ba3125b0fcbcab0669ea6c09fa83ad3a12d2))


### Bug Fixes

* fetch() 반복 호출 시 쿼리 캐시 처리 로직 수정 ([7e81600](https://github.com/jonathanbak/mysqlilib/commit/7e81600eec07d98a549e76f89612e3a0ede3eede))
* replace array destructuring with list() for PHP 7.0 compatibility ([bc5a6ed](https://github.com/jonathanbak/mysqlilib/commit/bc5a6edf4a36ded124f978cf43a627fdd2cbad26))
* update PHP 7.4 to use PHPUnit 9.5 for compatibility ([6f30c28](https://github.com/jonathanbak/mysqlilib/commit/6f30c289ac0c54e8aae8038f97bb3142f1f3eac0))
* wrap mysqli_connect with try-catch to standardize error handling via custom Exception ([ad162b5](https://github.com/jonathanbak/mysqlilib/commit/ad162b5d52a095e7b072c1ac73a9c95851b7a7f3))


### Performance Improvements

* optimize query/fetch by reusing prepared statements ([7762ba2](https://github.com/jonathanbak/mysqlilib/commit/7762ba202aa48187d2ef0da40771a41cd91a69d4))


### Miscellaneous Chores

* prepare release v1.3.8 with CHANGELOG update ([64769cb](https://github.com/jonathanbak/mysqlilib/commit/64769cbb757facdf1e81c1fb88740d354f8f728d))

## [v1.3.7] - 2025-04-11

### Fixed

- 반복문 내 `fetch()` 사용 시 데이터 누락 문제 수정
    - `while($row = $DB->fetch($query))` 형태로 사용할 때 일부 데이터가 누락되는 현상 해결

## [v1.3.6] - 2024-10-20

### Fixed

- PHP 7.4에서 `get_magic_quotes_gpc` 사용 시 발생하는 Deprecated 경고 메시지 제거

## [v1.3.5] - 2024-10-19

### Fixed

- PHP 8에서 `get_magic_quotes_gpc` 함수가 제거됨에 따라 관련 코드 수정
    - PHP 8 환경에서도 라이브러리가 정상 작동하도록 개선

## [v1.3.4] - 2024-03-07

### Fixed

- `fetch()` 함수의 `parseCondition` 조건 로직 수정
    - 특정 조건에서의 데이터 조회 오류 수정
