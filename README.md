# MySQLiLib

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

### SELECT with Parameters

```php
$query = "SELECT * FROM test WHERE id = ?";
$row = $DB->fetch($query, [222]);
```

### Fetch Multiple Rows (Iterator)

```php
$query = "SELECT * FROM test WHERE id = ?";
$rows = [];
while ($row = $DB->fetch($query, [11])) {
    $rows[] = $row;
}
```

### LIKE Query

```php
$query = "SELECT * FROM test WHERE name LIKE '??%'";
$rows = [];
while ($row = $DB->fetch($query, ['테스트'])) {
    $rows[] = $row;
}
```

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
