# MySQLiLib

가볍게, 간단히 실무에 적용할수 있는 MySQL 라이브러리입니다.

사용해보시고 이상있으면 메일주세요~ 언제든지 문의 환영입니다.

## Install

## Usage

간단한 디비 연결 및 SELECT 쿼리 :

```php
$DB = new MySQLiLib($host, $user, $password, $dbName);
$query = "SELECT * FROM test";
$row = $DB->fetch($query);
var_dump($row);
```

#### SELECT

test 테이블의 id = 222 인 데이터 한 행 가져오기

```php
$query = "SELECT * FROM test WHERE id = ?";
$row = $DB->fetch($query, array(222));
var_dump($row);
```

test 테이블의 id = 11 인 데이터 여러 행 가져오기

```php
$query = "SELECT * FROM test WHERE id = ?";
$rows = array();
while($row = $DB->fetch($query, array(11))){
    $rows[] = $row;
}
var_dump($rows);
```

#### INSERT, UPDATE, DELETE

```php
$query = "INSERT INTO test SET id = ?, reg_date = ?";
$result = $DB->query($query, array(33, date("Y-m-d H:i:s")));
var_dump($result);

$query = "DELETE FROM test SET id = ?";
$result = $DB->query($query, array(33));
var_dump($result);
```