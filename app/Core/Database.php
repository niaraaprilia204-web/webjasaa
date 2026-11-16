<?php

/**
 * PDO Database Class Wrapper
 */
class Database
{
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $port = DB_PORT;
    private $charset = DB_CHARSET;

    private $dbh; // Database Handler
    private $stmt; // Statement
    private $error;
    private $isConnected = false;

    public function __construct()
    {
        $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->dbname . ';charset=' . $this->charset;
        $options = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ];

        try {
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
            $this->isConnected = true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->isConnected = false;
        }
    }

    public function isConnected()
    {
        return $this->isConnected;
    }

    public function getError()
    {
        return $this->error;
    }

    public function query($sql)
    {
        if (!$this->isConnected) {
            return false;
        }
        $this->stmt = $this->dbh->prepare($sql);
        return $this;
    }

    public function bind($param, $value, $type = null)
    {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

    public function execute()
    {
        if (!$this->stmt) {
            return false;
        }
        return $this->stmt->execute();
    }

    public function resultSet()
    {
        $this->execute();
        return $this->stmt->fetchAll();
    }

    public function single()
    {
        $this->execute();
        return $this->stmt->fetch();
    }

    public function rowCount()
    {
        return $this->stmt->rowCount();
    }

    public function lastInsertId()
    {
        if (!$this->isConnected || !$this->dbh) {
            return 0;
        }
        return (int) $this->dbh->lastInsertId();
    }

    public function beginTransaction()
    {
        return $this->isConnected && $this->dbh ? $this->dbh->beginTransaction() : false;
    }

    public function commit()
    {
        return $this->isConnected && $this->dbh ? $this->dbh->commit() : false;
    }

    public function rollBack()
    {
        return $this->isConnected && $this->dbh ? $this->dbh->rollBack() : false;
    }
}
