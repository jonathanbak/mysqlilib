# MySQLiLib

가볍게, 간단히 실무에 적용할수 있는 MySQL 라이브러리입니다.

사용해보시고 이상있으면 메일주세요~ 언제든지 문의 환영입니다.

## Install
```bash
$ composer require jonathanbak/mysqlilib

$ composer install
```

## Test
phpunit.xml.dist 에 아래 내용을 본인의 MySQL 서버 정보를 넣고
```bash
<php>
    <var name="DB_HOST" value="localhost" />
    <var name="DB_USER" value="test" />
    <var name="DB_PASSWD" value="test1234" />
    <var name="DB_NAME" value="db_test" />
    <var name="DB_PORT" value="3306" />
</php>
```
phpunit 실행하여 테스트 해봅니다.
```bash
$ vendor/bin/phpunit

```

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

test 테이블의 name LIKE '테스트%' 인 데이터 여러 행 가져오기

```php
$query = "SELECT * FROM test WHERE name LIKE '??%'";
$rows = array();
while($row = $DB->fetch($query, array('테스트'))){
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

#### Exception

test 테이블의 id = 33 인 데이터가 이미 입력되있을때 Duplicate entry '33' for key 'PRIMARY' 오류 발생시 

```php
try{
    $query = "INSERT INTO test SET id = ?, reg_date = ?";
    $result = $DB->query($query, array(33, date("Y-m-d H:i:s")));
}catch(\MySQLiLib\Exception $e){
    //print error message "Duplicate entry '33' for key 'PRIMARY'"
    var_dump($e->getMessage());
}
```

#### Prepared statement query

```php
$query = "INSERT INTO test SET id = ?, reg_date = ?";
$DB->bind_param('i');
$result = $DB->query($query, array(33, date("Y-m-d H:i:s")));
var_dump($result);

$query = "DELETE FROM test SET id = ?";
$DB->bind_param('i');
$result = $DB->query($query, array(33));
var_dump($result);
```