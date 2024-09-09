<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once('./config/koneksi.php');
require('./assets/php/custom.php');

date_default_timezone_set("Asia/Jakarta");

$datetime_now   = date("Y-m-d H:i:s");
$date_now       = date("Y-m-d");
$month_now      = date("m");
$year_now       = date("Y");

$notificationHeader         = getallheaders();
$notificationHeader_json    = json_encode($notificationHeader);
$notificationBody           = file_get_contents('php://input');
$arrNotifBody               = json_decode($notificationBody, true);

if (array_key_exists("callback_query", $arrNotifBody)) {
    $from = $arrNotifBody['callback_query']['from']['id'];
    $tipe = "callback_query";
    $data_callback = $arrNotifBody['callback_query']['data'];
} else {
    $from = $arrNotifBody['message']['from']['id'];
    $tipe = "message";
    $data_callback = $arrNotifBody['message']['text'];
    $firstName = $arrNotifBody['message']['from']['first_name'];
    $username = $arrNotifBody['message']['from']['username'];
    $entities = ($arrNotifBody['message']['entities'][0]['type']) ? $arrNotifBody['message']['entities'][0]['type'] : "none";
}

/* [15 Aug 24 14:40 ARIF] START - Find tipe on db ==================================================== */
$dataSplit = explode(" ", $data_callback);
// var_dump($dataSplit);
$slugTipe = strtolower($dataSplit[0]);

$sql        = "SELECT * FROM $dbms_tipe WHERE slug='$slugTipe' AND aktif='1'";
$res        = m_query($sql);
$count      = m_num_rows($res);
$arrAnchor  = m_fetch_array($res);

if ($count === 1) {
    $anchor = '1';
    $id_tipe = $arrAnchor['id'];
} else {
    $anchor = '2';
    $id_tipe = '0';
}

$txtJenis = getJenis($id_tipe);
/* [15 Aug 24 14:40 ARIF] END - Find tipe on db ==================================================== */

/* [18 Apr 24 05:46 ARIF] START - input log ==================================================== */
$dataInp = [
    "full_header"   => $notificationHeader_json,
    "full_body"     => $notificationBody,
    "telegram_id"   => $from,
    "tipe"          => $tipe,
    "id_tipe"       => $id_tipe,
    "data_callback" => $data_callback,
    "anchor"        => $anchor,
    "datetime"      => $datetime_now,
    "env"           => $tipeKon
];
$sqlInp = sql_insert($db_log, $dataInp);
$resInp = m_query($sqlInp);

if ($resInp) {
    $returnInp = [
        "code" => 200,
        "msg" => "Data berhasil input"
    ];

    $idLog = m_insert_id();
} else {
    $returnInp = [
        "code" => 500,
        "msg" => "Data gagal input"
    ];
}
/* [18 Apr 24 05:46 ARIF] END - input log ==================================================== */

$idUser = getUser($from)['id'];

/* [03 Sep 24 15:07 ARIF] START - Find limit per user to notify ==================================================== */
$sqlFLimit = "SELECT nominal, n_persen, n_nominal 
            FROM $db_limit
            WHERE id_user='$iduser' AND aktif='1'";
$resFLimit = m_query($sqlFLimit);
$arrFLimit = m_fetch_array($resFLimit); /* Checkpoint */
/* [03 Sep 24 15:07 ARIF] END - Find limit per user to notify ==================================================== */

