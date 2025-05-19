# Changelog

모든 변경 사항은 [GitHub Releases](https://github.com/jonathanbak/mysqlilib/releases)에서 확인할 수 있습니다.

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
