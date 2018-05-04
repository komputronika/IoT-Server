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

// Set parameter untuk MySQL, sesuaikan!
$host = "localhost";
$user = ""; // Silahkan isi
$pass = ""; // Silahkan isi
$db   = ""; // Silahkan isi
$table= ""; // Silahkan isi

// Konek ke database MySQL
// Struktur database bisa diimpor dari file iotserver.sql

mysql_connect( $host, $user, $pass );
mysql_select_db( $db );

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
    global $table, $format;

    // Baca dari MySQL semua data data $key
    if ( strpos($format, 'single') !== false )  {
        $limit = 1;
    } else {
        $limit = 60;
    }
    $q = mysql_query("select * from $table where `key` = '$key' limit 0,$limit");

    // Susun text nya
    $res = "";
    if (strpos($format, 'json') !== false) {
        $jsondata = array();
    }

    if ($limit == 1) {

        $d = mysql_fetch_object($q);
        $jsondata["created_at"] = $d->created_at;
        $json = json_decode($d->content);

        foreach($json as $k => $v){
            $jsondata["$k"] = $v;
        }

    } else {

        while ($d = mysql_fetch_object($q)) {

            // Di awali dengan 'tanggal/'
            $res .= $d->created_at."/";

            // JSON yang tersimpan di table, dikonver jadi array
            $json = json_decode($d->content);

            // Baca setiap field dalam JSON, ke dalam array
            if (strpos($format, 'json') !== false) {

                $temp = array();
                $temp["created_at"] = $d->created_at;
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

    //if ($format=="json") {
    if (strpos($format, 'json') !== false) {
        // Kirim response berupa plain text
        header('Content-Type: application/json');
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo json_encode($jsondata, JSON_PRETTY_PRINT);
    } else {
        // Kirim response berupa JSON
        header('Content-Type: text/plain');
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo $res;
    }
}

//----------------------------
// Fungsi untuk menyimpan data IoT
//----------------------------
function simpan($key) {
    // Variabel $table diambil dari var global
    global $table;

    // Konversi dulu semua variable yang terbaca di URL
    // Menjadi kode JSON, untuk disimpan dalam tabel
    $content = json_encode($_GET);

    // Simpan ke mysql
    mysql_query("insert into $table(`key`,content) values('$key','$content')");

    // Cek apakah ada error dalam insert ke database
    $success = empty(mysql_error());

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
