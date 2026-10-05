<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local')) throw new \RuntimeException('Data demo hanya boleh dibuat pada lingkungan lokal.');
        $now=now('Asia/Jakarta');
        $branch=DB::table('branches')->where('code','DEMO')->value('id');
        if (!$branch) $branch=DB::table('branches')->insertGetId(['code'=>'DEMO','name'=>'Cabang Demo Jakarta',
            'latitude'=>-6.175392,'longitude'=>106.827153,'radius_m'=>100,'qr_secret'=>Str::random(64),
            'created_at'=>$now,'updated_at'=>$now]);
        $position=DB::table('positions')->where('name','Staf')->value('id');
        if (!$position) $position=DB::table('positions')->insertGetId(['name'=>'Staf','created_at'=>$now,'updated_at'=>$now]);
        foreach ([
            ['Admin Demo','admin@example.test','admin',null,0],
            ['Manager Demo','manager@example.test','manager',$branch,0],
            ['Karyawan Demo','karyawan@example.test','employee',$branch,3000000],
        ] as [$name,$email,$role,$branchId,$salary]) {
            if (!DB::table('users')->where('email',$email)->exists()) DB::table('users')->insert([
                'name'=>$name,'email'=>$email,'password'=>Hash::make('Demo12345!'),'role'=>$role,
                'branch_id'=>$branchId,'position_id'=>$position,'hired_at'=>$now->copy()->subMonth()->toDateString(),
                'base_salary'=>$salary,'active'=>true,'created_at'=>$now,'updated_at'=>$now]);
        }
        $employee=DB::table('users')->where('email','karyawan@example.test')->first();
        if (!DB::table('shifts')->where('user_id',$employee->id)->exists())
            DB::table('shifts')->insert(['user_id'=>$employee->id,'branch_id'=>$branch,
                'start_at'=>$now->copy()->addMinutes(10)->toDateTimeString(),
                'end_at'=>$now->copy()->addHours(2)->toDateTimeString(),
                'status'=>'approved','approved_by'=>DB::table('users')->where('email','admin@example.test')->value('id'),
                'approved_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
    }
}
