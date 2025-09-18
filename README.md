# MySQLiLib

[![Build Status](https://github.com/jonathanbak/mysqlilib/actions/workflows/test.yml/badge.svg)](https://github.com/jonathanbak/mysqlilib/actions/workflows/test.yml)
[![codecov](https://codecov.io/gh/jonathanbak/mysqlilib/branch/master/graph/badge.svg)](https://codecov.io/gh/jonathanbak/mysqlilib)
![PHP Version](https://img.shields.io/badge/php-5.6%20~%208.3-blue)

💡 **가볍고 실무 친화적인 PHP MySQL 라이브러리**  
PHP 5.6부터 PHP 8.3까지 폭넓게 호환되며, 단순하고 직관적인 인터페이스로 빠르게 데이터베이스 연동을 구현할 수 있습니다.

---

## ✨ Features

- `mysqli` 기반 경량 ORM 스타일 구현
- `:param`, `?` 스타일의 유연한 바인딩 지원
- Prepared Statement 자동 처리
- Iterator 기반 `fetch()` 지원
- 예외 처리 기반의 안정성 확보
- FakeDb 를 통한 단위 테스트 가능

---

## 🛠️ Installation

```bash
composer require jonathanbak/mysqlilib
```

---

## 📦 Usage

### Connect & Fetch

```php
$DB = new MySQLiLib($host, $user, $password, $dbName);
$query = "SELECT * FROM test";
$row = $DB->fetch($query);
var_dump($row);
```

### 🔍 SELECT with Parameters: `fetch()` vs `fetchOne()`

두 함수 모두 **단일 row를 반환**하지만, 동작 방식에는 차이가 있습니다.

| 함수        | 반환 동작 | 반복 호출 시 동작 | 적합한 상황               |
|-------------|------------|--------------------|----------------------------|
| `fetch()`   | 한 줄 반환 | 다음 줄 순차 반환  | 여러 줄 중에서 반복 처리 시 |
| `fetchOne()`| 한 줄 반환 | 매번 동일한 결과   | 단 한 줄만 가져올 때      |

#### ✅ `fetch()` - 반복 호출로 다음 row 순차 접근

```php
$query = "SELECT * FROM test WHERE id < ?";
$row1 = $DB->fetch($query, [5]);
$row2 = $DB->fetch($query, [5]);

var_dump($row1 === $row2); // false (같은 쿼리 → 다음번 데이터 가져옴)
```

- 내부적으로 `mysqli_result`를 반복해서 읽어오며 **이터레이터처럼 작동**합니다.
- 같은 쿼리/파라미터라도 매 호출마다 다음 행을 반환하며, 더 이상 없으면 `null`을 반환합니다.

#### ✅ `fetchOne()` - 항상 단 한 줄만 반환

```php
$query = "SELECT * FROM test WHERE id < ?";
$row1 = $DB->fetchOne($query, [5]);
$row2 = $DB->fetchOne($query, [5]);

var_dump($row1 === $row2); // true (같은 쿼리 → 같은 결과)
```

- 내부적으로 `query()`를 실행하고 결과를 `fetch_assoc()`으로 즉시 가져온 뒤 반환합니다.
- **매 호출마다 동일한 결과를 반환**하므로, 조건이 정확히 하나의 row를 반환하는 경우 적합합니다.

### Fetch Multiple Rows (Iterator)

```php
$query = "SELECT * FROM test WHERE id = ?";
$rows = [];
while ($row = $DB->fetch($query, [11])) {
    $rows[] = $row;
}
```

### LIKE Query
> ⚠️ LIKE 쿼리에서 `??` 치환 방식은 v1.3.0부터 더 이상 지원되지 않습니다.  
> 바인딩 값에 `%`를 포함하여 `LIKE ?` 형식으로 사용하세요.

### LIKE 검색 예제 (`?` 한 개만 사용)

```php
$query = "SELECT * FROM test WHERE name LIKE ?";
$rows = [];
while ($row = $DB->fetch($query, ['테스트%'])) {
    $rows[] = $row;
}
var_dump($rows);
```

> 문자열 전체를 바인딩할 경우 `'테스트%'`처럼 와일드카드를 포함시켜 전달합니다.

---

### 🏷️ Named Parameter (:param) 예제

```php
$query = "SELECT * FROM test WHERE id > :id AND name = :name";
$params = [
    'id' => 10,
    'name' => '홍길동'
];
$rows = [];
while ($row = $DB->fetch($query, $params)) {
    $rows[] = $row;
}
var_dump($rows);
```

> `:param` 스타일은 내부적으로 `?`로 치환되며, 배열의 키를 기준으로 자동 정렬하여 바인딩됩니다.

---

### INSERT / UPDATE / DELETE

```php
$DB->query("INSERT INTO test SET id = ?, reg_date = ?", [33, date("Y-m-d H:i:s")]);
$DB->query("DELETE FROM test WHERE id = ?", [33]);
```

---

### Exception Handling

```php
try {
    $DB->query("INSERT INTO test SET id = ?", [33]);
} catch (\MySQLiLib\Exception $e) {
    echo "에러: " . $e->getMessage();
}
```

---

### Using `bind_param()`

```php
$DB->bind_param('i');
$DB->query("INSERT INTO test SET id = ?, reg_date = ?", [33, date("Y-m-d H:i:s")]);

$DB->bind_param('i');
$DB->query("DELETE FROM test WHERE id = ?", [33]);
```

---

## 📧 Contact

사용 중 문의사항이나 버그 제보는 언제든지 아래 이메일로 연락주세요:

📨 **jonathanbak@gmail.com**

---

## 🧾 License

MIT License.
