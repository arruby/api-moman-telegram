<?php
$server = "";
$username = "";
$password = "";
$nama_db = "";

$envFilePath = __DIR__ . '/.env';

if (file_exists($envFilePath)) {
    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            putenv("$key=$value");

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
} else {
    die('.env file not found.');
}

$tipeKon = getenv('tipe');
$base_url = getenv('base_url');
$secret_token = getenv('secret_token');
$bot_token = getenv("bot_token");

$db_log = getenv('dblog_name');
$dbms_tipe = getenv('dbms_tipe');
$dbms_user = getenv('dbms_user');
$dbms_jenis = getenv('dbms_jenis');
$dbms_laporan = getenv('dbms_laporan');
$db_transk = getenv('db_transk');
$db_limit = getenv('db_limit');

$conn = mysqli_connect($server, $username, $password, $nama_db);

function m_query($q)
{
    global $conn;
    $sql = mysqli_query($conn, $q);
    return $sql;
}

function m_fetch_array($s)
{
    $abc = mysqli_fetch_array($s);
    return $abc;
}

function m_num_rows($s)
{
    $abc = mysqli_num_rows($s);
    return $abc;
}

function m_insert_id()
{
    global $conn;
    $abc = mysqli_insert_id($conn);
    return $abc;
}

function sql_insert($nama_tabel, $data)
{
    $get_kolom     = array_keys($data);
    $get_datax     = array_values($data);

    $kolom         = implode(',', $get_kolom);
    $datax         = implode('##$$', $get_datax);

    $gabung        = "";
    $pch        = explode('##$$', $datax);
    $j            = count($pch);
    for ($i = 0; $i < $j; $i++) {
        $gabung .= "'" . $pch[$i] . "',";
    }

    $gabung =  rtrim($gabung, ',');
    $sql    = "insert into $nama_tabel ($kolom) values ($gabung)";
    return $sql;
}

function sql_update($nama_tabel, $data, $kondisi)
{
    $get_kolom = array_keys($data);
    $get_datax = array_values($data);

    $kolom         = implode('##$$', $get_kolom);
    $datax         = implode('##$$', $get_datax);

    $gabung        = "";
    $pch        = explode('##$$', $datax);
    $j            = count($pch);
    for ($i = 0; $i < $j; $i++) {
        //is_string
        $gabung .= "'" . $pch[$i] . "'##$$";
    }
    //================================================

    $setting_data = "";
    $list_kolom = explode('##$$', $kolom); //$kolom = a,b,c,d
    $list_data     = explode('##$$', $gabung); //gabung = 'a','b','c','d'

    $jj = count($list_kolom);
    for ($ii = 0; $ii < $jj; $ii++) {
        $setting_data .= "$list_kolom[$ii]=$list_data[$ii],";
    }


    $setting_data =  rtrim($setting_data, ',');
    $sql    = "update $nama_tabel set $setting_data where $kondisi";
    return $sql;
    //echo $sql;
}
