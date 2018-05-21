<?php
/**
 * Created by PhpStorm.
 * User: 김명철
 * Date: 2018-05-07
 * Time: 오후 10:58
 */

namespace Mcl\Session;

use Mcl\Db\DBManager;
use Mcl\Timer\Timestamp;

class DbSecureHandler extends SecureHandler
{
    protected $db;

    function __construct($pdo = null)
    {
        parent::__construct();

        $this->db = new DBManager($pdo);

        // register_shutdown_function('session_write_close');
    }

    public function open($save_path, $session_name)
    {
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $sql = "SELECT privilege,session_key FROM sessions WHERE id = ?";

        $row = $this->db->executePreparedQueryToMap($sql, array(
            $id
        ));

        if (is_null($row['privilege'])) {
            return '';
        }

        $key = $row['session_key'];
        $privilege = base64_decode($row['privilege']);
        $data = $this->decrypt($privilege, $key);
        settype($data, "string");
        return $data;
    }

    public function write($id, $data)
    {
        $time = time() + get_cfg_var("session.gc_maxlifetime");

        $key = $this->session_key($id);
        $privilege = $this->encrypt($data, $key);

        $data = array(
            'id' => $id,
            'privilege' => base64_encode($privilege),
            'session_key' => $key,
            'updated' => Timestamp::getUNIXtime()
        );

        return $res = $this->db->AutoExecuteReplace('sessions', $data);
    }

    public function destroy($id)
    {
        $sql = "DELETE FROM sessions WHERE id = ?";

        $res = $this->db->executePreparedUpdate($sql, array(
            $id
        ));

        return true;
    }

    public function gc($maxlifetime)
    {
        $sql = "DELETE FROM sessions WHERE updated < ?";

        $res = $this->db->executePreparedUpdate($sql, array(
            Timestamp::getUNIXtime() - $maxlifetime
        ));

        return true;
    }

    private function session_key($session_id)
    {
        $sql = "SELECT session_key FROM sessions WHERE id = ?";

        $res = $this->db->executePreparedQueryOne($sql, array(
            $session_id
        ));

        return ($res) ? $res : base64_encode(random_bytes(64));
    }
}

/* example
$dbname = 'mysql';
$user = 'root';
$pass = 'root1234';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=' . $dbname, $user, $pass);
} catch (PDOException $e) {
    die('Connection failed.' . $e->getMessage());
}

ini_set('session.save_handler', 'user');
$handler = new Mcl\Session\DbSecureHandler($pdo);
session_set_save_handler($handler, true);
session_start();

if (empty($_SESSION['time'])) {
    $_SESSION['time'] = time(); // set the time
}
session_write_close();

var_dump($_SESSION);
*/