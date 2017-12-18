<?php

class Database
{

  private $pdo;
  private $settings = [
    "host" => "localhost",
    "database" => "",
    "user" => "root",
    "password" => "",
    "port" => "3306"
  ];

  private $parameters;
  private $connected = false;
  private $query;
  public $queryCount = 0;
  public $rowCount = 0;
  public $columnCount = 0;


  public function __construct($host = null, $database = null, $user = null, $password = null, $port = 3306)
  {
    if ($host != null) $this->settings["host"] = $host;
    if ($database != null) $this->settings["database"] = $database;
    if ($user != null) $this->settings["user"] = $user;
    if ($password != null) $this->settings["password"] = $password;
    if ($port != null) $this->settings["port"] = $port;
    $this->connect();
    $this->parameters = array();
  }

  private function connect()
  {
    try {
      $this->pdo = new PDO("mysql:host=" . $this->settings["host"] . ";port=" . $this->settings["port"] . ";dbname=" . $this->settings["database"] . ";charset=utf8",
        $this->settings["user"],
        $this->settings["password"],
        array(
          //For PHP 5.3.6 or lower
          PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
          PDO::ATTR_EMULATE_PREPARES => false,
          //PDO::ATTR_PERSISTENT => true,
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        )
      );
      $this->connected = true;
    } catch (PDOException $e) {
      echo $e;
    }
  }

  public function disconnect()
  {
    $this->pdo = null;
  }

  private function init($query, $parameters = "")
  {
    if (!$this->connected) {
      $this->Connect();
    }
    try {
      $this->parameters = $parameters;
      $this->query = $this->pdo->prepare($this->createParameters($query, $this->parameters));
      if (!empty($this->parameters)) {
        if (array_key_exists(0, $parameters)) {
          $parametersType = true;
          array_unshift($this->parameters, "");
          unset($this->parameters[0]);
        } else {
          $parametersType = false;
        }
        foreach ($this->parameters as $column => $value) {
          $this->query->bindParam($parametersType ? intval($column) : ":" . $column, $this->parameters[$column]);
        }
      }
      $this->succes = $this->query->execute();
      $this->queryCount++;
    } catch (PDOException $e) {
      // Throw the exception, maybe add logging functionality
      throw new PDOException($e);
    }
    $this->parameters = array();
  }

  private function createParameters($query, $parameters = null)
  {
    if (!empty($parameters)) {
      $statement = explode(" ", $query);
      foreach ($statement as $value) {
        if (strtolower($value) == 'in') {
          return str_replace("(?)", "(" . implode(",", array_fill(0, count($parameters), "?")) . ")", $query);
        }
      }
    }
    return $query;
  }

  public function query($query, $parameters = null, $fetchMode = PDO::FETCH_ASSOC)
  {
    $query = trim($query);
    $statement = explode(" ", $query);
    $this->init($query, $parameters);
    $statement = strtolower($statement[0]);
    if ($statement === 'select' || $statement === 'show') {
      return $this->query->fetchAll($fetchMode);
    } elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
      return $this->query->rowCount();
    } else {
      return NULL;
    }
  }

  public function lastInsertId()
  {
    return $this->pdo->lastInsertId();
  }

  public function column($query, $parameters = null)
  {
    $this->init($query, $parameters);
    $resultColumn = $this->query->fetchAll(PDO::FETCH_COLUMN);
    $this->rowCount = $this->query->rowCount();
    $this->columnCount = $this->query->columnCount();
    $this->query->closeCursor();
    return $resultColumn;
  }

  public function row($query, $parameters = null, $fetchMode = PDO::FETCH_ASSOC)
  {
    $this->init($query, $parameters);
    $resultRow = $this->query->fetch($fetchMode);
    $this->rowCount = $this->query->rowCount();
    $this->columnCount = $this->query->columnCount();
    $this->query->closeCursor();
    return $resultRow;
  }

  public function single($query, $parameters = null)
  {
    $this->init($query, $parameters);
    return $this->query->fetchColumn();
  }
}

