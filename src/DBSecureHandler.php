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

class DbSecureHandler extends SecureHandler //implements SessionHandlerInterface
{
    protected $db;

    function __construct($pdo = null, $logger = null)
    {
        parent::__construct();

        $this->db = new DBManager($pdo, $logger);
    }

    public function __destruct()
    {
        session_write_close(true);
    }

    public function open($save_path, $session_name)
    {
        $this->key = $this->getKey('KEY_' . $session_name);
        return true;
    }

    public function close()
    {
        return true;
    }

    public function read($id)
    {
        $sql = "SELECT session_data FROM ue_user_session WHERE id = ?";

//        $row = $this->db->executePreparedQueryToMap($sql, array(
//            $id
//        ));


//        if (is_null($row['session_data'])) {
//            return '';
//        }

        $session_data = $this->db->executePreparedQueryOne($sql, array(
            $id
        ));

        if (empty($session_data)) {
            return '';
        }

        $session_data = base64_decode($session_data);
        $data = $this->decrypt($session_data, $this->key);
        return $data;
    }

    public function write($id, $data)
    {
        $session_data = $this->encrypt($data, $this->key);

        $data = array(
            'id' => $id,
            'address' => $_SERVER ['REMOTE_ADDR'],
            'agent' => $_SERVER ['HTTP_USER_AGENT'],
            'session_data' => base64_encode($session_data),
            'session_key' => base64_encode($this->key),
            'server' => $_SERVER ['HTTP_HOST'],
            'request' => substr($_SERVER ['REQUEST_URI'], 0, 255),
            'referer' => isset ($_SERVER ['HTTP_REFERER']) ? substr($_SERVER ['HTTP_REFERER'], 0, 255) : '',
            'updated' => Timestamp::getUNIXtime()
        );

        $res = $this->db->AutoExecuteReplace('ue_user_session', $data);

        return true;
    }

    public function destroy($id)
    {
        $sql = "DELETE FROM ue_user_session WHERE id = ?";

        $res = $this->db->executePreparedUpdate($sql, array(
            $id
        ));

        return true;
    }

    public function gc($maxlifetime)
    {
        $sql = "DELETE FROM ue_user_session WHERE updated < ?";

        $res = $this->db->executePreparedUpdate($sql, array(
            Timestamp::getUNIXtime() - $maxlifetime
        ));

        return true;
    }

    private function session_key($id)
    {
        $sql = "SELECT session_key FROM ue_user_session WHERE id = ?";

        $res = $this->db->executePreparedQueryOne($sql, array(
            $id
        ));

        $key = random_bytes(64); // 32 for encryption and 32 for authentication
        $encKey = base64_encode($key);

        return ($res) ? base64_decode($res) : $encKey;
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