if ($returnInp['code'] == 200) { /* [25 Apr 24 09:41 ARIF] cek apakah input ke db aman */
    if ($tipe == 'message') { /* [25 Apr 24 11:11 ARIF] ini kalau tipemya message */
        if ($entities == 'bot_command') { /* [25 Apr 24 11:09 ARIF] cek apakah command */
            switch ($data_callback) {
                case '/start':
                    $endpoint = "sendMessage";



                    $postField = [
                        "chat_id" => $from,
                        "text" => "Selamat datang di _Masprint Money Manager_ *$firstName*, silahkan pilih apa yang ingin kamu catat\\!",
                        "parse_mode" => "MarkdownV2",
                        "reply_markup" => [
                            "keyboard" => [
                                [
                                    "Pemasukan 🤑",
                                    "Pengeluaran 💸"
                                ],
                                ["Tabungan 💰"]
                            ],
                            "resize_keyboard" => true,
                            "is_persistent" => true
                        ]
                    ];

                    break;
                case '/last5':
                    $endpoint = "sendMessage";

                    $last5 = "";
                    $ancTgl = "";
                    /* [23 Aug 24 19:02 ARIF] START - find last 5 ==================================================== */
                    $sql = "SELECT a.tanggal, a.nominal, a.keterangan, b.nama as nmJenis 
                            FROM $db_transk a
                            JOIN $dbms_jenis b ON a.id_jenis = b.id
                            WHERE a.aktif='1' AND a.user_act='$idUser' ORDER BY a.id DESC LIMIT 5;";
                    $res = m_query($sql);
                    while ($arr = m_fetch_array($res)) {

                        $escapeJn = escape_txt($arr['nmJenis']);

                        $escapeKet = escape_txt($arr['keterangan']);

                        $uang = uang($arr['nominal']);
                        $tgl = convert_tanggal($arr['tanggal']);

                        if ($arr['tanggal'] != $ancTgl) {
                            $last5 .= "\n*\\[$tgl\\]* \n\\- $escapeJn \\| ||Rp$uang|| \\| $escapeKet\n";
                        } else {
                            $last5 .= "\\- $escapeJn \\| ||Rp$uang|| \\| $escapeKet\n";
                        }

                        $ancTgl = $arr['tanggal'];
                    }
                    /* [23 Aug 24 19:02 ARIF] END - find last 5 ==================================================== */

                    $postField = [
                        "chat_id" => $from,
                        "text" => "$last5",
                        "parse_mode" => "MarkdownV2"
                    ];

                    break;
                case '/listreport':
                    $endpoint = "sendMessage";

                    $postField = [
                        "chat_id" => $from,
                        "text" => "Okeh\\! Berikut adalah laporan yang tersedia, klik aja tombolnya\\!",
                        "parse_mode" => "MarkdownV2",
                        "reply_markup" => [
                            "inline_keyboard" => [],
                            "resize_keyboard" => true,
                            "is_persistent" => true
                        ]
                    ];

                    /* [23 Aug 24 22:35 ARIF] START - find master laporan ==================================================== */
                    $arrCounter     = (int)0;
                    $rprtCounter    = (int)0;

                    $sql = "SELECT * FROM $dbms_laporan WHERE aktif='1'";
                    $res = m_query($sql);
                    while ($arr = m_fetch_array($res)) {
                        $postField['reply_markup']['inline_keyboard'][$arrCounter][] =
                            [
                                "text" => "$arr[nama]",
                                "callback_data" => "report_$arr[callback_data]"
                            ];

                        $rprtCounter++;
                        if ($rprtCounter == 2) {
                            $arrCounter++;
                            $rprtCounter = (int)0;
                        }
                    }
                    /* [23 Aug 24 22:35 ARIF] END - find master laporan ==================================================== */
                    break;

                case '/status':
                    $endpoint = "sendMessage";
                    $postField = [
                        "chat_id" => $from,
                        "text" => "Halo *$firstName*, status bot sekarang adalah *$tipeKon*",
                        "parse_mode" => "MarkdownV2"
                    ];
                    break;
                case '/setlimit':
                    $endpoint = "sendMessage";
                    $postField = [
                        "chat_id" => $from,
                        "text" => "Halo *$firstName*, fitur ini masih dalam pengembangan, ditunggu ya\\!",
                        "parse_mode" => "MarkdownV2"
                    ];
                    break;
                default:
                    $endpoint = "sendMessage";

                    $postField = [
                        "chat_id" => $from,
                        "text" => "Halo *$firstName*, perintah tidak kami ketahui, coba lagi ya\\! Coba klik tombol _Menu_ di kiri bawah deh buat liat list perintahnya\\.",
                        "parse_mode" => "MarkdownV2"
                    ];
                    break;
            }

            // var_dump($postField);
            // exit;
            sendCurl($endpoint, $postField, $idLog);
        } else { /* [25 Apr 24 11:09 ARIF] kalau bukan command */
            $dataSplit = explode(" ", $data_callback);
            switch (strtolower($dataSplit[0])) {
                case "pemasukan":
                    $endpoint = "sendMessage";

                    $postField = [
                        "chat_id" => $from,
                        "text" => "Halo *$firstName*, Pemasukan ya? Siap\\!\n$txtJenis",
                        "parse_mode" => "MarkdownV2"
                    ];

                    break;
                case "pengeluaran":
                    $endpoint = "sendMessage";

                    $postField = [
                        "chat_id" => $from,
                        "text" => "Pengeluaran apa yang ingin kamu catat, *$firstName*?\n$txtJenis",
                        "parse_mode" => "MarkdownV2"
                    ];

                    break;
                case "tabungan":
                    $endpoint = "sendMessage";

                    $postField = [
                        "chat_id" => $from,
                        "text" => "Wih nabung nih *$firstName*? Buruan masukin deh, keburu ilang\\!\n$txtJenis",
                        "parse_mode" => "MarkdownV2"
                    ];

                    break;
                default:
                    $endpoint = "sendMessage";

                    $msg = explode("-", $data_callback);
                    if (count($msg) == 3) {
                        $jenis = $msg[0];
                        $uang = $msg[1];
                        $keterangan = $msg[2];

                        /* [17 Aug 24 15:31 ARIF] START - Find Jenis ==================================================== */
                        $sqlJenis = "SELECT a.*, b.id AS idTipe, b.telegram_stickerId AS stickerId, b.nama AS namaTipe
                                    FROM $dbms_jenis a
                                    JOIN $dbms_tipe b ON a.id_tipe = b.id 
                                    WHERE a.id='$jenis' AND a.aktif='1'";
                        $resJenis = m_query($sqlJenis);
                        $arrJenis = m_fetch_array($resJenis);

                        $namaTipe = strtolower($arrJenis['namaTipe']);
                        /* [17 Aug 24 15:31 ARIF] END - Find Jenis ==================================================== */

                        /* [16 Aug 24 21:34 ARIF] START - input to transaksi ==================================================== */
                        $dataInp = [
                            "id_jenis" => $jenis,
                            "tanggal" => $datetime_now,
                            "nominal" => $uang,
                            "keterangan" => $keterangan,
                            "user_act" => $idUser,
                            "tgl_act" => $datetime_now,
                        ];
                        $sqlInp = sql_insert($db_transk, $dataInp);
                        $resInp = m_query($sqlInp);
                        /* [16 Aug 24 21:34 ARIF] End - input to transaksi ==================================================== */

                        if ($resInp) {
                            /* [03 Sep 24 11:51 ARIF] START - Find total bulan ini ==================================================== */
                            $uangLimit = uang(getTot($arrJenis['idTipe']));
                            /* [03 Sep 24 11:51 ARIF] END - Find total bulan ini ==================================================== */

                            /* [18 Aug 24 18:57 ARIF] START - Update last command ==================================================== */
                            // $dataLc = [];
                            /* [18 Aug 24 18:57 ARIF] END - Update last command ==================================================== */

                            $tglReadable = convert_tanggal($date_now);
                            $uang = uang($uang);

                            $postField = [
                                "chat_id" => $from,
                                "text" => "Siap *$firstName*\\! \nData *$namaTipe* dengan nominal *Rp$uang* pada tanggal *$tglReadable* kamu udah tersimpan\\.\nKu tunggu catatan keuanganmu selanjutnyaa\\.\n\n_nb: Sekedar info aja\\, bulan ini kamu udah nyatet dengan total *Rp$uangLimit* buat $namaTipe loh_\\!",
                                "parse_mode" => "MarkdownV2",
                                "reply_markup" => [
                                    "keyboard" => [
                                        [
                                            "Pemasukan 🤑",
                                            "Pengeluaran 💸"
                                        ],
                                        ["Tabungan 💰"]
                                    ],
                                    "resize_keyboard" => true,
                                    "is_persistent" => true
                                ]
                            ];

                            sendSticker($arrJenis['stickerId'], $from);
                        } else {
                            $postField = [
                                "chat_id" => $from,
                                "text" => "Yah, maaf *$firstName*\\! Kok gagal tersimpan ya? Coba tanya ke admin deh\\.",
                                "parse_mode" => "MarkdownV2"
                            ];
                        }
                    } else {
                        $postField = [
                            "chat_id" => $from,
                            "text" => "*$firstName*, datamu tidak lengkap atau kelebihan nih\\. Cek lagi yaa",
                            "parse_mode" => "MarkdownV2"
                        ];
                    }
                    break;
            }
            sendCurl($endpoint, $postField, $idLog);
        }
    } elseif ($tipe == 'callback_query') { /* [25 Apr 24 11:11 ARIF] ini kalau tipenya callback_query */
        if (str_contains($data_callback, 'report')) {
            $expReport = explode('_', $data_callback);

            /* [24 Aug 24 22:41 ARIF] START - get last command message_id ==================================================== */
            $sqlMsgId = "SELECT message_id FROM $db_log WHERE telegram_id='$from' AND data_callback='/listreport' ORDER BY id DESC LIMIT 1";
            $resMsgId = m_query($sqlMsgId);
            $arrMsgId = m_fetch_array($resMsgId);
            $messId = $arrMsgId['message_id'];
            /* [24 Aug 24 22:41 ARIF] END - get last command message_id ==================================================== */

            /* [23 Aug 24 22:57 ARIF] START - find report ==================================================== */
            $sql = "SELECT command, pesan, custom FROM $dbms_laporan WHERE callback_data ='$expReport[1]' AND aktif = '1'";
            $res = m_query($sql);
            $arr = m_fetch_array($res);

            $command = json_decode($arr['command'], true);
            $pesanLap = $arr['pesan'];
            /* [23 Aug 24 22:57 ARIF] END - find report ==================================================== */

            $endpoint = "editMessageText";

            /* [07 Sep 24 20:58 ARIF] START - data where (tanggal comment tidak akurat) ==================================================== */
            $where = "";
            for ($i = 0; $i < count($command['where']); $i++) {
                $name = $command['where'][$i]['name'];
                $mid = $command['where'][$i]['mid'];
                if (str_contains($command['where'][$i]['command'], '|')) {
                    $expValue = explode('|', $command['where'][$i]['command']);
                    $value = $expValue[0]($expValue[1]);
                } else {
                    $value = $command['where'][$i]['command'];
                }

                if ($arr['custom'] == '0') {
                    $where .= "AND $name $mid '$value' ";
                } elseif ($arr['custom'] == '1') {
                    $ccWhere = $$value;
                    $where .= "AND $name $mid '$ccWhere' ";
                }
            }
            /* [07 Sep 24 20:58 ARIF] START - data where (tanggal comment tidak akurat) ==================================================== */

            /* [08 Sep 24 15:29 ARIF] START - data join ==================================================== */
            $csJoin = "";

            if ($command['join']) {
                for ($i = 0; $i < count($command['join']); $i++) {
                    $tb = $command['join'][$i]['tb'];
                    $on = $command['join'][$i]['on'];
                    $as = $command['join'][$i]['as'];

                    $ccTb = $$tb;
                    $csJoin .= "JOIN $ccTb $as ON $on ";
                }
            }
            /* [08 Sep 24 15:29 ARIF] END - data join ==================================================== */


            if ($arr['custom'] == '0') {
                /* [24 Aug 24 00:06 ARIF] START - get transaksi -- where from report ==================================================== */
                $sql = "SELECT a.tanggal, a.nominal, a.keterangan, b.nama as nmJenis 
                        FROM $db_transk a
                        JOIN $dbms_jenis b ON a.id_jenis = b.id
                        WHERE a.aktif='1' AND a.user_act='$idUser' $where;";
                $res = m_query($sql);
                while ($arr = m_fetch_array($res)) {

                    $escapeJn = escape_txt($arr['nmJenis']);

                    $uang = uang($arr['nominal']);
                    $tgl = convert_tanggal($arr['tanggal']);
                    $escapeKet = escape_txt($arr['keterangan']);

                    if ($arr['tanggal'] != $ancTgl) {
                        $dataKet .= "\n*\\[$tgl\\]* \n\\- $escapeJn \\| ||Rp$uang|| \\| $escapeKet\n";
                    } else {
                        $dataKet .= "\\- $escapeJn \\| ||Rp$uang|| \\| $escapeKet\n";
                    }

                    $ancTgl = $arr['tanggal'];
                }
                /* [24 Aug 24 00:06 ARIF] END - get transaksi -- where from report ==================================================== */
            } elseif ($arr['custom'] == '1') {
                $csSelect = $command['select'];
                $csGroup = $command['group_by'];

                $sql = "SELECT $csSelect 
                        FROM $db_transk a
                        $csJoin
                        WHERE a.aktif='1' AND a.user_act='$idUser' $where
                        GROUP BY $csGroup;";
                $res = m_query($sql);

                while ($arr = m_fetch_array($res)) {
                    $nom = uang($arr['nominal']);
                    $dataKet .= "\n$arr[nama] : ||Rp$nom||";
                }
                // echo $dataKet;
                // exit;
            }

            $postField = [
                "chat_id" => $from,
                "message_id" => $messId,
                "text" => "$pesanLap\n $dataKet",
                "parse_mode" => "MarkdownV2"
            ];
        }
        sendCurl($endpoint, $postField, $idLog);
    }
}
// echo json_encode($postField);
// exit;

