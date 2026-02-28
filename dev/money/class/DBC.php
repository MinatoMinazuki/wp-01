<?php

require_once __DIR__."/../config.php";

/**
  *
  */
class DBC
{
  private $dbh;

  // データベースに接続
  function __construct()
  {
    $dsn = 'mysql:host='.HOST.';dbname='.DB_NAME.';charset='.UTF;
    try{
      $this->dbh = new PDO($dsn, USER, PASS);
    } catch(Exception $e) {
      exit($e->getMessage());
    }

    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
  }

  public function select($sql){
    $stmt = $this->dbh->query($sql);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $items;
  }

  public function Dsql($sql){
    try {
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute();

        $queryType = strtoupper(strtok(trim($sql), " ")); // 最初の単語を取得して判定
        $queryType = trim($queryType);

        switch ($queryType) {
          case 'SELECT':
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        echo "エラー: " . $e->getMessage();
        return false;
    }
  }
}

?>