<?php

/**
 * @desc mini class to manipulate sqlite db for evocluster.
 *
 * @example
 * <?php
 *   require 'bdd.php';
 *
 *   // create bdd
 *   $bdd = new bdd();
 *
 *    if (!file_exists("evocluster.sqlite"))
 *       $bdd->create("evocluster.sqlite");
 *    else
 *       $bdd->open("evocluster.sqlite");
 *
 *    // declare account
 *    $account_demo = array(
 *        "name" => "demo",
 *        "domain" => "demo.quai13.fr",
 *        "bdd" => "demo_bdd",
 *        "replication" => "realtime");
 *    
 *    $bdd->add_account($account_demo);
 *    
 *    // declare servers
 *    $server1 = array("name" => "quai13-www00");
 *    $server2 = array("name" => "quai13-www01");
 *    
 *    // declare roles
 *    $bdd->add_role($account_demo['name'], 'quai13-www00', 'master');
 *    $bdd->add_role($account_demo['name'], 'quai13-www01', 'slave');
 *
 *    // list domains
 *    $domains = $bdd->list_domains();
 *    
 *    ?>
 */

class bdd {

    private $db; /* resource of a created database */

    /**
     * @desc Open a sqlite database. Create it if it doesn't exist.
     * @param string $db_name Name of the sqlite database
     */  
    public function open($db_name)
    {
        try {
            $this->db = new SQLiteDatabase($db_name, 0666, $error);
        }
        catch(Exception $e)
        {
            die ($error);
        }
    }

    /**
     * @desc Close the current database instance
     *
     * Note: php close automatically the instance on exit
     */
    public function close()
    {
        sqlite_close($this->db);
    }

    /**
     * @desc Install the table structure on a new sqlite database
     * @param string $dn_name Name of the sqlite database
     */
    public function create($db_name)
    {
        /*
         * open a database or create it if it doesn't exist
         */
        $this->open($db_name);

        $database = $this->db;


        /* Table Accounts */
        $query = 'CREATE Table Accounts ' .
            '(id INTEGER PRIMARY KEY , name TEXT,  domain TEXT, bdd TEXT, replication TEXT, id_master INTEGER, id_slave INTEGER)';

        if (!$database->queryExec($query, $error))
        {
            die($error);
        }

        /* Table Servers */
        $query = 'CREATE Table Servers ' .
            '(id INTEGER PRIMARY KEY , name TEXT, ip TEXT)';

        if (!$database->queryExec($query, $error))
        {
            die($error);
        }

        /* Table ServersAlias */
        $query = 'CREATE Table Serveralias ' .
            '(id INTEGER PRIMARY KEY , domain TEXT, alias TEXT)';

        if (!$database->queryExec($query, $error))
        {
            die($error);
        }

        /* Table Roles */
        $query = 'CREATE Table Roles ' .
            '(id INTEGER PRIMARY KEY , name TEXT, id_account INTEGER, id_server INTEGER)';

        if (!$database->queryExec($query, $error))
        {
            die($error);
        }
    }

   public function get_server_from_roleid($roleid)
   {
        $database = $this->db;

        $query = "SELECT Servers.name FROM Servers, Roles where Roles.id = '$roleid' and Roles.id_server = Servers.id";

	if ($result = $database->query($query, SQLITE_ASSOC, $error))
        {
            $row = $result->fetch();
            if (isset($row))
                return $row['Servers.name'];
        }

	return 0;
   }

    /**
     * @desc Get the account id of an account_name
     * @param string $account_name Name of the account
     * @return int 0 if account_name doesn't exist,
     *             id else
     */ 
    private function get_account_id($account_name)
    {
        $database = $this->db;

        $query = "SELECT id FROM Accounts where name = '$account_name'";

        if ($result = $database->query($query, SQLITE_ASSOC, $error))
        {
            $row = $result->fetch();
            if (isset($row))
                return $row['id'];
        }

        return 0;
    }

    /**
     * @desc Get account 
     * @param account name
     * @return array
     */
    public function get_account($account_name)
    {
        $database = $this->db;

        $query = "SELECT * FROM Accounts where name = '$account_name'";

        if ($result = $database->query($query, SQLITE_ASSOC, $error))
        {
            $row = $result->fetch();
            if (isset($row))
                return $row;
            else 
                return array();
        }

        return array();
    }

    /** 
     * @desc Check if account_name entry exists on table Accounts
     * @param string $account_name Name of the account
     * @return int 1 if it exists,
     *             0 else
     */
    public function is_account($account_name)
    {
        return !!($this->get_account_id($account_name));
    }

    /**
     * @desc Add an account to the table Accounts
     * @param array { 
     *          'name' => "$name",
     *          'domain' => "$domain",
     *          'bdd' => "bdd",
     *          'replication' => "replication"
     *        }
     * @return 1 on success,
     *         0 else
     */
    public function add_account($account)
    {
        $database = $this->db;

        $name = $account["name"];
        $domain = $account["domain"];
        $bdd = $account["bdd"];
        $replication = $account["replication"];

        /* check if account exists */
        if ($this->is_account($name))
            return 0;

        $query = "INSERT INTO Accounts (name, domain, bdd, replication)
            VALUES (
                '$name',
                '$domain',
                '$bdd',
                '$replication');";

        if (!$database->queryExec($query, $error))
        {
            die($error);
        }
        return 1;
    }