/* [04 Sep 24 13:30 ARIF] START - find tot ==================================================== */
function getTot($tipe)
{
    global $month_now;
    global $year_now;
    global $db_transk;
    global $dbms_jenis;

    $sqlLimit = "SELECT SUM(nominal) AS tot
                FROM $db_transk a
                JOIN $dbms_jenis b ON a.id_jenis = b.id
                WHERE MONTH(a.tanggal) ='$month_now' AND YEAR(a.tanggal)='$year_now' AND b.id_tipe ='$tipe'";
    $resLimit = m_query($sqlLimit);
    $arrLimit = m_fetch_array($resLimit);
    $uangLimit = $arrLimit['tot'];

    return $uangLimit;
}
/* [04 Sep 24 13:30 ARIF] END - find tot ==================================================== */

/* [24 Aug 24 21:03 ARIF] START - find user ==================================================== */
function getUser($telegramId)
{
    global $dbms_user;

    $sqlUser = "SELECT * FROM $dbms_user WHERE telegram_id='$telegramId' AND aktif='1'";
    $resUser = m_query($sqlUser);
    $arrUser = m_fetch_array($resUser);

    // return $arrUser['id'];
    return $arrUser;
}
/* [24 Aug 24 21:03 ARIF] END - find user ==================================================== */

