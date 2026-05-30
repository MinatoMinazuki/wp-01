<?php
require_once __DIR__."/config.php";

class DBC
{
  private PDO $dbh;

  function __construct()
  {
    $dsn = 'mysql:host='.HOST.';dbname='.DB_NAME.';charset='.UTF;
    try{
      $this->dbh = new PDO($dsn, USER, PASS);
    } catch(Exception $e) {
      error_log('Database connection failed: ' . $e->getMessage());
      http_response_code(500);
      exit('Database connection failed.');
    }

    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->dbh->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  }

  /** @deprecated Use fetchAll() with bound parameters instead. */
  public function select($sql){
    $stmt = $this->dbh->query($sql);
    $items = $stmt->fetchAll();
    return $items;
  }

  public function fetchAll(string $sql, array $params = []): array
  {
    $stmt = $this->dbh->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
  }

  public function fetchOne(string $sql, array $params = []): ?array
  {
    $stmt = $this->dbh->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
  }

  public function execute(string $sql, array $params = []): int
  {
    $stmt = $this->dbh->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
  }

  public function insert(string $sql, array $params = []): string
  {
    $stmt = $this->dbh->prepare($sql);
    $stmt->execute($params);

    return $this->dbh->lastInsertId();
  }

  /** @deprecated Use fetchAll(), fetchOne(), execute(), or insert() instead. */
  public function Dsql($sql){
    try {
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute();

        $queryType = strtoupper(strtok(trim($sql), " "));
        $queryType = trim($queryType);

        switch ($queryType) {
          case 'SELECT':
            return $stmt->fetchAll();
            break;

          case 'INSERT':
            return $this->dbh->lastInsertId();
            break;

          case 'UPDATE':
          case 'DELETE':
            return $stmt->rowCount();
            break;
          default:
            return true;
            break;
        }
    } catch (PDOException $e) {
        return false;
    }
  }

  public function escape($str) {
    if ($str === null) return '';
    $quoted = $this->dbh->quote($str);
    return substr($quoted, 1, -1);
  }

  public function columnExists(string $table, string $column): bool
  {
    $row = $this->fetchOne(
      "
      SELECT COUNT(*) AS cnt
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = :table_name
        AND COLUMN_NAME = :column_name
      ",
      [
        'table_name' => $table,
        'column_name' => $column,
      ]
    );

    return $row !== null && (int)$row['cnt'] > 0;
  }
}
?>