    /**
     * @desc Add an alias to the table Serveralias
     * @param array { 
     *          'domain' => "$domain",
     *          'alias' => "$alias",
     *        }
     * @return 1 on success,
     *         0 else
     */
    public function add_serveralias($serveralias)
    {
        $database = $this->db;

        $domain = $serveralias["domain"];
        $alias = $serveralias["alias"];

        $query = "INSERT INTO Serveralias (domain, alias)
            VALUES (
                '$domain',
                '$alias');";

        if (!$database->queryExec($query, $error))
        {
            die($error);
        }
        return 1;
    }
    
    /**
     * @desc Del an alias from the table Serveralias
     * @param array { 
     *          'domain' => "$domain",
     *          'alias' => "$alias",
     *        }
     * @return 1 on success,
     *         0 else
     */
    public function del_serveralias($serveralias)
    {
        $database = $this->db;


        $domain = $serveralias['domain'];
        $alias = $serveralias['alias'];

        $query = "DELETE FROM Serveralias WHERE domain='$domain' AND alias='$alias';";

        if (!$database->queryExec($query, $error))
            die($error);

        return 1;
    }

    /**
     * @desc Get the server id of a server_name
     * @param string $server_name Name of the server
     * @return int 0 if server_name doesn't exist,
     *             id else
     */ 
    private function get_server_id($server_name)
    {
        $database = $this->db;

        $query = "SELECT id FROM Servers where name = '$server_name'";

        if ($result = $database->query($query, SQLITE_ASSOC, $error))
        {
            $row = $result->fetch();
            if (isset($row))
                return $row['id'];
        }

        return 0;
    }

    /** 
     * @desc Check if server_name entry exists on table Servers
     * @param string $server_name Name of the server
     * @return int 1 if it exists,
     *             0 else
     */
    public function is_server($server_name)
    {
        return !!($this->get_server_id($server_name));
    }


    /**
     * @desc Add an server to the table Servers
     * @param array { 
     *          'name' => "$name",
     *        }
     * @return 1 on success,
     *         0 else
     */
    public function add_server($server)
    {
        $database = $this->db;

        $name = $server["name"];

        /* check if server exists */
        if ($this->is_server($name))
            return 0;

        $query = "INSERT INTO Servers (name)
            VALUES ( 
                '$name'
            );";

        if (!$database->queryExec($query, $error))
            die($error);

        return 1;
    }

    /**
     * @desc Add a role to the table Roles
     * @param string $account_name
     *        string $server_name
     *        string $role master or slave
     * @return 1 on success,
     *         0 else.
     */
    public function add_role($account_name, $server_name, $role)
    {
        $database = $this->db;

        /* get id_account */
        $id_account = $this->get_account_id($account_name);
        if ($id_account == 0)
            return 0;

        /* get id_server */
        $id_server = $this->get_server_id($server_name);
        if ($id_server == 0)
            return 0;

        $query = "INSERT INTO Roles (name, id_account, id_server)
            VALUES (
                '$role',
                '$id_account',
                '$id_server')";

        if (!$database->queryExec($query, $error))
            die($error);

        $id = $database->lastInsertRowid();

        /* update id_master or id_slave */
        if (($role === 'master') || ($role === 'slave'))
            $query = "UPDATE Accounts SET id_$role = '$id' WHERE id = '$id_account'"; 

        if (!$database->queryExec($query, $error))
            die($error);

        return 1;
    }


    /**
     * @desc List domains by server's role
     * @param none
     * @return list of array 
     *      example : Array (
     *                  [0] => Array
     *                  (
     *                      [Accounts.id] => 1
     *                      [Accounts.name] => demo
     *                      [Accounts.domain] => demo.quai13.fr
     *                      [Accounts.bdd] => demo_bdd
     *                      [Accounts.replication] => realtime
     *                      [Accounts.id_master] => 1
     *                      [Accounts.id_slave] => 2
     *                      [Roles.id] => 1
     *                      [Roles.name] => master
     *                      [Roles.id_account] => 1
     *                      [Roles.id_server] => 1
     *                      [Servers.id] => 1
     *                      [Servers.name] => quai13-www00
     *                      [Servers.ip] => 
     *                  )
     *                  [1] => Array
     *                  ( 
     *                      ... 
     *                  )
     *                  ...
     *                )
     */
    public function list_domains()
    {
        $database = $this->db;

        $query = "SELECT * FROM Accounts, Roles, Servers WHERE Accounts.id = Roles.id_account AND Roles.id_server = Servers.id";
        if($result = $database->query($query, SQLITE_ASSOC, $error))
        {
            $domains = array();
            $i = 0;

            while($row = $result->fetch())
            {
                $domains[$i] = $row;
                ++$i;
            }
        }
        else
            die($error);

        return $domains;
    }

    public function list_accounts()
    {
	$database = $this->db;

	$query = "SELECT * FROM Accounts";

        if($result = $database->query($query, SQLITE_ASSOC, $error))
        {
            $accounts = array();
            $i = 0;

            while($row = $result->fetch())
            {
                $accounts[$i] = $row;
                ++$i;
            }
        }
        else
            die($error);

        return $accounts;
    }

    public function list_serveralias($domain)
    {
	    $database = $this->db;
    
        if ($domain == NULL)
            return NULL;

	    $query = "SELECT * FROM Serveralias WHERE domain = '$domain'";
        
        if ($result = $database->query($query, SQLITE_ASSOC, $error))
        {
            $serveralias = array();
            $i = 0;

            while($row = $result->fetch())
            {
                $serveralias[$i] = $row;
                ++$i;
            }
        }
        else
            die($error);

        return $serveralias;
    }
}
