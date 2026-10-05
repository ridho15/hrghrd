<?php

namespace App\Services;

use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ZipArchive;

final class EmployeeImport
{
    public function read(string $path, string $extension): array
    {
        if ($extension === 'csv') {
            $handle = fopen($path,'r');
            $first = fgets($handle); rewind($handle);
            $delimiter = substr_count($first,';') > substr_count($first,',') ? ';' : ',';
            if (substr_count($first,"\t") > substr_count($first,$delimiter)) $delimiter="\t";
            $rows=[];
            while (($row=fgetcsv($handle,0,$delimiter)) !== false) $rows[]=array_map(fn($v)=>trim((string)$v),$row);
            fclose($handle);
            return $rows;
        }
        if ($extension !== 'xlsx') throw new \RuntimeException('Gunakan CSV atau XLSX.');
        $zip=new ZipArchive();
        if ($zip->open($path)!==true) throw new \RuntimeException('File XLSX tidak dapat dibaca.');
        foreach (['xl/sharedStrings.xml','xl/worksheets/sheet1.xml'] as $entry) {
            $stat=$zip->statName($entry);
            if ($stat && $stat['size'] > 10_000_000) { $zip->close(); throw new \RuntimeException('Isi XLSX terlalu besar.'); }
        }
        $strings=[];
        if (($xml=$zip->getFromName('xl/sharedStrings.xml'))!==false) {
            $dom=new DOMDocument(); $dom->loadXML($xml,LIBXML_NONET);
            $xp=new DOMXPath($dom); $xp->registerNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($xp->query('//x:si') as $item) {
                $value=''; foreach ($xp->query('.//x:t',$item) as $text) $value.=$text->textContent;
                $strings[]=$value;
            }
        }
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
        if ($sheet===false) throw new \RuntimeException('Sheet pertama tidak ditemukan.');
        $dom=new DOMDocument(); $dom->loadXML($sheet,LIBXML_NONET);
        $xp=new DOMXPath($dom); $xp->registerNamespace('x','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows=[];
        foreach ($xp->query('//x:sheetData/x:row') as $row) {
            $values=[];
            foreach ($xp->query('./x:c',$row) as $cell) {
                $ref=$cell->getAttribute('r'); preg_match('/^[A-Z]+/',$ref,$m);
                $index=0; foreach (str_split($m[0] ?? 'A') as $letter) $index=$index*26+ord($letter)-64;
                $index--;
                $value=$xp->query('./x:v',$cell)->item(0)?->textContent ?? '';
                if ($cell->getAttribute('t')==='s') $value=$strings[(int)$value] ?? '';
                if ($cell->getAttribute('t')==='inlineStr') $value=$xp->query('.//x:t',$cell)->item(0)?->textContent ?? '';
                $values[$index]=trim($value);
            }
            if ($values) { ksort($values); $rows[]=array_replace(array_fill(0,max(array_keys($values))+1,''),$values); }
        }
        return $rows;
    }

    public function run(array $rows, array $map): array
    {
        $ok=0; $errors=[];
        foreach (array_slice($rows,1) as $index=>$row) {
            $line=$index+2;
            if (!array_filter($row,fn($v)=>trim((string)$v)!=='')) continue;
            $v=[]; foreach ($map as $field=>$column) $v[$field]=trim((string)($row[(int)$column] ?? ''));
            if (!$v['name'] || !filter_var($v['email'],FILTER_VALIDATE_EMAIL)) { $errors[]="Baris $line: nama/email tidak valid"; continue; }
            if (DB::table('users')->where('email',$v['email'])->exists()) { $errors[]="Baris $line: email sudah ada"; continue; }
            $branch=DB::table('branches')->where('code',$v['branch'])->first();
            if (!$branch) { $errors[]="Baris $line: kode cabang tidak ditemukan"; continue; }
            try {
                $hired=$this->date($v['hired_at']);
                $salaryText=preg_replace('/[^0-9.,]/','',$v['base_salary']);
                $salary=(int)preg_replace('/\D/','',preg_replace('/[.,]\d{2}$/','',$salaryText));
                if ($salary <= 0) throw new \RuntimeException();
            } catch (\Throwable) { $errors[]="Baris $line: tanggal mulai atau gaji tidak valid"; continue; }
            $position=DB::table('positions')->where('name',$v['position'])->value('id');
            if (!$position) $position=DB::table('positions')->insertGetId(['name'=>$v['position'] ?: 'Staf','created_at'=>now(),'updated_at'=>now()]);
            DB::table('users')->insert(['name'=>$v['name'],'email'=>$v['email'],'password'=>Hash::make(Str::random(40)),
                'role'=>'employee','branch_id'=>$branch->id,'position_id'=>$position,'hired_at'=>$hired,
                'base_salary'=>$salary,'active'=>!in_array(strtolower($v['active'] ?? 'aktif'),['0','tidak','nonaktif','inactive'],true),
                'created_at'=>now(),'updated_at'=>now()]);
            $ok++;
        }
        return ['imported'=>$ok,'failed'=>count($errors),'errors'=>$errors];
    }

    private function date(string $value): string
    {
        if (is_numeric($value) && (int)$value > 20000)
            return Carbon::create(1899,12,30)->addDays((int)$value)->toDateString();
        return Carbon::parse($value,'Asia/Jakarta')->toDateString();
    }
}