/* [17 Aug 24 16:36 ARIF] START - FIND jenis ==================================================== */
function getJenis($tipe)
{
    global $dbms_jenis;

    $sql = "SELECT GROUP_CONCAT(CONCAT('[ ',id,' ] ', nama) SEPARATOR '\n') AS tipe FROM $dbms_jenis WHERE id_tipe = '$tipe' AND aktif='1'";
    $res = m_query($sql);
    $arr = m_fetch_array($res);

    return str_replace('-', '\\-', str_replace(']', '\\]', str_replace('[', '\\[', $arr['tipe'])));
}
/* [17 Aug 24 16:36 ARIF] END - FIND jenis ==================================================== */

/* [17 Aug 24 00:08 ARIF] START - func send sticker ==================================================== */
function sendSticker($idStiker, $from)
{
    $stickerField = [
        "chat_id" => $from,
        "sticker" => "$idStiker"
    ];

    sendCurl("sendSticker", $stickerField);
}
/* [17 Aug 24 00:08 ARIF] END - func send sticker ==================================================== */

/* [25 Apr 24 09:46 ARIF] START - CURL ==================================================== */
function sendCurl($endpoint, $postField, $idLog = 0)
{
    global $base_url;
    global $bot_token;
    global $db_log;

    $postField = json_encode($postField);

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "$base_url$bot_token/$endpoint",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $postField,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $resReplaced = str_replace("'", "\'", $response);
        $jsonRes = json_decode($response, true);

        $message_id = $jsonRes['result']['message_id'];

        $dataUpd = [
            "full_response" => $resReplaced,
            "message_id" => $message_id
        ];
        $kondUpd = "id = '$idLog'";
        $sqlUpd = sql_update($db_log, $dataUpd, $kondUpd);
        $resUpd = m_query($sqlUpd);

        echo $response;
    }
}
/* [25 Apr 24 09:46 ARIF] END - CURL ==================================================== */