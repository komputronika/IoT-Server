<?php
/*
Script memerlukan file bernama ".htaccess" dengan isi seperti ini:
-----------------------------------
Options -MultiViews
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
-----------------------------------

Struktur URL adalah /function/key?var1=val1&var2=val2....

Misalnya:
Untuk menyimpan     = http://server.com/simpan/lokasiku?lan=0.11222&lat=1.883773
Untuk membaca text  = http://server.com/baca/lokasiku
Untuk membaca JSON  = http://server.com/baca/lokasiku/json

Author: Komputronika.com
*/

// Konfigurasi dasar
date_default_timezone_set('Asia/Jakarta');
set_time_limit(60*10);
error_reporting(0&~E_WARNING&~E_STRICT&~E_NOTICE);

// Include file konfigurasi database
require_once("config.php");

// Panggil fungsi untuk mendapatkan URL
$base_url = parsingURL();

// Masukan setiap bagian pada URL yang dipisahkan '/'
// ke dalam array 4routes
$routes = array();
$routes = explode('/', $base_url);
foreach($routes as $route) {
    if(trim($route) != '')
        array_push($routes, $route);
}

// Set $function sebagai bagian pertama dari URL
$function = trim(strtolower($routes[1]));

// Set $key sebagai bagian kedua dari URL
$key      = trim(strtolower($routes[2]));

// Set $format sebagai bagian ketiga dari URL
$format   = strtolower(trim(strtolower($routes[3])));

// Sesuaikan variable dengan config
$host  = $CONFIG["hostname"];
$user  = $CONFIG["username"];
$pass  = $CONFIG["password"];
$db    = $CONFIG["database"];
$table = "data";

// Konek ke database MySQL
// Struktur database bisa diimpor dari file iotserver.sql

$mysql = mysqli_connect( $host, $user, $pass, $db );

// Periksa aoakah isi dari variabel $function
switch ($function) {

    // Kalau 'baca', panggil fungsi baca()
    case "baca" :
        baca($key);
        break;

    // Kalau 'simpan', panggil fungsi simpan()
    case "simpan":
        simpan($key);
        break;

    // Kalau bukan 'baca' atau 'simpan', panggil fungsi ngaco()
    default:
        ngaco();
}

//----------------------------
// Fungsi untuk membaca data IoT
//----------------------------
function baca($key) {
    // Variabel $table diambil dari var global
    global $table, $format, $mysql;

    // Baca dari MySQL semua data data $key
    if ( strpos($format, 'single') !== false )  {
        $limit = 1;
    } else {
        $limit = 60;
    }

    // Query ke database MySQL
    $q = mysqli_query($mysql, "select CONVERT_TZ(created_at, @@session.time_zone, '+07:00') as created, content
                      from $table
                      where `key` = '$key'
                      order by created_at desc
                      limit 0,$limit");

    // Susun text nya
    $res = "";
    if (strpos($format, 'json') !== false) {
        $jsondata = array();
    }

    if ($limit == 1 and strpos($format, 'json') !== false) {

        $d = mysqli_fetch_object($q);
        $jsondata["created_at"] = $d->created;
        $json = json_decode($d->content);

        foreach($json as $k => $v){
            $jsondata["$k"] = $v;
        }

    } else {

        while ($d = mysqli_fetch_object($q)) {

            // Di awali dengan 'tanggal/'
            $res .= $d->created."/";

            // JSON yang tersimpan di table, dikonver jadi array
            $json = json_decode($d->content);

            // Baca setiap field dalam JSON, ke dalam array
            if (strpos($format, 'json') !== false) {
                $temp = array();
                $temp["created_at"] = $d->created;
            }

            $line = array();
            foreach($json as $k => $v){
                $line[] = "$k:$v";

                if (strpos($format, 'json') !== false) {
                    $temp["$k"] = $v;
                    $jsondata[] = $temp;
                }
            }
            // Jadikan array tersebut menjadi string dengan pemisah '/'
            $res .= implode("/",$line)."\n";
        }
    }

    // Kirim output
    header('Access-Control-Allow-Origin: *');
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    if (strpos($format, 'json') !== false) {
        // Kirim response berupa JSON
        header('Content-Type: application/json');
        echo json_encode($jsondata, JSON_PRETTY_PRINT);
    } else {
        // Kirim response berupa plain text
        header('Content-Type: text/plain');
        echo $res;
    }
}

//----------------------------
// Fungsi untuk menyimpan data IoT
//----------------------------
function simpan($key) {
    // Variabel $table diambil dari var global
    global $table, $mysql;

    // Konversi dulu semua variable yang terbaca di URL
    // Menjadi kode JSON, untuk disimpan dalam tabel
    $content = json_encode($_GET);

    // Simpan ke mysql
    mysqli_query($mysql, "insert into $table(`key`,content) values('$key','$content')");

    // Cek apakah ada error dalam insert ke database
    $success = empty(mysqli_error($mysql));

    // Response 'Error' atau 'Ok'
    header('Content-Type:text/plain');
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Tampilkan 'Ok' atau 'Error'
    if ($success) {
        echo "Ok";
    } else {
        echo "Error";
    }
}

//----------------------------
// Fungsi untuk menampilkan error
//----------------------------

function ngaco() {
    header('Content-Type:text/plain');
    header("Cache-Control: no-cache, no-store, must-revalidate");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "Wrong";
}

//----------------------------
// Fungsi untuk memparsing URL
//----------------------------
function parsingURL() {
    // Pecahkan nama script dari variabe $_SERVER
    $basepath = implode('/', array_slice(explode('/', $_SERVER['SCRIPT_NAME']), 0, -1)) . '/';

    // Hilangkan alamat servernya
    $uri = substr($_SERVER['REQUEST_URI'], strlen($basepath));

    // Bila ditemui tanda '?', potong dan buang
    if (strstr($uri, '?')) $uri = substr($uri, 0, strpos($uri, '?'));
    $uri = '/' . trim($uri, '/');

    return $uri;
}

?>
