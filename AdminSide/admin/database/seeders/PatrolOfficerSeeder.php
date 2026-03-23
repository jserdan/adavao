<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class PatrolOfficerSeeder extends Seeder
{
    /**
     * Seed patrol officer accounts for all PS stations.
     * Safe to re-run - updates existing records.
     */
    public function run(): void
    {
        $this->command->info("Starting PatrolOfficerSeeder...");

        $password = Hash::make('patrol123');

        $stations = DB::table('police_stations')
            ->select('station_id', 'station_name')
            ->whereRaw("LOWER(COALESCE(station_name, '')) NOT LIKE ?", ['%cybercrime%'])
            ->orderBy('station_id')
            ->get()
            ->sortBy(function ($station) {
                if (preg_match('/\bPS\s*(\d+)\b/i', (string) $station->station_name, $matches)) {
                    return (int) $matches[1];
                }
                if (preg_match('/\bstation\s*(\d+)\b/i', (string) $station->station_name, $matches)) {
                    return (int) $matches[1];
                }

                return (int) $station->station_id;
            })
            ->values();

        if ($stations->isEmpty()) {
            $this->command->warn('No PS stations found. Skipping patrol account seeding.');
            return;
        }

        $patrolOfficers = [];
        foreach ($stations as $index => $station) {
            $psNumber = null;
            if (preg_match('/\bPS\s*(\d+)\b/i', (string) $station->station_name, $matches)) {
                $psNumber = (int) $matches[1];
            } elseif (preg_match('/\bstation\s*(\d+)\b/i', (string) $station->station_name, $matches)) {
                $psNumber = (int) $matches[1];
            }
            if ($psNumber === null || $psNumber <= 0) {
                $psNumber = (int) $station->station_id;
            }
            $psCode = str_pad((string) $psNumber, 2, '0', STR_PAD_LEFT);

            $patrolOfficers[] = [
                'firstname' => 'PS' . $psNumber,
                'lastname' => 'Patrol',
                'email' => 'ps' . $psCode . '.patrol@alertdavao.local',
                'contact' => '+6399000' . str_pad((string) $psNumber, 4, '0', STR_PAD_LEFT),
                'assigned_station_id' => $station->station_id,
            ];
        }

        foreach ($patrolOfficers as $officer) {
            // Insert into user_admin
            try {
                $exists = DB::table('user_admin')->where('email', $officer['email'])->exists();
                
                if (!$exists) {
                    $data = [
                        'firstname' => $officer['firstname'],
                        'lastname' => $officer['lastname'],
                        'email' => $officer['email'],
                        'contact' => $officer['contact'],
                        'password' => $password,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    // Add optional columns if they exist
                    if (Schema::hasColumn('user_admin', 'user_role')) {
                        $data['user_role'] = 'patrol_officer';
                    }
                    if (Schema::hasColumn('user_admin', 'email_verified_at')) {
                        $data['email_verified_at'] = now();
                    }
                    if (Schema::hasColumn('user_admin', 'assigned_station_id')) {
                        $data['assigned_station_id'] = $officer['assigned_station_id'];
                    }
                    
                    DB::table('user_admin')->insert($data);
                    $this->command->info("Created user_admin: {$officer['email']}");
                } else {
                    // Update existing
                    $update = ['updated_at' => now()];
                    if (Schema::hasColumn('user_admin', 'user_role')) {
                        $update['user_role'] = 'patrol_officer';
                    }
                    if (Schema::hasColumn('user_admin', 'email_verified_at')) {
                        $update['email_verified_at'] = now();
                    }
                    if (Schema::hasColumn('user_admin', 'assigned_station_id')) {
                        $update['assigned_station_id'] = $officer['assigned_station_id'];
                    }
                    DB::table('user_admin')->where('email', $officer['email'])->update($update);
                    $this->command->info("Updated user_admin: {$officer['email']}");
                }
            } catch (\Exception $e) {
                $this->command->error("user_admin error for {$officer['email']}: " . $e->getMessage());
                Log::error("PatrolOfficerSeeder user_admin error: " . $e->getMessage());
            }

            // Insert into users_public
            try {
                $existsPublic = DB::table('users_public')->where('email', $officer['email'])->exists();
                
                if (!$existsPublic) {
                    $dataPublic = [
                        'firstname' => $officer['firstname'],
                        'lastname' => $officer['lastname'],
                        'email' => $officer['email'],
                        'contact' => $officer['contact'],
                        'password' => $password,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    
                    // Add optional columns if they exist
                    if (Schema::hasColumn('users_public', 'user_role')) {
                        $dataPublic['user_role'] = 'patrol_officer';
                    }
                    if (Schema::hasColumn('users_public', 'role')) {
                        $dataPublic['role'] = 'patrol_officer';
                    }
                    if (Schema::hasColumn('users_public', 'is_on_duty')) {
                        $dataPublic['is_on_duty'] = false;
                    }
                    if (Schema::hasColumn('users_public', 'assigned_station_id')) {
                        $dataPublic['assigned_station_id'] = $officer['assigned_station_id'];
                    }
                    if (Schema::hasColumn('users_public', 'email_verified_at')) {
                        $dataPublic['email_verified_at'] = now();
                    }
                    
                    DB::table('users_public')->insert($dataPublic);
                    $this->command->info("Created users_public: {$officer['email']}");
                } else {
                    // Update existing
                    $updatePublic = ['updated_at' => now()];
                    if (Schema::hasColumn('users_public', 'user_role')) {
                        $updatePublic['user_role'] = 'patrol_officer';
                    }
                    if (Schema::hasColumn('users_public', 'role')) {
                        $updatePublic['role'] = 'patrol_officer';
                    }
                    if (Schema::hasColumn('users_public', 'is_on_duty')) {
                        $updatePublic['is_on_duty'] = false;
                    }
                    if (Schema::hasColumn('users_public', 'assigned_station_id')) {
                        $updatePublic['assigned_station_id'] = $officer['assigned_station_id'];
                    }
                    if (Schema::hasColumn('users_public', 'email_verified_at')) {
                        $updatePublic['email_verified_at'] = now();
                    }
                    DB::table('users_public')->where('email', $officer['email'])->update($updatePublic);
                    $this->command->info("Updated users_public: {$officer['email']}");
                }
            } catch (\Exception $e) {
                $this->command->error("users_public error for {$officer['email']}: " . $e->getMessage());
                Log::error("PatrolOfficerSeeder users_public error: " . $e->getMessage());
            }
        }

        $this->command->info('PatrolOfficerSeeder completed.');
    }
}

