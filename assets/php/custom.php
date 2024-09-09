<?php
function convert_tanggal($tgl)
{
    $arrBulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agsustus', 'September', 'Oktober', 'November', 'Desember'];
    $tglSplit = explode('-', $tgl);
    $bulan = (int)$tglSplit[1];
    $hasil = "$tglSplit[2] $arrBulan[$bulan] $tglSplit[0]";

    return $hasil;
}

function uang($uang)
{
    return str_replace(".", "\\.", number_format($uang, 2, ',', '.'));
}

function reduce_tanggal($jml)
{
    return date('Y-m-d', strtotime("-$jml days"));
}

function escape_txt($txt)
{
    $search = ['-', '.', '+', '(', ')'];
    $replc  = ['\\-', '\\.', '\\+', '\\(', '\\)'];

    return str_replace($search, $replc, $txt);
}
